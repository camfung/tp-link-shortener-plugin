# URL Validator Library

A comprehensive JavaScript library for validating URLs with online checks, redirect handling, and content-type validation.

## Features

- **Online Validation**: Performs HTTP HEAD requests to validate URL availability
- **Redirect Detection**: Identifies and handles permanent (301, 308) and temporary (302, 307) redirects
- **Content-Type Validation**: Validates content types based on user authentication status
- **SSL/TLS Validation**: Detects SSL certificate errors
- **User Authentication Awareness**: Different validation rules for guest vs. registered users
- **Visual Feedback**: Provides border colors and messages for different validation states
- **Debounced Validation**: Built-in debouncing for real-time input validation
- **CORS Proxy Support**: Works with backend proxy to bypass CORS restrictions

## Validation Rules

### For Guest Users
- ✅ Static web pages (text/html, text/plain)
- ✅ Images (JPEG, PNG, GIF, WebP, SVG)
- ❌ Video, audio, and other file types
- ❌ Temporary redirects (302, 303, 307)
- ❌ Protected/authenticated resources (401, 403)
- ⚠️  Permanent redirects (301, 308) - Warning with suggestion to update

### For Registered Users
- ✅ All content types allowed
- ✅ Temporary redirects allowed
- ✅ Protected resources (401, 403) - Warning only
- ⚠️  Permanent redirects (301, 308) - Warning with suggestion to update

## Installation

Include the library in your HTML:

```html
<script src="assets/js/url-validator.js"></script>
```

Or via WordPress enqueue:

```php
wp_enqueue_script(
    'tp-url-validator',
    plugin_url . '/assets/js/url-validator.js',
    array(),
    '1.0.0',
    true
);
```

## Usage

### Basic Usage

```javascript
// Initialize the validator
const validator = new URLValidator({
    isUserRegistered: false,  // User authentication status
    proxyUrl: '/api/validate-url',  // Optional proxy URL for CORS
    timeout: 10000  // Request timeout in milliseconds
});

// Validate a URL
const result = await validator.validateURL('https://example.com');

if (result.valid) {
    console.log('URL is valid:', result.message);
} else {
    console.error('URL is invalid:', result.message);
}
```

### Validation Result Format

```javascript
{
    valid: true,           // Overall validity
    isError: false,        // Is this an error?
    isWarning: false,      // Is this a warning?
    errorType: null,       // Error type constant
    message: 'Success',    // User-friendly message
    borderColor: '#28a745', // Suggested border color
    redirectLocation: null  // For redirects, the target URL
}
```

### Using with Form Inputs

```javascript
const validator = new URLValidator({
    isUserRegistered: isLoggedIn
});

// Apply validation to input element
const result = await validator.validateURL(urlString);
validator.applyValidationToElement(
    inputElement,
    result,
    messageElement  // Optional message display element
);
```

### Debounced Validation for Real-Time Input

```javascript
const validator = new URLValidator({
    isUserRegistered: isLoggedIn,
    proxyUrl: '/api/validate-url'
});

// Create debounced validator
const debouncedValidate = validator.createDebouncedValidator(
    (result, url) => {
        console.log('Validation complete:', result);
    },
    500  // Debounce delay in ms
);

// Use in input event handler
inputElement.addEventListener('input', (e) => {
    const url = e.target.value.trim();
    debouncedValidate(url, inputElement, messageElement);
});
```

## Error Types

The library defines the following error type constants:

```javascript
URLValidator.ErrorTypes = {
    INVALID_URL: 'invalid_url',
    NOT_AVAILABLE: 'not_available',
    PROTECTED: 'protected',
    SSL_ERROR: 'ssl_error',
    REDIRECT_PERMANENT: 'redirect_permanent',
    REDIRECT_TEMPORARY: 'redirect_temporary',
    INVALID_CONTENT_TYPE: 'invalid_content_type',
    NETWORK_ERROR: 'network_error'
}
```

## Border Colors

Predefined colors for different validation states:

```javascript
URLValidator.BorderColors = {
    ERROR: '#dc3545',      // Red for errors
    WARNING: '#ffc107',    // Yellow/amber for warnings
    SUCCESS: '#28a745',    // Green for success
    DEFAULT: '#ced4da'     // Default gray
}
```

## Status Codes

HTTP status code groupings:

```javascript
URLValidator.StatusCodes = {
    PERMANENT_REDIRECT: [301, 308],
    TEMPORARY_REDIRECT: [302, 303, 307],
    SUCCESS: [200, 201, 202, 203, 204, 205, 206]
}
```

## CORS Proxy Setup

For production use, you need a backend proxy to avoid CORS issues. Example WordPress implementation:

```php
public function ajax_validate_url() {
    $url = isset($_GET['url']) ? esc_url_raw($_GET['url']) : '';

    $response = wp_remote_head($url, array(
        'timeout' => 10,
        'redirection' => 0,
        'sslverify' => true
    ));

    echo json_encode(array(
        'ok' => !is_wp_error($response),
        'status' => wp_remote_retrieve_response_code($response),
        'headers' => wp_remote_retrieve_headers($response)->getAll()
    ));
    wp_die();
}
```

## Testing

The library includes comprehensive unit tests using Vitest:

```bash
npm test -- assets/js/url-validator.test.js
```

Test coverage includes:
- URL format validation
- Content type validation
- Error result creation
- Warning result creation
- Success result creation
- Element validation application
- Debounced validation
- All static constants

## Integration with WordPress Plugin

This library is integrated with the TP Link Shortener plugin:

1. **Enqueued** via `class-tp-assets.php`
2. **Initialized** in `frontend.js` with user auth status
3. **Proxy endpoint** provided in `class-tp-api-handler.php`
4. **Real-time validation** on the destination URL input field

## API Reference

### Constructor Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `isUserRegistered` | Boolean | `false` | Whether the user is logged in/registered |
| `proxyUrl` | String | `null` | Backend proxy URL for CORS handling |
| `timeout` | Number | `10000` | Request timeout in milliseconds |

### Methods

#### `isValidURLFormat(urlString)`
Validates if a string is a properly formatted URL.

#### `validateURL(urlString)`
Performs comprehensive online validation of a URL.

#### `applyValidationToElement(inputElement, validationResult, messageElement)`
Applies validation result styling and messages to DOM elements.

#### `createDebouncedValidator(callback, delay)`
Creates a debounced validation function for real-time input.

## Browser Compatibility

- Modern browsers with ES6+ support
- Fetch API support required
- AbortController support required

For older browsers, consider using polyfills.

## License

Part of the TP Link Shortener WordPress plugin.

## Version

1.0.0
