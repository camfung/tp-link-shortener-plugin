# Short Code Generation API - JavaScript Client

A JavaScript/TypeScript client for the Short Code Generation API with support for three endpoint types: Fast (rule-based), Smart (NLP), and AI (Gemini).

## Installation

Include the client in your project:

```javascript
import { ShortCodeClient, ShortCodeResponse, ShortCodeError } from './shortcode-client.js';
```

Or for browser usage:
```html
<script type="module" src="./shortcode-client.js"></script>
```

## Quick Start

```javascript
// Initialize client
const client = new ShortCodeClient();

// Generate short code using Fast endpoint
client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
const result = await client.generateShortCode('https://example.com', 'trfc.link');

console.log(result.shortCode);  // "example"
console.log(result.method);     // "rule-based"
console.log(result.wasModified); // false
```

## API Reference

### ShortCodeClient

#### Constructor

```javascript
new ShortCodeClient(baseUrl)
```

- `baseUrl` (optional): Base URL for the API. Defaults to production endpoint.

#### Methods

**`setEndpointType(endpointPath)`**
Set which endpoint to use:
- `ShortCodeClient.ENDPOINT_FAST` - Rule-based (~500ms)
- `ShortCodeClient.ENDPOINT_SMART` - AWS Comprehend NLP (~800ms)
- `ShortCodeClient.ENDPOINT_AI` - Gemini AI (~3-5s)
- `ShortCodeClient.ENDPOINT_LEGACY` - Legacy/AI endpoint

**`generateShortCode(url, domain)`**
Generate a short code from a URL.
- `url` (required): URL to analyze
- `domain` (optional): Domain to check availability
- Returns: `Promise<ShortCodeResponse>`
- Throws: `ShortCodeError`

**`setTimeout(timeout)`**
Set request timeout in milliseconds. Default: 30000ms (30 seconds)

### ShortCodeResponse

Response object with endpoint-specific fields:

#### Common Fields (all endpoints)
- `shortCode`: Generated short code
- `method`: Generation method ("rule-based", "nlp-comprehend", or "gemini-ai")
- `wasModified`: Whether suffix was added for collision handling
- `message`: Success message
- `success`: Success flag

#### Fast Endpoint Fields
- `candidates`: Array of alternative short codes

#### Smart Endpoint Fields
- `candidates`: Array of alternative short codes
- `keyPhrases`: Array of extracted key phrases
- `entities`: Array of extracted entities

#### AI Endpoint Fields
- `originalCode`: Code before collision handling
- `url`: Original URL that was analyzed

#### Helper Methods
- `isFast()`: Check if response is from Fast endpoint
- `isSmart()`: Check if response is from Smart endpoint
- `isAI()`: Check if response is from AI endpoint

### ShortCodeError

Custom error class for API errors.

#### Properties
- `message`: Error message
- `statusCode`: HTTP status code (0 for network errors)
- `data`: Response data (if available)

#### Helper Methods
- `isValidationError()`: Check if error is 400 (validation)
- `isRateLimitError()`: Check if error is 429 (rate limit)
- `isServerError()`: Check if error is 500+ (server error)
- `isNetworkError()`: Check if error is network/timeout

## Usage Examples

### Basic Usage

```javascript
const client = new ShortCodeClient();

try {
  client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
  const result = await client.generateShortCode('https://example.com');
  console.log(`Generated: ${result.shortCode}`);
} catch (error) {
  if (error.isRateLimitError()) {
    console.error('Rate limited, retry later');
  } else {
    console.error('Error:', error.message);
  }
}
```

### Progressive Enhancement

Try fast endpoint first, fallback to smart:

```javascript
async function generateWithFallback(url, domain) {
  const client = new ShortCodeClient();

  try {
    // Try fast first
    client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
    return await client.generateShortCode(url, domain);
  } catch (error) {
    // Fallback to smart
    client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);
    return await client.generateShortCode(url, domain);
  }
}
```

### Compare Multiple Endpoints

```javascript
async function compareEndpoints(url) {
  const client = new ShortCodeClient();
  const results = {};

  // Fast
  client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
  results.fast = await client.generateShortCode(url);

  // Smart
  client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);
  results.smart = await client.generateShortCode(url);

  console.log('Fast:', results.fast.shortCode, results.fast.candidates);
  console.log('Smart:', results.smart.shortCode, results.smart.keyPhrases);

  return results;
}
```

### Error Handling

```javascript
try {
  const result = await client.generateShortCode(url);
} catch (error) {
  if (error.isValidationError()) {
    // 400 - Invalid URL format
    console.error('Invalid URL');
  } else if (error.isRateLimitError()) {
    // 429 - Rate limit exceeded
    console.error('Rate limited');
  } else if (error.isServerError()) {
    // 500+ - Server error
    console.error('Server error');
  } else if (error.isNetworkError()) {
    // Network or timeout error
    console.error('Network error or timeout');
  }
}
```

## Testing

### Unit Tests (Mocked)

```bash
npm run test:unit
```

Runs unit tests with mocked fetch calls. All 21 tests should pass.

### Integration Tests (Live API)

```bash
npm run test:integration
```

Runs integration tests against the live API. Requires network access.

**Note:** AI/Gemini endpoints may fail due to quota limits.

### All Tests

```bash
npm test
```

## Performance

Typical response times:
- **Fast endpoint:** ~500ms (rule-based)
- **Smart endpoint:** ~800ms (AWS Comprehend NLP)
- **AI endpoint:** ~3-5s (Gemini AI)

## Browser Compatibility

Requires:
- ES6 modules support
- Fetch API
- AbortController (for timeouts)
- Promises/async-await

Supported browsers:
- Chrome 63+
- Firefox 57+
- Safari 11.1+
- Edge 79+

## Node.js Usage

Works with Node.js 18+ (native fetch support) or Node.js 16+ with a fetch polyfill:

```bash
npm install node-fetch
```

```javascript
import fetch from 'node-fetch';
global.fetch = fetch;

import { ShortCodeClient } from './shortcode-client.js';
```

## License

MIT
