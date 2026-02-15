# Coding Conventions

**Analysis Date:** 2026-02-15

## Naming Patterns

**Files:**
- PHP classes: PascalCase with hyphens between words, e.g., `class-tp-api-handler.php`, `class-tp-admin-settings.php`
- JavaScript files: kebab-case, e.g., `frontend.js`, `url-validator.js`, `storage-service.js`
- Test files: append `.test.js` or `.spec.php` suffix, e.g., `rate-limit.test.js`, `CreateMapRequestTest.php`

**Functions:**
- PHP: snake_case for all functions and methods, e.g., `ajax_create_link()`, `generate_short_code()`, `init_client()`
- JavaScript: camelCase for all functions and methods, e.g., `handleSubmit()`, `getFingerprint()`, `cacheElements()`
- PHP constructors: use `__construct()` convention
- Methods in JavaScript objects: camelCase without function keyword, e.g., `init: async function()`, `bindEvents: function()`

**Variables:**
- PHP: snake_case, e.g., `$api_endpoint`, `$custom_key`, `$this->snapcapture_client`, `$destination`
- JavaScript: camelCase in functions/objects, e.g., `isLoggedIn`, `currentUrl`, `debouncedValidate`
- JavaScript: UPPER_SNAKE_CASE for constants in static classes, e.g., `static ErrorTypes`, `static StatusCodes`

**Types:**
- PHP DTO classes: PascalCase in namespace folders, e.g., `TrafficPortal\DTO\CreateMapRequest`, `SnapCapture\DTO\ScreenshotRequest`
- PHP exception classes: PascalCase in namespace, e.g., `TrafficPortal\Exception\ValidationException`, `TrafficPortal\Exception\RateLimitException`
- JavaScript class definitions: PascalCase, e.g., `class URLValidator`, `class StorageService`

## Code Style

**Formatting:**
- No explicit linting tool configured (no eslint.config.js, .prettierrc, or phpcs.xml found)
- PHP follows PSR-12 conventions implicitly with type declarations at file top: `declare(strict_types=1);`
- JavaScript files use `'use strict';` at function scope for safety
- Indentation: Apparent 4-space indentation in both PHP and JavaScript

**PHP Type System:**
- Strict types enforced: All PHP files declare `declare(strict_types=1);` at top
- Parameter types required: `public function ajax_create_link()`, `private function create_short_link(string $destination, string $key, int $uid, ?string $fingerprint = null): array`
- Return types specified: `: array`, `: void` on most methods in `class-tp-api-handler.php`
- Property types: Private properties declared with types, e.g., `private $client`, `private string $tpKey`

**JavaScript Language Features:**
- ES6 module style not used (no explicit imports/exports visible)
- jQuery library still actively used for DOM manipulation, e.g., `$('#tp-submit-btn')`, `$form.on('submit')`
- Async/await used for asynchronous operations: `init: async function()`, `submitCreate: async function()`
- IIFE (Immediately Invoked Function Expression) pattern used: `(function($) { ... })(jQuery)`
- Strict mode used at top of functions: `'use strict';`

## Import Organization

**PHP:**
Order in `class-tp-api-handler.php`:
1. PHP namespace declaration: `namespace TrafficPortal\TrafficPortalApiClient;`
2. Use statements for all dependencies:
   ```php
   use TrafficPortal\DTO\CreateMapRequest;
   use TrafficPortal\Exception\AuthenticationException;
   use SnapCapture\SnapCaptureClient;
   use ShortCode\GenerateShortCodeClient;
   ```

**JavaScript:**
- jQuery used as global `$`
- External scripts loaded via WordPress enqueue system
- No ES6 imports observed; dependencies injected through global scope (e.g., `tpAjax` object for WordPress AJAX)
- Custom validation module available as global: `URLValidator` class from `url-validator.js`
- StorageService imported in test files: `import { StorageService } from '../assets/js/storage-service.js';`

## Error Handling

**PHP Patterns:**
- Try-catch blocks wrap external API calls (TrafficPortalApiClient, SnapCaptureClient, GenerateShortCodeClient)
- Exception types caught separately:
  ```php
  catch (ValidationException $e) { ... }
  catch (AuthenticationException $e) { ... }
  catch (RateLimitException $e) { ... }
  catch (ApiException $e) { ... }
  ```
- WordPress JSON error responses: `wp_send_json_error(array('message' => ...))`
- WordPress JSON success responses: `wp_send_json_success($result['data'])`
- Error data includes optional `error_type` field for specialized handling (e.g., `rate_limit` for HTTP 429)
- HTTP codes tracked in error responses: `$error_data['http_code']` for client-side handling

**JavaScript Patterns:**
- Try-catch blocks around localStorage access (can fail in private mode):
  ```javascript
  try {
    const value = window.localStorage.getItem('key');
  } catch (error) {
    // Handle gracefully
  }
  ```
