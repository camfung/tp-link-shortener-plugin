# Screenshot Capture API

The TP Link Shortener plugin now includes a screenshot capture endpoint powered by SnapCapture API.

## Configuration

### API Key Setup

The screenshot endpoint requires a SnapCapture API key. Configure it using one of these methods:

**Option 1: .env.snapcapture file (Recommended)**
```bash
# Create file at project root: .env.snapcapture
SNAPCAPTURE_API_KEY=your-rapidapi-key-here
```

**Option 2: Environment Variable**
```bash
export SNAPCAPTURE_API_KEY=your-rapidapi-key-here
```

**Option 3: WordPress Constant**
```php
// In wp-config.php
define('SNAPCAPTURE_API_KEY', 'your-rapidapi-key-here');
```

## API Endpoint

### Capture Screenshot

**Endpoint:** `/wp-admin/admin-ajax.php`

**Method:** `POST`

**Parameters:**
- `action` (required): `tp_capture_screenshot`
- `nonce` (required): WordPress nonce for security (`tp_link_shortener_nonce`)
- `url` (required): Full URL to capture (must include http:// or https://)

**Example Request:**
```bash
curl -X POST "http://your-site.com/wp-admin/admin-ajax.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=tp_capture_screenshot" \
  -d "nonce=YOUR_NONCE_HERE" \
  -d "url=https://example.com"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "screenshot_base64": "base64-encoded-image-data",
    "data_uri": "data:image/jpeg;base64,...",
    "content_type": "image/jpeg",
    "cached": false,
    "response_time_ms": 1234,
    "url": "https://example.com"
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "data": {
    "message": "Error message here",
    "debug_error": "Detailed error for debugging"
  }
}
```

## Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `screenshot_base64` | string | Base64-encoded image data (without data URI prefix) |
| `data_uri` | string | Complete data URI for embedding in HTML (`data:image/jpeg;base64,...`) |
| `content_type` | string | MIME type of the image (e.g., `image/jpeg`) |
| `cached` | boolean | Whether the screenshot was served from cache |
| `response_time_ms` | integer | API response time in milliseconds (null if cached) |
| `url` | string | The original URL that was captured |

## Error Handling

The endpoint handles various error types:

### Authentication Error
```json
{
  "success": false,
  "data": {
    "message": "Screenshot authentication failed. Please check configuration."
  }
}
```

**Cause:** Invalid or missing SnapCapture API key

### Validation Error
```json
{
  "success": false,
  "data": {
    "message": "Please enter a valid URL"
  }
}
```

**Cause:** Invalid URL format

### Rate Limit Error
```json
{
  "success": false,
  "data": {
    "message": "Screenshot rate limit exceeded. Please try again later."
  }
}
```

**Cause:** Too many requests to SnapCapture API

### Network Error
```json
{
  "success": false,
  "data": {
    "message": "Network error while capturing screenshot. Please try again."
  }
}
```

**Cause:** Network connectivity issues

### Service Not Configured
```json
{
  "success": false,
  "data": {
    "message": "Screenshot service not configured. Please contact administrator."
  }
}
```

**Cause:** SnapCaptureClient not initialized (no API key configured)

## Testing with Postman

A comprehensive Postman collection is available: `docs/tp-link-shortener.postman_collection.json`

### Import Collection

1. Open Postman
2. Click **Import**
3. Select `tp-link-shortener.postman_collection.json`
4. Update the `base_url` variable to your WordPress site URL

### Collection Variables

- `base_url`: Your WordPress site URL (e.g., `http://localhost:8000`)
- `nonce`: Automatically extracted by running the "Get Nonce" request first

### Test Requests

The collection includes 7 pre-configured requests:

1. **Get Nonce** - Extracts WordPress nonce from plugin page
2. **Create Short Link** - Creates a new short link
3. **Capture Screenshot** - Captures screenshot of example.com
4. **Validate Key** - Validates if a shortcode is available
5. **Validate URL** - Checks if a URL is accessible
6. **Screenshot - Google** - Captures screenshot of Google homepage
7. **Screenshot - Invalid URL** - Tests error handling

### Running Tests

1. First, run **"Get Nonce"** to extract and save the nonce
2. Then run any other request (nonce is automatically included)

## Usage in JavaScript

### Example: Capture and Display Screenshot

```javascript
// Get nonce from WordPress localized script
const nonce = wpApiSettings.nonce;

// Capture screenshot
async function captureScreenshot(url) {
  const formData = new URLSearchParams();
  formData.append('action', 'tp_capture_screenshot');
  formData.append('nonce', nonce);
  formData.append('url', url);

  try {
    const response = await fetch('/wp-admin/admin-ajax.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: formData
    });

    const data = await response.json();

    if (data.success) {
      // Display screenshot
      const img = document.createElement('img');
      img.src = data.data.data_uri;
      document.body.appendChild(img);

      console.log('Screenshot captured:', data.data);
    } else {
      console.error('Screenshot failed:', data.data.message);
    }
  } catch (error) {
    console.error('Request failed:', error);
  }
}

// Usage
captureScreenshot('https://example.com');
```

### Example: Download Screenshot

```javascript
async function downloadScreenshot(url, filename = 'screenshot.jpg') {
  const formData = new URLSearchParams();
  formData.append('action', 'tp_capture_screenshot');
  formData.append('nonce', wpApiSettings.nonce);
  formData.append('url', url);

  const response = await fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: formData
  });

  const data = await response.json();

  if (data.success) {
    // Convert base64 to blob
    const base64Data = data.data.screenshot_base64;
    const byteCharacters = atob(base64Data);
    const byteNumbers = new Array(byteCharacters.length);
    for (let i = 0; i < byteCharacters.length; i++) {
      byteNumbers[i] = byteCharacters.charCodeAt(i);
    }
    const byteArray = new Uint8Array(byteNumbers);
    const blob = new Blob([byteArray], { type: data.data.content_type });

    // Download
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
  }
}
```

## Security

- **Nonce Validation:** All requests require a valid WordPress nonce
- **URL Sanitization:** URLs are sanitized using WordPress `sanitize_url()`
- **URL Validation:** URLs must pass `filter_var($url, FILTER_VALIDATE_URL)`
- **Rate Limiting:** Subject to SnapCapture API rate limits
- **HTTPS Only:** Screenshots are captured over secure HTTPS connection

## Rate Limits

Rate limits depend on your SnapCapture API plan:
- **Free Tier:** Limited requests per day
- **Pro Tier:** Higher request limits
- **Enterprise:** Custom limits

If you exceed the rate limit, the API will return a rate limit error. Implement exponential backoff or caching to avoid hitting limits.

## Caching

The SnapCapture API caches screenshots automatically:
- **Cache Hit:** `cached: true`, `response_time_ms: 0` or very low
- **Cache Miss:** `cached: false`, normal response time

Benefits of caching:
- Faster response times
- Reduced API usage
- Lower costs

## Performance

Typical response times:
- **Cached:** < 500ms
- **Uncached:** 1-3 seconds (depends on target website)
- **Complex pages:** 3-5 seconds

Recommendations:
- Capture screenshots asynchronously
- Show loading indicator to user
- Cache responses on your server if needed
- Use smaller viewport sizes for faster captures

## Troubleshooting

### "Screenshot service not configured"
- Check that `.env.snapcapture` exists with valid API key
- Verify API key is correct
- Check error logs: `error_log('TP Link Shortener: ...')`

### "Authentication failed"
- Verify SnapCapture API key is valid
- Check that RapidAPI subscription is active
- Test API key with integration tests

### "Rate limit exceeded"
- Wait before making more requests
- Implement caching on your server
- Upgrade SnapCapture API plan
- Use cached screenshots when available

### Screenshots not capturing
- Verify target URL is accessible
- Check if target site blocks automated access
- Try a different URL (e.g., example.com)
- Check WordPress debug.log for errors

## Advanced Features

### Custom Screenshot Options

The endpoint currently uses desktop preset (1920x1080). To customize:

1. Modify `capture_screenshot()` method in `class-tp-api-handler.php`
2. Create different request types:
   - `ScreenshotRequest::desktop($url)` - 1920x1080
   - `ScreenshotRequest::mobile($url)` - 375x667
   - `ScreenshotRequest::fullPage($url)` - Full scrollable page
   - `new ScreenshotRequest($url, 'png', 100, ['width' => 1280, 'height' => 720])`

### Binary Response

To receive binary image instead of base64 JSON:

1. Modify `captureScreenshot()` call to use `false` for second parameter
2. Return binary data directly with appropriate headers

## Support

For issues or questions:
- Plugin: Check WordPress debug.log
- SnapCapture API: [RapidAPI Dashboard](https://rapidapi.com/)
- Integration Tests: `./vendor/bin/phpunit --testsuite Integration`

## See Also

- [SnapCapture API Documentation](includes/SnapCapture/README.md)
- [Postman Collection](tp-link-shortener.postman_collection.json)
- [Integration Tests](../tests/Integration/SnapCaptureIntegrationTest.php)
- [Unit Tests](../tests/Unit/SnapCaptureClientTest.php)
