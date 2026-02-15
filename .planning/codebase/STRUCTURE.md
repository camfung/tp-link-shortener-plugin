# Codebase Structure

**Analysis Date:** 2026-02-15

## Directory Layout

```
tp-link-shortener-plugin/
├── tp-link-shortener.php           # Plugin entry point & bootstrap
├── includes/                        # Core plugin classes
│   ├── autoload.php                 # PSR-4 autoloader for API clients
│   ├── class-tp-link-shortener.php  # Main plugin singleton
│   ├── class-tp-admin-settings.php  # Admin settings page
│   ├── class-tp-assets.php          # Asset enqueuing manager
│   ├── class-tp-shortcode.php       # Basic form shortcode handler
│   ├── class-tp-dashboard-shortcode.php  # Dashboard shortcode handler
│   ├── class-tp-client-links-shortcode.php  # Client links page handler
│   ├── class-tp-api-handler.php     # API wrapper & AJAX handler (1580 lines)
│   ├── TrafficPortal/               # Traffic Portal API client
│   │   ├── TrafficPortalApiClient.php
│   │   ├── DTO/                     # Data Transfer Objects
│   │   │   ├── CreateMapRequest.php
│   │   │   ├── CreateMapResponse.php
│   │   │   ├── MapItem.php
│   │   │   ├── PaginatedMapItemsResponse.php
│   │   │   ├── FingerprintSearchResponse.php
│   │   │   ├── FingerprintRecord.php
│   │   │   ├── MapItemUsage.php
│   │   │   └── FingerprintRecordUsage.php
│   │   ├── Http/                    # HTTP transport layer
│   │   │   ├── HttpClientInterface.php
│   │   │   ├── CurlHttpClient.php
│   │   │   ├── HttpResponse.php
│   │   │   └── MockHttpClient.php
│   │   └── Exception/               # API exceptions
│   │       ├── ApiException.php
│   │       ├── AuthenticationException.php
│   │       ├── ValidationException.php
│   │       ├── NetworkException.php
│   │       ├── RateLimitException.php
│   │       └── PageNotFoundException.php
│   ├── SnapCapture/                 # Screenshot capture API client
│   │   ├── SnapCaptureClient.php
│   │   ├── DTO/
│   │   │   ├── ScreenshotRequest.php
│   │   │   └── ScreenshotResponse.php
│   │   ├── Http/
│   │   │   ├── HttpClientInterface.php
│   │   │   └── CurlHttpClient.php
│   │   └── Exception/
│   │       ├── ApiException.php
│   │       ├── ValidationException.php
│   │       └── NetworkException.php
│   └── ShortCode/                   # AI short code generation client
│       ├── GenerateShortCodeClient.php
│       ├── GenerationTier.php
│       ├── DTO/
│       │   ├── GenerateShortCodeRequest.php
│       │   └── GenerateShortCodeResponse.php
│       ├── Http/
│       │   ├── HttpClientInterface.php
│       │   └── CurlHttpClient.php
│       └── Exception/
│           ├── ApiException.php
│           ├── ValidationException.php
│           └── NetworkException.php
├── templates/                       # PHP templates for shortcodes
│   ├── shortcode-template.php       # [tp_link_shortener] form
│   ├── dashboard-template.php       # [tp_link_dashboard] table
│   └── client-links-template.php    # [tp_client_links] advanced page
├── assets/                          # Frontend CSS/JS
│   ├── css/
│   │   ├── frontend.css             # Shared styles (form, variables)
│   │   ├── dashboard.css            # Dashboard table styles
│   │   └── client-links.css         # Client links page styles
│   ├── js/
│   │   ├── frontend.js              # Main form logic
│   │   ├── dashboard.js             # Dashboard table logic
│   │   ├── client-links.js          # Client links logic
│   │   ├── qr-utils.js              # QR code utilities
│   │   ├── storage-service-standalone.js  # localStorage wrapper
│   │   ├── storage-service.js       # Alternative storage service
│   │   ├── url-validator.js         # URL validation
│   │   ├── validation-client.js     # Validation client
│   │   ├── fingerprintjs-v4-iife.min.js  # Browser fingerprinting
│   │   └── url-validator.test.js    # URL validator tests
│   └── Orange-cat.jpg               # Fallback screenshot
├── tests/                           # Test files
│   ├── Unit/                        # PHP unit tests
│   ├── Integration/                 # PHP integration tests
│   ├── e2e/                         # End-to-end tests
│   ├── *.test.js                    # Vitest JS tests
│   ├── setup.js                     # Test setup
│   └── logs/                        # Test log output
├── docs/                            # Documentation
│   ├── DASHBOARD-UI-REQUIREMENTS.md
│   └── error-handling-changes.md
├── logs/                            # Runtime logs
├── package.json                     # JavaScript dependencies (Vitest)
├── composer.json                    # PHP dependencies & autoload config
├── phpunit.xml                      # PHPUnit configuration
├── vitest.config.js                 # Vitest configuration
└── .env.test                        # Test environment variables
```

