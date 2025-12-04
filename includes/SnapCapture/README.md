# SnapCapture API Client

PHP client for the SnapCapture API with dependency injection support for HTTP clients.

## Features

- Dependency injection pattern for HTTP clients (real or mock)
- PSR-4 autoloading compatible
- Comprehensive unit tests with mock HTTP client
- Integration tests with real API calls
- Screenshot saving to filesystem

## Installation

The SnapCapture client is now located in the `includes/SnapCapture` directory and uses PSR-4 autoloading.

## Configuration

### API Key Setup

Create a file named `.env.snapcapture` in the project root directory:

```bash
SNAPCAPTURE_API_KEY=your-rapidapi-key-here
```

**Location:** `/path/to/project/.env.snapcapture`

The integration tests will automatically load this file to get your API key.

### Alternative: Environment Variable

You can also set the API key as an environment variable:

```bash
export SNAPCAPTURE_API_KEY=your-rapidapi-key-here
```

## Usage

### Basic Usage

```php
use SnapCapture\SnapCaptureClient;
use SnapCapture\DTO\ScreenshotRequest;

// Create client with default CurlHttpClient
$client = new SnapCaptureClient('your-api-key');

// Capture a desktop screenshot
$request = ScreenshotRequest::desktop('https://example.com');
$response = $client->captureScreenshot($request);

// Save to file
$response->saveToFile('/path/to/screenshot.jpg');
```

### With Custom HTTP Client

```php
use SnapCapture\SnapCaptureClient;
use SnapCapture\Http\CurlHttpClient;

// Create custom HTTP client with custom timeout
$httpClient = new CurlHttpClient(60, 15);

// Inject custom client
$client = new SnapCaptureClient('your-api-key', $httpClient);
```

### With Mock HTTP Client (for testing)

```php
use SnapCapture\SnapCaptureClient;
use SnapCapture\Http\MockHttpClient;
use SnapCapture\Http\HttpResponse;

// Create mock client
$mockClient = new MockHttpClient();

// Add mocked response
$mockClient->addResponse(
    new HttpResponse(200, [
        'content-type' => 'image/jpeg',
    ], 'fake-image-data')
);

// Create client with mock
$client = new SnapCaptureClient('test-api-key', $mockClient);

// This will use the mocked response
$request = ScreenshotRequest::desktop('https://example.com');
$response = $client->captureScreenshot($request);
```

## Running Tests

### Unit Tests (with mocks)

Unit tests use the MockHttpClient and don't require an API key:

```bash
./vendor/bin/phpunit --testsuite Unit
```

### Integration Tests (with real API)

Integration tests make real API calls and require a valid API key:

```bash
# Make sure .env.snapcapture exists with your API key
./vendor/bin/phpunit --testsuite Integration
```

Screenshots from integration tests are saved to:
`tests/screenshots/integration/`

### Run All Tests

```bash
./vendor/bin/phpunit
```

## Architecture

### Dependency Injection

The SnapCaptureClient now uses dependency injection for the HTTP client, making it:
- Testable with mock clients
- Flexible to swap implementations
- Easy to extend with custom HTTP clients

### Key Classes

- **SnapCaptureClient**: Main API client
- **HttpClientInterface**: Interface for HTTP clients
- **CurlHttpClient**: Default cURL-based HTTP client
- **MockHttpClient**: Mock HTTP client for testing
- **HttpResponse**: Represents an HTTP response
- **ScreenshotRequest**: Request DTO
- **ScreenshotResponse**: Response DTO with image data

## File Structure

```
includes/SnapCapture/
├── SnapCaptureClient.php      # Main API client
├── Http/
│   ├── HttpClientInterface.php # HTTP client interface
│   ├── CurlHttpClient.php      # Default cURL implementation
│   ├── MockHttpClient.php      # Mock for testing
│   └── HttpResponse.php        # Response object
├── DTO/
│   ├── ScreenshotRequest.php   # Request data transfer object
│   └── ScreenshotResponse.php  # Response data transfer object
└── Exception/
    ├── SnapCaptureException.php
    ├── ApiException.php
    ├── AuthenticationException.php
    ├── NetworkException.php
    └── ValidationException.php
```

## Testing Coverage

### Unit Tests (15 tests)
- ✅ Screenshot capture (binary/JSON/PNG/mobile/fullpage)
- ✅ Authentication errors
- ✅ Validation errors
- ✅ Server errors
- ✅ API ping
- ✅ Request headers
- ✅ Invalid JSON handling
- ✅ Getters and utilities

### Integration Tests (9 tests)
- ✅ Desktop screenshots
- ✅ Mobile screenshots
- ✅ PNG screenshots
- ✅ Full page screenshots
- ✅ JSON response format
- ✅ Authentication validation
- ✅ Invalid URL handling
- ✅ Multiple screenshots
- ⏭️ Ping endpoint (skipped - API doesn't support)

All screenshots from integration tests are saved to the filesystem for manual verification.
