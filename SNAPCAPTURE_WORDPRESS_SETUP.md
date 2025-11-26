# SnapCapture WordPress Integration Setup

## Overview

The SnapCapture screenshot feature has been integrated into the TP Link Shortener plugin. When users create a short link, they will see both a QR code and a live screenshot of the destination URL in a side-by-side layout.

## Configuration

### 1. Get Your RapidAPI Key

1. Visit: https://rapidapi.com/thebluesoftware-development/api/snapcapture1
2. Subscribe to the API (free tier available)
3. Copy your API key from the "X-RapidAPI-Key" header

### 2. Add API Key to wp-config.php

Add the following line to your `wp-config.php` file (before the "That's all, stop editing!" line):

```php
define('SNAPCAPTURE_API_KEY', 'your-rapidapi-key-here');
```

**Example:**

```php
// Traffic Portal API Configuration
define('API_KEY', 'your-traffic-portal-key');
define('TP_API_ENDPOINT', 'https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev');

// SnapCapture API Configuration
define('SNAPCAPTURE_API_KEY', 'abc123xyz456...');

/* That's all, stop editing! Happy publishing. */
```

### 3. Verify Installation

1. Create a short link using the plugin
2. You should see:
   - Left side: QR code for the short link
   - Right side: Screenshot preview of the destination URL

## Features

### User Experience

When a user creates a short link:

1. **Immediate QR Code**: QR code is generated instantly
2. **Screenshot Capture**: Screenshot is captured in the background
3. **Loading State**: Shows a spinner while screenshot is being captured
4. **Error Handling**: Gracefully shows "Screenshot not available" if capture fails
5. **Performance Info**: Shows cache status and response time

### Technical Details

- **Screenshot Quality**: 75% JPEG (optimized for web)
- **Viewport**: 1920x1080 (desktop view)
- **Caching**: API caches screenshots for 24 hours
- **Response Time**: 1-5ms for cached, 300-3000ms for new
- **Fallback**: If API key is not configured, only QR code is shown

## UI Layout

The QR code and screenshot are displayed side-by-side on desktop, and stack vertically on mobile devices:

```
┌─────────────────────────────────────┐
│  Your Short URL                     │
│  https://dev.trfc.link/abc123       │
│  [ Copy ]                           │
└─────────────────────────────────────┘

┌──────────────────┬──────────────────┐
│   QR Code        │  Page Preview    │
│                  │                  │
│   [QR Image]     │  [Screenshot]    │
│                  │                  │
│  [Download QR]   │  Cached • 123ms  │
└──────────────────┴──────────────────┘
```

## Troubleshooting

### Screenshot Not Showing

1. **Check API Key**: Verify `SNAPCAPTURE_API_KEY` is defined in wp-config.php
2. **Check Browser Console**: Look for JavaScript errors
3. **Check WordPress Error Log**: Look for SnapCapture error messages
4. **Test API Key**: Run the integration test:
   ```bash
   SNAPCAPTURE_API_KEY=your-key ./run-integration-test.sh
   ```

### Common Errors

**"Screenshot service not configured"**
- API key is not set in wp-config.php
- Add the `SNAPCAPTURE_API_KEY` constant

**"Screenshot authentication failed"**
- Invalid API key
- Verify your RapidAPI subscription is active
- Check the API key is correct

**"Network error while capturing screenshot"**
- Check server's internet connectivity
- Verify no firewall blocks to snapcapture1.p.rapidapi.com

**"Failed to capture screenshot"**
- Destination URL may be unreachable
- URL may block screenshot bots
- Check error logs for details

## Performance Considerations

### Caching

- Screenshots are cached by the API for 24 hours
- First request: 300-3000ms (depending on website)
- Subsequent requests: 1-5ms (served from cache)

### Quality vs Size

Current settings (75% JPEG) provide a good balance:
- **Good quality**: Clear, readable screenshot
- **Small file size**: Fast loading
- **Optimized for web**: Perfect for preview purposes

To adjust quality, edit `includes/class-tp-api-handler.php`:

```php
// Line 550 - Change quality (1-100)
$request = ScreenshotRequest::desktop($url, 'jpeg', 75);
```

## Security

- API key is stored in wp-config.php (not in database)
- All AJAX requests are nonce-verified
- URLs are sanitized before screenshot capture
- No user input goes directly to the API

## API Costs

Visit RapidAPI for current pricing: https://rapidapi.com/thebluesoftware-development/api/snapcapture1

- **Free Tier**: Available with limited requests
- **Pay-as-you-go**: Scales with usage
- **Cached responses**: Don't count against quota

## Disabling Screenshots

If you want to disable screenshots but keep QR codes:

1. Remove or comment out the `SNAPCAPTURE_API_KEY` in wp-config.php
2. The plugin will gracefully fall back to showing only QR codes

## Support

For issues with:
- **Screenshot API**: Check SnapCapture documentation on RapidAPI
- **WordPress Integration**: Check plugin error logs
- **UI Issues**: Check browser console for JavaScript errors

## Development

### Testing Locally

```bash
# Add to wp-config.php
define('SNAPCAPTURE_API_KEY', 'your-test-key');

# Test the API client directly
cd wp-content/plugins/tp-link-shortener-plugin
SNAPCAPTURE_API_KEY=your-key ./run-integration-test.sh
```

### Viewing API Responses

Check WordPress debug log for SnapCapture API responses:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Then check `wp-content/debug.log` for SnapCapture messages.