## Directory Purposes

**includes/:**
- Purpose: Core plugin functionality and API clients
- Contains: PHP classes implementing plugin logic and API integrations
- Key files: `class-tp-api-handler.php` (largest, ~1580 lines), `class-tp-link-shortener.php` (singleton entry)

**includes/TrafficPortal/:**
- Purpose: Traffic Portal API client
- Contains: Main API client, request/response DTOs, HTTP transport, exception definitions
- Key files: `TrafficPortalApiClient.php` (main client, ~27K+ lines with inline logging)

**includes/SnapCapture/:**
- Purpose: Screenshot capture API integration
- Contains: API client for capturing website screenshots as base64 images
- Key files: `SnapCaptureClient.php` (API wrapper)

**includes/ShortCode/:**
- Purpose: AI-powered short code generation
- Contains: Gemini API integration for generating meaningful short codes
- Key files: `GenerateShortCodeClient.php` (API wrapper)

**templates/:**
- Purpose: Render user-facing HTML
- Contains: PHP templates included by shortcode handlers via output buffering
- Key files: `shortcode-template.php` (form), `dashboard-template.php` (list), `client-links-template.php` (advanced)

**assets/css/:**
- Purpose: Styling for user interfaces
- Contains: Bootstrap-based CSS with custom variables and layout
- Key files: `frontend.css` (shared), `dashboard.css`, `client-links.css`

**assets/js/:**
- Purpose: Client-side logic and AJAX communication
- Contains: jQuery-based form handling, AJAX calls to backend, UI interactions
- Key files: `frontend.js` (form), `dashboard.js` (list), `client-links.js` (advanced)

**tests/:**
- Purpose: Test coverage for plugin functionality
- Contains: PHP unit/integration tests, JavaScript Vitest tests, e2e tests
- Key files: `Unit/`, `Integration/`, `e2e/`, `*.test.js`

**docs/:**
- Purpose: Feature documentation and implementation notes
- Contains: UI requirements, error handling specs, development guides
- Key files: `DASHBOARD-UI-REQUIREMENTS.md`, `error-handling-changes.md`

## Key File Locations

**Entry Points:**
- `tp-link-shortener.php`: Plugin entry point; registers activation/deactivation hooks
- `includes/class-tp-link-shortener.php`: Main plugin class with singleton pattern and component initialization
- `includes/class-tp-api-handler.php`: AJAX dispatcher; all request routing goes through here

**Configuration:**
- `package.json`: JavaScript build/test dependencies and scripts
- `composer.json`: PHP package dependencies and PSR-4 autoloading
- `phpunit.xml`: PHPUnit test configuration
- `vitest.config.js`: Vitest (JavaScript test runner) configuration

**Core Logic:**
- `includes/class-tp-admin-settings.php`: Admin page with toggles for feature flags
- `includes/class-tp-shortcode.php`: Short form for creating individual links
- `includes/class-tp-dashboard-shortcode.php`: Paginated table of user's links
- `includes/class-tp-client-links-shortcode.php`: Advanced link management with charts and filtering

**Testing:**
- `tests/Unit/`: PHP unit tests for API client behavior
- `tests/Integration/`: PHP integration tests with mock API responses
- `tests/e2e/`: End-to-end Playwright tests simulating user workflows
- `tests/*.test.js`: Vitest unit tests for JavaScript utilities

**API Clients:**
- `includes/TrafficPortal/TrafficPortalApiClient.php`: Main API for link creation/retrieval
- `includes/SnapCapture/SnapCaptureClient.php`: Screenshot capture API
- `includes/ShortCode/GenerateShortCodeClient.php`: AI short code suggestions

