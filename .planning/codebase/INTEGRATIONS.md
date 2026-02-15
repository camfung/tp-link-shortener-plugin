# External Integrations

**Analysis Date:** 2026-02-15

## APIs & External Services

**Traffic Portal API:**
- Service: Custom URL shortening and link management API
- What it's used for: Creating, retrieving, updating, and searching short links; fingerprint-based link lookups
- SDK/Client: `TrafficPortal\TrafficPortalApiClient` in `includes/TrafficPortal/TrafficPortalApiClient.php`
- Auth: `TP_API_KEY` WordPress constant or environment variable
- Endpoint: Configured via `TP_API_ENDPOINT` constant (typically `https://api.trafficportal.dev`)
- Operations: `createMaskedRecord()`, `getMaskedRecord()`, `updateMaskedRecord()`, `searchByFingerprint()`, `getUserMapItems()`
- Rate Limits: Implements `RateLimitException` for HTTP 429 responses

**SnapCapture Screenshot API:**
- Service: RapidAPI-hosted screenshot capture service
- What it's used for: Capturing website screenshots for preview thumbnails
- SDK/Client: `SnapCapture\SnapCaptureClient` in `includes/SnapCapture/SnapCaptureClient.php`
- Auth: `SNAPCAPTURE_API_KEY` constant or environment variable or `.env.snapcapture` file
- Endpoint: `https://snapcapture1.p.rapidapi.com`
- Operations: `captureScreenshot()` returns base64-encoded image or binary data
- Response: Includes caching info, response time, and content type headers

**Gemini AI Short Code Generation:**
- Service: AWS Lambda-based AI short code generation (Google Gemini integration)
- What it's used for: Generating semantically meaningful short codes for URLs
- SDK/Client: `ShortCode\GenerateShortCodeClient` in `includes/ShortCode/GenerateShortCodeClient.php`
- Endpoint: `https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev`
- Operations: `generateShortCode()` with tiering (AI, Smart, Fast)
- Tiers: Configurable via `GenerationTier` enum (Fast, Smart, AI)
- Fallback: Random 8-character alphanumeric code if Gemini unavailable

## Data Storage

**Databases:**
- WordPress database (MySQL/MariaDB)
  - Connection: Via WordPress global `$wpdb`
  - Storage: Link history in custom table `wp_tp_link_history` (dynamically created)
  - Schema: `mid` (mapped item ID), `uid` (user ID), `action`, `changes` (JSON), `created_at` timestamp

**File Storage:**
- Local filesystem only (no S3/cloud storage)
- Logs directory: `logs/` in plugin root
- Screenshot caching: In-memory (via SnapCapture response headers)

**Caching:**
- SnapCapture service provides caching headers (`x-cache-hit` header)
- WordPress object cache integration: Not detected

## Authentication & Identity

**Auth Provider:**
- Custom API key authentication
- Traffic Portal: API key-based (Bearer token pattern)
- SnapCapture: RapidAPI key-based (X-RapidAPI-Key header)

**User Identification:**
- WordPress user IDs for logged-in users
- Anonymous fingerprinting: Browser fingerprint via FingerprintJS v4 for unlogged users
- UID -1: Represents anonymous users

## Monitoring & Observability

**Error Tracking:**
- None detected (no Sentry, Bugsnag integration)

**Logs:**
- WordPress error logs: `WP_CONTENT_DIR . '/plugins/tp-update-debug.log'`
- SnapCapture debug log: `logs/snapcapture.log` in plugin directory
- WordPress standard log: `WP_CONTENT_DIR . '/debug.log'`
- REST endpoint for log access: `GET /wp-json/tp-link-shortener/v1/logs` (admin only)

## CI/CD & Deployment

**Hosting:**
- WordPress plugin installation (self-hosted or managed WordPress hosting)
- SnapCapture via RapidAPI (SaaS)
- Gemini generation via AWS Lambda (SaaS)

**CI Pipeline:**
- E2E tests: Playwright-based (`tests/e2e/`)
- PHP unit tests: PHPUnit with `phpunit.result.cache`
- JavaScript tests: Vitest with jsdom

## Environment Configuration

**Required env vars:**
- `TP_API_KEY` - Traffic Portal authentication
- `SNAPCAPTURE_API_KEY` - RapidAPI key for SnapCapture (optional but recommended for screenshots)
- `TP_API_ENDPOINT` - Traffic Portal API base URL
- `TP_LINK_SHORTENER_DOMAIN` - Short domain name (default: "dev.trfc.link")
- `TP_LINK_SHORTENER_USER_ID` - Default UID when creating links

**Secrets location:**
- Primary: WordPress constants in `wp-config.php` (recommended)
- Secondary: Environment variables (checked via `getenv()`)
- Fallback: `.env.snapcapture` file (development only, not committed)

**Config Priority (SnapCapture):**
1. `SNAPCAPTURE_API_KEY` WordPress constant
2. `SNAPCAPTURE_API_KEY` environment variable
3. `.env.snapcapture` file (fallback)

## Webhooks & Callbacks

**Incoming:**
- None detected

**Outgoing:**
- None detected
- All operations are synchronous API calls

## Cross-Service Data Flow

**Link Creation Flow:**
1. User submits URL via form or API
2. Browser captures device fingerprint (FingerprintJS)
3. Plugin generates short code (Gemini or random fallback)
4. Creates link via Traffic Portal API
5. Optionally captures screenshot via SnapCapture
6. Returns short URL, screenshot, and metadata to client

**Link Search Flow:**
1. Browser fingerprint captured
2. Plugin queries Traffic Portal via `searchByFingerprint()`
3. Returns matching link records for anonymous user

**Link Management Flow:**
1. Logged-in user authenticates via WordPress
2. Plugin fetches user's links via Traffic Portal API
3. Supports pagination, sorting, filtering, and search
4. Link history tracked in local WordPress table

---

*Integration audit: 2026-02-15*