- Async errors in promises handled with `.catch()` or try-catch in async functions
- AJAX callbacks use `error: function(xhr, status, error)` pattern
- Debug-aware error logging via `TPDebug.warn()`, `TPDebug.error()`, `TPDebug.log()`
- Fallback handling for failed operations (e.g., FingerprintJS fallback script when primary CDN fails)
- Missing or null values checked explicitly: `if (empty($value))`, `if (!value)`, `value || null`

## Logging

**Framework:** PHP uses `error_log()`, JavaScript uses `console.log()/warn()/error()` with debug utility

**Patterns:**

**PHP:**
- `error_log('TP Link Shortener: ' . message)` for all error_log calls (consistent prefix)
- File-based logging to `/logs/` directory via `log_to_file()` method
- Log entries include request boundaries: `=== CREATE LINK REQUEST START ===`, `=== CREATE LINK REQUEST END ===`
- Sequential logging of request flow for debugging: initialization, validation, API calls, results

**JavaScript:**
- Debug utility `TPDebug` gate all logging based on feature flags stored in localStorage
- Feature keys enable targeted debugging: `'all'`, `'init'`, `'fingerprint'`, `'validation'`, `'submit'`, etc.
- Pattern: `TPDebug.log('feature', message)` - only logs if feature enabled
- Console methods mocked in test setup: `global.console = { error: vi.fn(), warn: vi.fn(), log: vi.fn() }`
- localStorage fallback for environments where localStorage unavailable

## Comments

**When to Comment:**
- Complex validation logic documented inline with explanation of rules
- Request flow boundaries marked with clear separators for debugging
- Configuration priorities explained (WordPress constant > env var > .env file)
- Edge cases and special handling noted inline
- Business logic constraints documented (e.g., anonymous user rate limits, premium feature checks)

**JSDoc/TSDoc:**
- Used in JavaScript classes:
  ```javascript
  /**
   * Create a new URL validator instance
   * @param {Object} options - Configuration options
   * @param {boolean} options.isUserRegistered - Whether user is registered
   * @param {string} options.proxyUrl - Optional proxy URL for CORS
   * @param {number} options.timeout - Request timeout in milliseconds
   */
  constructor(options = {}) { ... }
  ```

- Used in PHP methods:
  ```php
  /**
   * AJAX handler for creating short links
   */
  public function ajax_create_link() { ... }

  /**
   * Create short link via API
   */
  private function create_short_link(string $destination, string $key, int $uid, ?string $fingerprint = null): array { ... }
  ```

- File-level documentation blocks at top of files explaining purpose and scope

## Function Design

**Size:**
- PHP methods range from 50-150 lines (including error handling and logging)
- JavaScript methods range from 30-100 lines
- Longer methods broken into logical sections with explanatory comments

**Parameters:**
- PHP: Required parameters first, optional/nullable last
- PHP: Named arguments used in constructors where multiple parameters:
  ```php
  new CreateMapRequest(
      uid: 125,
      tpKey: 'mylink',
      domain: 'dev.trfc.link',
      destination: 'https://example.com'
  )
  ```
- JavaScript: Object destructuring for multiple options: `constructor(options = { isUserRegistered, proxyUrl, timeout })`

**Return Values:**
- PHP: Always specify return type (`: array`, `: void`, `: bool`)
- PHP API methods return shape: `['success' => bool, 'data' => mixed, 'message' => string]`
- JavaScript: Returns values, error states, or promises (async functions)
- Null/undefined used explicitly for optional values

## Module Design

**Exports:**
- PHP: Public methods prefixed with `public`, private implementation details with `private`
- JavaScript: Global object pattern (`TPLinkShortener`) with method definitions
- Test classes use static methods for utilities (e.g., `StorageService.saveShortcodeData()`)

**Barrel Files:**
- Not used; each file imports its direct dependencies via use statements or script tags

## Constants and Configuration

**PHP:**
- Configuration loaded from environment at runtime in `init_client()`:
  - `TP_LINK_SHORTENER_PLUGIN_DIR` - plugin root
  - API keys from WordPress constants or environment variables
- Class constants for defaults: seen in DTO objects (empty string defaults, status 'active', etc.)

**JavaScript:**
- Static class properties for constants:
  ```javascript
  static ErrorTypes = {
    INVALID_URL: 'invalid_url',
    NOT_AVAILABLE: 'not_available',
    PROTECTED: 'protected'
  };
  static BorderColors = {
    ERROR: '#dc3545',
    WARNING: '#ffc107',
    SUCCESS: '#28a745'
  };
  ```
- Configuration object in TPLinkShortener:
  ```javascript
  config: {
    maxLength: 2000,
    minLength: 10,
    invalidChars: /[<>"{}|\\^`\[\]]/g,
    urlPattern: /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/i
  }
  ```

---

*Convention analysis: 2026-02-15*
