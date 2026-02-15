# Architecture

**Analysis Date:** 2026-02-15

## Pattern Overview

**Overall:** WordPress Plugin with Layered Architecture

**Key Characteristics:**
- Plugin-based architecture leveraging WordPress hooks and actions
- Separation between WordPress integration layer and API clients
- Multi-shortcode system for different UI requirements (single link creation, dashboard, client links page)
- Integration with three external APIs: Traffic Portal, SnapCapture, and AI Short Code generation
- Class-based OOP design with dependency injection

## Layers

**WordPress Integration Layer:**
- Purpose: Bridge between WordPress ecosystem and application logic
- Location: `includes/class-tp-link-shortener.php`, `includes/class-tp-admin-settings.php`
- Contains: Plugin lifecycle management, settings registration, WordPress hooks
- Depends on: WordPress core APIs, component classes
- Used by: WordPress core during plugin initialization and admin operations

**Shortcode Rendering Layer:**
- Purpose: Handle different content shortcodes and asset loading for specific contexts
- Location: `includes/class-tp-shortcode.php`, `includes/class-tp-dashboard-shortcode.php`, `includes/class-tp-client-links-shortcode.php`
- Contains: Shortcode registration, template inclusion, context-specific asset enqueuing
- Depends on: WordPress shortcode API, assets layer, template files
- Used by: WordPress when shortcodes are encountered on pages

**Assets Management Layer:**
- Purpose: Centralize CSS and JavaScript enqueuing based on context
- Location: `includes/class-tp-assets.php`
- Contains: CSS/JS registration, dependency management, localization
- Depends on: WordPress script/style APIs
- Used by: Shortcode handlers and plugin initialization

**API Wrapper Layer:**
- Purpose: Provide unified abstraction over three external API clients and handle AJAX requests
- Location: `includes/class-tp-api-handler.php` (1580 lines)
- Contains: API client initialization, AJAX handlers (15+ methods), REST route registration, request validation
- Depends on: TrafficPortal, SnapCapture, ShortCode API clients; WordPress AJAX/REST APIs
- Used by: Frontend JavaScript via AJAX; WordPress REST API consumers

**API Client Layers:**
- Purpose: Direct communication with external services
- Locations:
  - `includes/TrafficPortal/TrafficPortalApiClient.php` - Creates/manages short links
  - `includes/SnapCapture/SnapCaptureClient.php` - Screenshot capture
  - `includes/ShortCode/GenerateShortCodeClient.php` - AI-powered short code suggestions
- Contains: HTTP client implementations, request/response DTOs, exception handling
- Depends on: HTTP layer, DTO classes
- Used by: API wrapper layer (TP_API_Handler)

**Template Layer:**
- Purpose: Render UI for shortcodes
- Location: `templates/` directory
- Contains: PHP templates that generate HTML for form, dashboard, and client links pages
- Depends on: Frontend JavaScript and CSS assets
- Used by: Shortcode handlers via output buffering

**Frontend JavaScript Layer:**
- Purpose: Client-side form handling, AJAX communication, UI interaction
- Location: `assets/js/` directory
- Contains: `frontend.js` (main form), `dashboard.js` (dashboard table), `client-links.js` (advanced management)
- Depends on: jQuery, Bootstrap, Chart.js, QRCode.js, FingerprintJS
- Used by: Templates rendered by shortcodes

**Frontend CSS Layer:**
- Purpose: Styling for user interfaces
- Location: `assets/css/` directory
- Contains: `frontend.css` (shared), `dashboard.css`, `client-links.css`
- Depends on: Bootstrap CSS, Font Awesome
- Used by: All shortcodes

## Data Flow

**Link Creation Flow:**

1. User fills form in `shortcode-template.php` rendered by `TP_Shortcode`
2. Frontend JavaScript (`frontend.js`) captures form submission and validates URL/shortcode
3. JavaScript sends AJAX POST to `wp_ajax_tp_create_link` action
4. `TP_API_Handler::ajax_create_link()` receives request and validates nonce
5. API handler calls `TrafficPortalApiClient::createMaskedRecord()` with sanitized request
6. HTTP layer (`TrafficPortal\Http\CurlHttpClient`) makes CURL request to Traffic Portal API endpoint
7. Response parsed and returned as JSON to frontend
8. JavaScript displays success/error message and populated short link UI

**Dashboard List Flow:**

1. `TP_Dashboard_Shortcode` renders `dashboard-template.php` for logged-in users
2. Dashboard JavaScript (`dashboard.js`) loads on page via AJAX
3. JavaScript calls `wp_ajax_tp_get_user_map_items` with pagination parameters
4. `TP_API_Handler::ajax_get_user_map_items()` calls `TrafficPortalApiClient::getMapItems()`
5. Client queries Traffic Portal API for user's links
6. Response paginated and formatted
7. JavaScript renders table with pagination controls

**Screenshot Capture Flow:**