## Naming Conventions

**Files:**
- WordPress integration classes: `class-tp-{component}.php` (e.g., `class-tp-shortcode.php`)
- API client classes: `{ServiceName}Client.php` (e.g., `TrafficPortalApiClient.php`)
- DTO classes: `{Name}Request.php`, `{Name}Response.php` (e.g., `CreateMapRequest.php`)
- Exception classes: `{ExceptionType}Exception.php` (e.g., `AuthenticationException.php`)
- CSS files: `{section}.css` (e.g., `frontend.css`, `dashboard.css`)
- JavaScript files: `{component}.js` (e.g., `frontend.js`, `dashboard.js`)

**Directories:**
- Feature directories: Plural nouns (e.g., `DTO`, `Http`, `Exception`)
- Service directories: PascalCase service names (e.g., `TrafficPortal`, `SnapCapture`, `ShortCode`)
- Asset types: Lowercase (e.g., `css`, `js`)

**Classes:**
- WordPress integration: `TP_*` prefix (e.g., `TP_Shortcode`, `TP_Admin_Settings`)
- API clients: `{ServiceName}Client` class in `{ServiceName}` namespace
- DTOs: Name matches business domain (e.g., `CreateMapRequest`, `MapItem`)

**Functions:**
- WordPress action callbacks: `{class_name}::{method_name}` in add_action calls
- AJAX handlers: `ajax_{action_name}` method names (e.g., `ajax_create_link`)

## Where to Add New Code

**New Feature:**
- Primary code: `includes/class-tp-{feature}.php` for WordPress integration; new method in `class-tp-api-handler.php` for AJAX handler
- Tests: `tests/Unit/test-{feature}.php` for PHP unit tests; `tests/{feature}.test.js` for JavaScript tests
- Assets: `assets/js/{feature}.js` and `assets/css/{feature}.css` if UI component
- Template: `templates/{feature}-template.php` if shortcode-based

**New Component/Module:**
- Implementation: `includes/class-tp-{component}.php` for singleton/feature class
- Tests: `tests/Unit/test-{component}.php`
- Assets: Reuse or extend existing `frontend.css` and create `{component}.css` if unique styling needed

**Utilities:**
- Shared helpers: `assets/js/{utility-name}.js` (e.g., `storage-service.js`, `url-validator.js`)
- PHP utilities: Inline utility functions in relevant class files or create `includes/class-tp-utilities.php`

**API Integrations:**
- New external API: Create `includes/{ServiceName}/` directory with `{ServiceName}Client.php`, `DTO/`, `Http/`, `Exception/` subdirectories
- Follow pattern from `TrafficPortal/` and `SnapCapture/` clients
- Register client initialization in `TP_API_Handler::__construct()`
- Create AJAX handlers in `TP_API_Handler` that dispatch to the new client

**Tests:**
- Unit tests: `tests/Unit/` - test individual methods/classes
- Integration tests: `tests/Integration/` - test interaction with API clients (use mock HTTP responses)
- E2E tests: `tests/e2e/` - test complete user workflows (Playwright-based)
- JavaScript tests: `tests/{component}.test.js` - use Vitest framework

## Special Directories

**includes/TrafficPortal/DTO/:**
- Purpose: Data Transfer Object definitions for API communication
- Generated: No (manually maintained)
- Committed: Yes - part of codebase

**tests/:**
- Purpose: All test files (PHP and JavaScript)
- Generated: No - tests written manually
- Committed: Yes - test code is version controlled

**logs/:**
- Purpose: Runtime log files
- Generated: Yes - created at runtime by `error_log()` and `log_to_file()`
- Committed: No - `.gitignore` excludes logs/

**node_modules/:**
- Purpose: JavaScript dependencies from npm
- Generated: Yes - created by `npm install`
- Committed: No - `.gitignore` excludes node_modules/

**vendor/:**
- Purpose: PHP dependencies from Composer
- Generated: Yes - created by `composer install`
- Committed: No - `.gitignore` excludes vendor/

**.pytest_cache/ and .playwright-mcp/:**
- Purpose: Test runner caches
- Generated: Yes - created during test execution
- Committed: No - `.gitignore` excludes these

---

*Structure analysis: 2026-02-15*
