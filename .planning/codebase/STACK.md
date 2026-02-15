# Technology Stack

**Analysis Date:** 2026-02-15

## Languages

**Primary:**
- PHP 8.1+ - Backend plugin code, API handlers, shortcode implementation
- JavaScript (ES6+) - Frontend interactions, dashboard UI, QR code generation

**Secondary:**
- HTML5 - Template markup in shortcodes
- CSS3 - Styling for frontend interfaces

## Runtime

**Environment:**
- WordPress 5.8+ (core dependency)
- PHP 8.0+ (plugin requirement)

**Package Manager:**
- Composer - PHP dependencies
- npm - JavaScript dependencies
- Lockfile: Both `composer.lock` and `package-lock.json` present

## Frameworks

**Core:**
- WordPress - Plugin framework
- Vitest 3.2.4 - JavaScript testing framework

**Testing:**
- PHPUnit 9.5 - PHP unit testing (dev dependency in `composer.json`)
- jsdom 27.0.1 - DOM simulation for JavaScript tests
- Vitest 3.2.4 - Test runner with coverage support

**Build/Dev:**
- No build tool detected (vanilla JavaScript assets)

## Key Dependencies

**Critical:**
- PHP 8.1+ - Type hints and modern PHP features used throughout
- Composer PSR-4 autoload - Namespace-based class loading (`ShortCode\`, `SnapCapture\`, `TrafficPortal\`)

**Infrastructure:**
- `testing-library/dom` 10.4.1 - DOM testing utilities
- `testing-library/user-event` 14.6.1 - User interaction simulation

## Configuration

**Environment:**
- WordPress options (via `wp-config.php` constants):
  - `TP_API_ENDPOINT` - Traffic Portal API base URL
  - `TP_API_KEY` - Traffic Portal authentication token
  - `SNAPCAPTURE_API_KEY` - SnapCapture screenshot service key
  - `TP_LINK_SHORTENER_USER_ID` - Default user ID
  - `TP_LINK_SHORTENER_DOMAIN` - Short domain (e.g., "dev.trfc.link")

**Build:**
- `vitest.config.js` - JavaScript testing configuration with jsdom environment
- `composer.json` - PHP package dependencies and PSR-4 autoload
- `package.json` - npm scripts and dev dependencies

## Platform Requirements

**Development:**
- WordPress installation with plugin enabled
- PHP 8.1 with curl extension
- MySQL/MariaDB database (via WordPress)
- Modern web browser for frontend testing

**Production:**
- WordPress hosting with PHP 8.0+ support
- SSL certificate (HTTPS required for SnapCapture API calls)
- cURL enabled for external API requests
- RapidAPI account for SnapCapture service
- Traffic Portal API credentials

---

*Stack analysis: 2026-02-15*