1. User submits URL in form
2. Form submission triggers `wp_ajax_tp_capture_screenshot` before link creation
3. `TP_API_Handler::ajax_capture_screenshot()` calls `SnapCaptureClient::captureScreenshot()`
4. Client makes request to SnapCapture API with target URL
5. Base64-encoded image returned
6. Stored in JavaScript localStorage via storage service
7. Displayed as preview image before link finalization

**State Management:**

- **Server-side:** Plugin options stored in WordPress options table (`wp_options`)
- **Client-side:** Session data in browser localStorage via `storage-service-standalone.js`; FingerprintJS generates persistent user identifier
- **API State:** Traffic Portal API is authoritative source for link data; dashboard queries it directly

## Key Abstractions

**TP_API_Handler:**
- Purpose: Central AJAX/REST dispatcher and API orchestrator
- Examples: `includes/class-tp-api-handler.php`
- Pattern: Facade pattern wrapping three different API clients; handles all HTTP request routing

**Shortcode Classes:**
- Purpose: Encapsulate different content experiences
- Examples: `TP_Shortcode`, `TP_Dashboard_Shortcode`, `TP_Client_Links_Shortcode`
- Pattern: Template Method pattern - each renders different template and enqueues context-specific assets

**API Client Classes:**
- Purpose: Encapsulate HTTP communication and data transformation
- Examples: `TrafficPortalApiClient`, `SnapCaptureClient`, `GenerateShortCodeClient`
- Pattern: Client pattern with DTO request/response objects; exception-based error handling

**DTO (Data Transfer Objects):**
- Purpose: Type-safe data structures for API communication
- Examples: `TrafficPortal\DTO\CreateMapRequest`, `TrafficPortal\DTO\CreateMapResponse`
- Pattern: Value objects with validation; `toArray()` for serialization

**HTTP Client Interface:**
- Purpose: Abstract HTTP layer for testability
- Location: `includes/TrafficPortal/Http/HttpClientInterface.php`
- Pattern: Strategy pattern allowing mock implementations for testing

## Entry Points

**Plugin Initialization:**
- Location: `tp-link-shortener.php` root file
- Triggers: WordPress `plugins_loaded` hook
- Responsibilities: Define constants, require class files, instantiate main plugin class

**Main Plugin Class:**
- Location: `includes/class-tp-link-shortener.php`
- Triggers: On `plugins_loaded` action
- Responsibilities: Singleton instantiation, component initialization, WordPress hook registration

**AJAX Entry Points:**
- Location: `includes/class-tp-api-handler.php`
- Triggers: WordPress AJAX actions (wp_ajax_* and wp_ajax_nopriv_*)
- Responsibilities: 15+ AJAX handlers for link creation, validation, updates, suggestions

**REST API Entry Points:**
- Location: `includes/class-tp-api-handler.php::register_rest_routes()`
- Triggers: WordPress `rest_api_init` hook
- Responsibilities: Register REST endpoints (implementation in progress)

**Shortcode Registration:**
- Location: Individual shortcode class constructors
- Triggers: WordPress `init` action via shortcode constructors
- Responsibilities: Register shortcodes `[tp_link_shortener]`, `[tp_link_dashboard]`, `[tp_client_links]`

## Error Handling

**Strategy:** Exception-based error handling with try-catch in API handlers; user-friendly messages returned to frontend

**Patterns:**

- **API Exceptions:** TrafficPortal client throws specific exceptions (`AuthenticationException`, `ValidationException`, `RateLimitException`, `NetworkException`, `ApiException`)
- **AJAX Error Response:** Errors caught and returned as JSON with `success: false` and error message
- **User Feedback:** Frontend JavaScript displays error toast/message; server logs detailed errors to `error_log()`
- **Nonce Validation:** All AJAX requests validated with WordPress nonces before processing
- **Input Sanitization:** User inputs sanitized via WordPress functions (`sanitize_text_field()`, `esc_url()`)

## Cross-Cutting Concerns

**Logging:**
- Approach: File-based logging via `error_log()` and custom `log_to_file()` method in `TrafficPortalApiClient`
- Location: Log files written to system temp or designated logs directory
- Verbosity: Full request/response payloads logged for debugging API issues

**Validation:**
- URL validation: Custom JavaScript validator (`assets/js/url-validator.js`) plus backend checks
- Shortcode validation: JavaScript suggests alternatives if key is taken via `ajax_validate_key`
- Request validation: Nonce checks on all AJAX handlers; type checking in API client methods

**Authentication:**
- WordPress User Authentication: Checks `is_user_logged_in()` for dashboard/client links shortcodes
- API Authentication: Sent via `API_KEY` from `wp-config.php` constant; set as Bearer token in TrafficPortal client
- AJAX Security: Nonce validation via `wp_verify_nonce()` on all AJAX handlers

**Configuration:**
- Plugin settings stored as WordPress options (prefix: `tp_link_shortener_*`)
- Settings editable via admin page (`includes/class-tp-admin-settings.php`)
- API endpoint and key sourced from `wp-config.php` constants (`TP_API_ENDPOINT`, `API_KEY`, `SNAPCAPTURE_API_KEY`)

---

*Architecture analysis: 2026-02-15*
