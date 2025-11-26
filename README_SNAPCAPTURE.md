# SnapCapture API Client

A PHP client for the [SnapCapture Website Screenshot API](https://rapidapi.com/thebluesoftware-development/api/snapcapture1) that allows you to capture screenshots of any website.

## Installation

The client is already included in this WordPress plugin. Dependencies are managed via Composer:

```bash
composer install
```

## Usage

### Basic Example

```php
<?php
use SnapCapture\SnapCaptureClient;
use SnapCapture\DTO\ScreenshotRequest;

// Initialize the client with your RapidAPI key
$client = new SnapCaptureClient('your-rapidapi-key');

// Capture a desktop screenshot
$request = ScreenshotRequest::desktop('https://example.com');
$response = $client->captureScreenshot($request);

// Save the screenshot to a file
$response->saveToFile('/path/to/screenshot.jpg');
```

### Screenshot Types

#### Desktop Screenshot

```php
$request = ScreenshotRequest::desktop('https://example.com');
$response = $client->captureScreenshot($request);
```

#### Mobile Screenshot

```php
$request = ScreenshotRequest::mobile('https://example.com');
$response = $client->captureScreenshot($request);
```

#### Full Page Screenshot

```php
$request = ScreenshotRequest::fullPage('https://example.com');
$response = $client->captureScreenshot($request);
```

#### Custom Configuration

```php
$request = new ScreenshotRequest(
    url: 'https://example.com',
    format: 'png',
    quality: 90,
    viewport: ['width' => 1280, 'height' => 720],
    fullPage: false,
    mobile: false
);
$response = $client->captureScreenshot($request);
```

### Response Handling

```php
$response = $client->captureScreenshot($request);

// Get binary image data
$imageData = $response->getImageData();

// Check if cached
if ($response->isCached()) {
    echo "Response served from cache!";
}

// Get response time
echo "Response time: " . $response->getResponseTimeMs() . "ms";

// Save to file
$response->saveToFile('/path/to/screenshot.jpg');

// Get base64 encoded data
$base64 = $response->getBase64();

// Get data URI for HTML embedding
$dataUri = $response->getDataUri();
echo "<img src='{$dataUri}' />";
```

### Error Handling

```php
use SnapCapture\Exception\AuthenticationException;
use SnapCapture\Exception\ValidationException;
use SnapCapture\Exception\NetworkException;
use SnapCapture\Exception\ApiException;

try {
    $response = $client->captureScreenshot($request);
} catch (AuthenticationException $e) {
    // Invalid API key
    echo "Authentication failed: " . $e->getMessage();
} catch (ValidationException $e) {
    // Invalid request parameters
    echo "Validation error: " . $e->getMessage();
} catch (NetworkException $e) {
    // Network/cURL error
    echo "Network error: " . $e->getMessage();
} catch (ApiException $e) {
    // Other API errors
    echo "API error: " . $e->getMessage();
}
```

### Health Check

```php
$status = $client->ping();
// Returns: ['status' => 'ok', 'timestamp' => '...', 'uptime' => ...]
```

## Testing

### Unit Tests

Run unit tests that use mocking (no API key required):

```bash
./vendor/bin/phpunit --testsuite Unit
```

### Integration Tests

Integration tests make actual API calls. Set your RapidAPI key first:

```bash
export SNAPCAPTURE_API_KEY=your-rapidapi-key-here
./vendor/bin/phpunit --testsuite Integration
```

The integration tests will save screenshots to `tests/screenshots/` for manual verification.

### All Tests

```bash
./vendor/bin/phpunit
```

## Configuration

### API Credentials

Get your API key from [RapidAPI](https://rapidapi.com/thebluesoftware-development/api/snapcapture1).

You can store your API key in a `.env.snapcapture` file (copy from `.env.snapcapture.example`):

```bash
cp .env.snapcapture.example .env.snapcapture
# Edit .env.snapcapture and add your key
```

### Timeout

Set a custom timeout (default is 30 seconds):

```php
$client = new SnapCaptureClient($apiKey, timeout: 60);
```

## API Features

- **Fast**: Cached responses in 1-5ms, fresh screenshots in 300-3000ms
- **Formats**: JPEG and PNG support
- **Customizable**: Configure viewport, quality, full-page capture, mobile mode
- **Reliable**: Comprehensive error handling and exception types
- **Easy to Use**: Simple, fluent API with factory methods

## Common Viewport Sizes

| Device | Width | Height | Mobile Mode |
|--------|-------|--------|-------------|
| Desktop HD | 1920 | 1080 | No |
| iPad | 768 | 1024 | No |
| iPhone SE | 375 | 667 | Yes |
| iPhone 12/13/14 | 390 | 844 | Yes |

## WordPress Integration Example

```php
// In your WordPress plugin
function capture_page_screenshot($url) {
    $api_key = get_option('snapcapture_api_key');
    $client = new SnapCapture\SnapCaptureClient($api_key);

    $request = SnapCapture\DTO\ScreenshotRequest::desktop($url);
    $response = $client->captureScreenshot($request);

    // Save to WordPress uploads directory
    $upload_dir = wp_upload_dir();
    $filename = sanitize_file_name(parse_url($url, PHP_URL_HOST) . '.jpg');
    $filepath = $upload_dir['path'] . '/' . $filename;

    if ($response->saveToFile($filepath)) {
        return $upload_dir['url'] . '/' . $filename;
    }

    return false;
}
```

## License

This client is part of the TP Link Shortener Plugin.

## API Documentation

For complete API documentation, visit:
https://rapidapi.com/thebluesoftware-development/api/snapcapture1
