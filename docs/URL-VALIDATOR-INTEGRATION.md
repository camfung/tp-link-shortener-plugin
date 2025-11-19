# URL Validator Integration Guide

This document explains how the URL Validator library is integrated and used within the TP Link Shortener WordPress plugin.

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Component Flow](#component-flow)
4. [File Structure](#file-structure)
5. [How It Works](#how-it-works)
6. [Validation Flow](#validation-flow)
7. [Error Handling](#error-handling)
8. [Customization](#customization)
9. [Troubleshooting](#troubleshooting)

## Overview

The URL Validator is a client-side JavaScript library that provides real-time URL validation for the link shortener form. It validates URLs by:

- Checking URL format
- Making HTTP HEAD requests to verify availability
- Detecting redirects (permanent and temporary)
- Validating content types
- Checking for SSL/TLS errors
- Verifying protected resources

The validation respects user authentication status, applying different rules for guest users vs. registered users.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    User Interface (Form)                     │
│                 templates/shortcode-template.php             │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│              Frontend JavaScript (frontend.js)               │
│  • Initializes URLValidator                                  │
│  • Handles user input                                        │
│  • Triggers debounced validation                            │
│  • Applies visual feedback                                   │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│           URL Validator Library (url-validator.js)           │
│  • Validates URL format                                      │
│  • Makes validation requests                                 │
│  • Checks content types                                      │
│  • Returns validation results                                │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│        CORS Proxy (class-tp-api-handler.php)                │
│  • Receives validation requests via AJAX                     │
│  • Makes HEAD request to target URL                         │
│  • Returns headers and status code                          │
└─────────────────────────────────────────────────────────────┘
```

## Component Flow

### 1. Asset Loading (`includes/class-tp-assets.php`)

The URL validator and its dependencies are loaded in the correct order:

```php
// 1. Load URL validator library
wp_enqueue_script(
    'tp-url-validator',
    TP_LINK_SHORTENER_PLUGIN_URL . 'assets/js/url-validator.js',
    array(),
    TP_LINK_SHORTENER_VERSION,
    true
);

// 2. Load frontend script (depends on url-validator)
wp_enqueue_script(
    'tp-link-shortener-js',
    TP_LINK_SHORTENER_PLUGIN_URL . 'assets/js/frontend.js',
    array('jquery', 'tp-qrcode', 'tp-bootstrap-js', 'tp-storage-service', 'tp-url-validator'),
    TP_LINK_SHORTENER_VERSION,
    true
);

// 3. Localize configuration
wp_localize_script('tp-link-shortener-js', 'tpLinkShortener', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'isLoggedIn' => is_user_logged_in(),
    // ... other settings
));
```

**Location**: `includes/class-tp-assets.php:76-92`

### 2. Initialization (`assets/js/frontend.js`)

When the page loads, the frontend script initializes the URL validator:

```javascript
/**
 * Initialize URL Validator
 */
initializeURLValidator: function() {
    // Check if URLValidator class is available
    if (typeof URLValidator === 'undefined') {
        console.warn('URLValidator library not loaded. Online validation disabled.');
        return;
    }

    // Initialize URLValidator with current user authentication status
    this.urlValidator = new URLValidator({
        isUserRegistered: tpLinkShortener.isLoggedIn || false,
        proxyUrl: tpLinkShortener.ajaxUrl + '?action=tp_validate_url',
        timeout: 10000
    });

    // Create debounced validator function
    this.debouncedValidate = this.urlValidator.createDebouncedValidator(
        this.handleValidationResult.bind(this),
        800 // 800ms delay
    );
}
```

**Location**: `assets/js/frontend.js:96-118`

**Key Points**:
- Checks if URLValidator is loaded
- Passes user authentication status from WordPress
- Sets proxy URL to WordPress AJAX endpoint
- Creates debounced validator with 800ms delay

### 3. User Input Handling (`assets/js/frontend.js`)

When a user types in the destination URL field:

```javascript
/**
 * Handle input event (real-time validation and sanitization)
 */
handleInput: function(e) {
    let value = e.target.value;

    // Remove invalid characters in real-time
    const cleaned = value.replace(this.config.invalidChars, '');

    if (cleaned !== value) {
        this.$destinationInput.val(cleaned);
        value = cleaned;
    }

    // Check length
    if (value.length > this.config.maxLength) {
        this.$destinationInput.val(value.substring(0, this.config.maxLength));
        this.showError('URL too long (max 2000 characters)');
        return;
    }

    // Remove validation classes while typing
    this.$destinationInput.removeClass('is-invalid is-valid');

    // Hide error while typing
    if (value.length > 0) {
        this.hideError();
    }

    // Trigger online validation if URLValidator is available
    if (this.urlValidator && this.debouncedValidate && value.trim().length > 0) {
        this.debouncedValidate(
            value.trim(),
            this.$destinationInput[0],
            this.$validationMessage[0]
        );
    }
}
```

**Location**: `assets/js/frontend.js:291-328`

**Key Points**:
- Sanitizes input by removing invalid characters
- Enforces maximum length (2000 characters)
- Triggers debounced validation after user stops typing (800ms)
- Passes DOM elements to validator for direct styling

### 4. URL Validation Process (`assets/js/url-validator.js`)

The validator performs comprehensive checks:

```javascript
async validateURL(urlString) {
    // 1. Check URL format
    if (!this.isValidURLFormat(urlString)) {
        return this.createErrorResult(
            URLValidator.ErrorTypes.INVALID_URL,
            'Invalid URL format. Please enter a valid HTTP or HTTPS URL.',
            URLValidator.BorderColors.ERROR
        );
    }

    try {
        // 2. Perform HEAD request to get headers
        const response = await this.fetchHeaders(urlString);

        // 3. Check for authentication/protected resources FIRST
        if (response.status === 401 || response.status === 403) {
            if (this.isUserRegistered) {
                return this.createWarningResult(/* ... */);
            } else {
                return this.createErrorResult(/* ... */);
            }
        }

        // 4. Check if URL is available (other 4xx errors)
        if (!response.ok && response.status >= 400) {
            return this.createErrorResult(/* ... */);
        }

        // 5. Check for permanent redirects
        if (URLValidator.StatusCodes.PERMANENT_REDIRECT.includes(response.status)) {
            const location = response.headers.get('Location');
            return this.createWarningResult(
                URLValidator.ErrorTypes.REDIRECT_PERMANENT,
                `Permanent redirect detected. Consider replacing with: ${location}`,
                URLValidator.BorderColors.WARNING,
                { redirectLocation: location }
            );
        }

        // 6. Check for temporary redirects
        if (URLValidator.StatusCodes.TEMPORARY_REDIRECT.includes(response.status)) {
            if (!this.isUserRegistered) {
                return this.createErrorResult(/* ... */);
            }
        }

        // 7. Check content type
        const contentType = response.headers.get('Content-Type');
        const contentTypeValidation = this.validateContentType(contentType);
        if (!contentTypeValidation.valid) {
            return contentTypeValidation.result;
        }

        // 8. All validations passed
        return this.createSuccessResult(
            'URL is valid and accessible.',
            URLValidator.BorderColors.SUCCESS
        );

    } catch (error) {
        // Handle SSL/TLS errors
        if (error.message && error.message.includes('SSL')) {
            return this.createErrorResult(/* SSL error */);
        }

        // Handle network errors
        return this.createErrorResult(/* Network error */);
    }
}
```

**Location**: `assets/js/url-validator.js:85-184`

### 5. CORS Proxy Request (`assets/js/url-validator.js`)

To avoid CORS issues, requests go through a WordPress AJAX proxy:

```javascript
async fetchHeaders(urlString) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.timeout);

    try {
        let fetchUrl = urlString;
        let options = {
            method: 'HEAD',
            signal: controller.signal,
            redirect: 'manual',
            mode: 'cors'
        };

        // If proxy URL is configured, use it to avoid CORS issues
        if (this.proxyUrl) {
            fetchUrl = `${this.proxyUrl}&url=${encodeURIComponent(urlString)}`;
            options.method = 'GET'; // Proxy uses GET
        }

        const response = await fetch(fetchUrl, options);
        clearTimeout(timeoutId);

        // If using proxy, transform the response to match expected format
        if (this.proxyUrl) {
            const data = await response.json();

            // Create a mock Response-like object
            return {
                ok: data.ok,
                status: data.status,
                headers: {
                    get: (key) => {
                        const lowerKey = key.toLowerCase();
                        for (const headerKey in data.headers) {
                            if (headerKey.toLowerCase() === lowerKey) {
                                return data.headers[headerKey];
                            }
                        }
                        return null;
                    }
                }
            };
        }

        return response;
    } catch (error) {
        if (error.name === 'AbortError') {
            throw new Error('Request timeout');
        }
        throw error;
    }
}
```

**Location**: `assets/js/url-validator.js:186-246`

### 6. WordPress AJAX Proxy (`includes/class-tp-api-handler.php`)

The proxy endpoint handles the actual HTTP request:

```php
public function ajax_validate_url() {
    // Get the URL to validate
    $url = isset($_GET['url']) ? esc_url_raw($_GET['url']) : '';

    if (empty($url)) {
        wp_send_json_error(array('message' => 'URL parameter is required'), 400);
        return;
    }

    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        wp_send_json_error(array('message' => 'Invalid URL format'), 400);
        return;
    }

    // Only allow http and https protocols
    $parsed_url = parse_url($url);
    if (!isset($parsed_url['scheme']) ||
        !in_array($parsed_url['scheme'], array('http', 'https'))) {
        wp_send_json_error(array(
            'message' => 'Only HTTP and HTTPS protocols are allowed'
        ), 400);
        return;
    }

    // Make HEAD request using WordPress HTTP API
    $response = wp_remote_head($url, array(
        'timeout' => 10,
        'redirection' => 0, // Don't follow redirects automatically
        'sslverify' => true,
        'user-agent' => 'TP-Link-Shortener-Validator/1.0'
    ));

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();

        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(array(
            'ok' => false,
            'status' => 0,
            'headers' => array(),
            'error' => $error_message
        ));
        wp_die();
    }

    // Get response data
    $status_code = wp_remote_retrieve_response_code($response);
    $headers = wp_remote_retrieve_headers($response);

    // Convert headers to array
    $headers_array = array();
    if (is_object($headers)) {
        $headers_array = $headers->getAll();
    } elseif (is_array($headers)) {
        $headers_array = $headers;
    }

    // Return response in format expected by URLValidator
    header('Content-Type: application/json');
    echo json_encode(array(
        'ok' => $status_code >= 200 && $status_code < 400,
        'status' => $status_code,
        'headers' => $headers_array
    ));
    wp_die();
}
```

**Location**: `includes/class-tp-api-handler.php:405-481`

**Security Features**:
- URL sanitization with `esc_url_raw()`
- Protocol validation (only HTTP/HTTPS)
- WordPress nonce verification (inherited from AJAX system)
- No user-controlled headers

### 7. Visual Feedback (`assets/js/url-validator.js`)

The validator applies visual feedback to the form:

```javascript
applyValidationToElement(inputElement, validationResult, messageElement = null) {
    if (!inputElement) {
        console.error('Input element is required');
        return;
    }

    // Apply border color
    inputElement.style.borderColor = validationResult.borderColor;
    inputElement.style.borderWidth = '2px';

    // Set custom validity for HTML5 validation
    if (validationResult.isError) {
        inputElement.setCustomValidity(validationResult.message);
    } else {
        inputElement.setCustomValidity('');
    }

    // Display message if message element is provided
    if (messageElement) {
        messageElement.textContent = validationResult.message;
        messageElement.className = validationResult.isError ? 'error-message' :
                                   validationResult.isWarning ? 'warning-message' :
                                   'success-message';
    }
}
```

**Location**: `assets/js/url-validator.js:348-373`

## Validation Flow

Here's a step-by-step flow of what happens when a user enters a URL:

```
1. User types in URL field
   └─> frontend.js:handleInput()

2. Input is sanitized (800ms debounce timer starts)
   └─> Invalid characters removed
   └─> Length checked

3. After 800ms of no typing, validation begins
   └─> frontend.js:debouncedValidate()

4. URL format check (client-side)
   └─> url-validator.js:isValidURLFormat()
   └─> If invalid: Show error immediately

5. Online validation request
   └─> url-validator.js:validateURL()
   └─> url-validator.js:fetchHeaders()

6. Request sent to WordPress proxy
   └─> AJAX GET: /wp-admin/admin-ajax.php?action=tp_validate_url&url=...

7. WordPress proxy makes HEAD request
   └─> class-tp-api-handler.php:ajax_validate_url()
   └─> wp_remote_head($url, ...)

8. Response returned to client
   └─> JSON: { ok, status, headers }

9. Validation checks performed
   └─> Check for 401/403 (protected)
   └─> Check for 404/5xx (not available)
   └─> Check for 301/308 (permanent redirect)
   └─> Check for 302/307 (temporary redirect)
   └─> Check content-type
   └─> Check SSL errors

10. Result returned to frontend
    └─> url-validator.js returns validation result object

11. Visual feedback applied
    └─> url-validator.js:applyValidationToElement()
    └─> Border color changed
    └─> Message displayed
    └─> HTML5 validity set

12. User sees feedback
    └─> Red border = Error (cannot submit)
    └─> Yellow border = Warning (can submit with caution)
    └─> Green border = Success (ready to submit)
```

## Error Handling

### Client-Side Errors

1. **Invalid URL Format**
   - Detected immediately (no server request)
   - Red border
   - Message: "Invalid URL format. Please enter a valid HTTP or HTTPS URL."

2. **Timeout**
   - Request timeout after 10 seconds
   - Red border
   - Message: "Unable to reach URL: Request timeout"

3. **Network Error**
   - JavaScript fetch error
   - Red border
   - Message: "Unable to reach URL: [error message]"

### Server-Side Errors

1. **Not Available (404, 5xx)**
   - HTTP status >= 400
   - Red border
   - Message: "URL not available (Status: [code])"

2. **Protected Resources (401, 403)**
   - **Guest users**: Red border, error
   - **Registered users**: Yellow border, warning
   - Message varies by user type

3. **SSL/TLS Errors**
   - Invalid certificate
   - Red border
   - Message: "SSL/TLS certificate error. The URL has an invalid or untrusted certificate."

### Warnings (Non-blocking)

1. **Permanent Redirects (301, 308)**
   - Yellow border
   - Message: "Permanent redirect detected. Consider replacing with: [target]"
   - Includes `redirectLocation` in result

2. **Temporary Redirects (302, 307) for Registered Users**
   - Yellow border
   - Allowed for registered users

3. **Content Type Restrictions**
   - **Guest users**: Only static pages and images
   - **Registered users**: All content types
   - Yellow/Red border depending on user type

## Customization

### Changing Validation Timeout

In `frontend.js:99-111`:

```javascript
this.urlValidator = new URLValidator({
    isUserRegistered: tpLinkShortener.isLoggedIn || false,
    proxyUrl: tpLinkShortener.ajaxUrl + '?action=tp_validate_url',
    timeout: 15000  // Change from 10000 to 15000 (15 seconds)
});
```

### Changing Debounce Delay

In `frontend.js:113-117`:

```javascript
this.debouncedValidate = this.urlValidator.createDebouncedValidator(
    this.handleValidationResult.bind(this),
    1000  // Change from 800 to 1000 (1 second)
);
```

### Adding Custom Content Types for Guest Users

In `url-validator.js:39-42`:

```javascript
static ContentTypes = {
    GUEST: [
        'text/html', 'text/plain',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf'  // Add PDF support for guests
    ],
    REGISTERED: []
}
```

### Customizing Border Colors

In `url-validator.js:24-29`:

```javascript
static BorderColors = {
    ERROR: '#ff0000',      // Change red
    WARNING: '#ff9900',    // Change amber
    SUCCESS: '#00ff00',    // Change green
    DEFAULT: '#cccccc'     // Change gray
}
```

### Disabling Specific Validations

To disable content-type validation, modify `validateURL()` in `url-validator.js:85-184`:

```javascript
// Comment out or remove content type check
// const contentType = response.headers.get('Content-Type');
// const contentTypeValidation = this.validateContentType(contentType);
// if (!contentTypeValidation.valid) {
//     return contentTypeValidation.result;
// }
```

## Troubleshooting

### Validation Not Working

**Symptom**: No validation occurs when typing in URL field

**Checks**:
1. Check browser console for errors
2. Verify URLValidator script is loaded:
   ```javascript
   console.log(typeof URLValidator);  // Should output "function"
   ```
3. Verify frontend.js initialization:
   ```javascript
   console.log(TPLinkShortener.urlValidator);  // Should be an object
   ```
4. Check if proxy URL is correct:
   ```javascript
   console.log(tpLinkShortener.ajaxUrl);  // Should be /wp-admin/admin-ajax.php
   ```

### CORS Errors

**Symptom**: "CORS policy" errors in browser console

**Solution**: The proxy should handle this. Check:
1. Is the proxy endpoint registered?
   ```php
   // In class-tp-api-handler.php:56-66
   add_action('wp_ajax_tp_validate_url', array($this, 'ajax_validate_url'));
   add_action('wp_ajax_nopriv_tp_validate_url', array($this, 'ajax_validate_url'));
   ```
2. Is the proxy URL correct in frontend.js?
3. Check WordPress AJAX endpoint is accessible

### Validation Always Returns "Not Available"

**Symptom**: All URLs show as unavailable

**Checks**:
1. Check server-side error log
2. Verify WordPress HTTP API is not blocked:
   ```php
   // Test in WordPress admin or plugin
   $response = wp_remote_head('https://google.com');
   var_dump(is_wp_error($response));  // Should be false
   ```
3. Check if your server allows outbound HTTP requests
4. Verify SSL certificates are up to date on server

### Slow Validation

**Symptom**: Validation takes too long

**Solutions**:
1. Increase timeout in frontend.js (default: 10000ms)
2. Increase debounce delay to reduce number of requests
3. Check server's DNS resolution speed
4. Check target URL's response time

### Visual Feedback Not Showing

**Symptom**: Border colors or messages don't appear

**Checks**:
1. Verify validation message element exists:
   ```javascript
   console.log($('#tp-url-validation-message').length);  // Should be 1
   ```
2. Check CSS isn't overriding border styles
3. Verify `applyValidationToElement` is being called:
   ```javascript
   // Add console.log in handleValidationResult
   handleValidationResult: function(result, url) {
       console.log('Validation result:', result);
       // ...
   }
   ```

### Authentication Status Not Recognized

**Symptom**: Registered users treated as guests (or vice versa)

**Checks**:
1. Verify WordPress login state:
   ```php
   var_dump(is_user_logged_in());  // In WordPress
   ```
2. Check localized script value:
   ```javascript
   console.log(tpLinkShortener.isLoggedIn);  // Should match login state
   ```
3. Verify URLValidator initialization:
   ```javascript
   console.log(TPLinkShortener.urlValidator.isUserRegistered);
   ```

## Performance Considerations

### Debouncing

The validator uses an 800ms debounce delay, meaning:
- Validation only triggers 800ms after the user stops typing
- Prevents excessive server requests
- Reduces server load
- Improves user experience

### Caching

Consider implementing caching for repeated validations:

```javascript
// In frontend.js, add a cache object
validationCache: {},

handleValidationResult: function(result, url) {
    // Cache the result
    this.validationCache[url] = {
        result: result,
        timestamp: Date.now()
    };

    // ... rest of method
}
```

Then check cache before validating:

```javascript
// In handleInput, before calling debouncedValidate
const cached = this.validationCache[value.trim()];
if (cached && (Date.now() - cached.timestamp < 60000)) { // 1 minute cache
    this.handleValidationResult(cached.result, value.trim());
    return;
}
```

### Request Limits

To prevent abuse, consider:
1. Implementing rate limiting on the WordPress proxy
2. Adding request throttling in JavaScript
3. Using WordPress transients to cache validation results server-side

## Integration Checklist

When deploying to production, verify:

- [ ] URLValidator library is enqueued
- [ ] Frontend.js has URL validator dependency
- [ ] AJAX endpoint is registered for both logged-in and non-logged-in users
- [ ] WordPress HTTP API is not blocked by server
- [ ] SSL verification is enabled on server
- [ ] User authentication status is properly passed
- [ ] Validation message element is created in DOM
- [ ] Border colors are visible (not overridden by CSS)
- [ ] All tests pass (`npm test`)
- [ ] Browser console shows no errors
- [ ] Network tab shows successful proxy requests
- [ ] Different validation rules work for guest vs. registered users

## Additional Resources

- [URLValidator API Reference](./README-URL-VALIDATOR.md)
- [Unit Tests](../../assets/js/url-validator.test.js)
- [WordPress HTTP API Documentation](https://developer.wordpress.org/plugins/http-api/)
- [Fetch API Documentation](https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API)

## Version History

- **1.0.0** (2025-01-18): Initial release with full validation support
