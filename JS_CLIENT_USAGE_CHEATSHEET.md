# JavaScript Client Usage Cheat Sheet

Quick reference for using the ShortCodeClient.

## Installation & Import

```javascript
// ES6 Module
import { ShortCodeClient, ShortCodeError } from './shortcode-client.js';

// Browser (ES6 module)
<script type="module" src="./shortcode-client.js"></script>
```

---

## Quick Start (5 seconds)

```javascript
const client = new ShortCodeClient();
const result = await client.generateShortCode('https://example.com');
console.log(result.shortCode); // "example"
```

---

## Choose Your Endpoint

```javascript
const client = new ShortCodeClient();

// Fast (~500ms) - Rule-based
client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);

// Smart (~800ms) - AWS NLP
client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);

// AI (~3-5s) - Gemini AI
client.setEndpointType(ShortCodeClient.ENDPOINT_AI);
```

---

## Common Use Cases

### 1. Generate with Domain Check

```javascript
const client = new ShortCodeClient();
client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);

const result = await client.generateShortCode(
  'https://example.com',
  'trfc.link'  // Check availability on this domain
);

console.log(result.shortCode);      // "example"
console.log(result.wasModified);    // false (no collision)
```

### 2. Progressive Enhancement (Fast → Smart Fallback)

```javascript
const client = new ShortCodeClient();

async function generateCode(url) {
  try {
    // Try fast first
    client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
    return await client.generateShortCode(url);
  } catch (error) {
    // Fallback to smart
    client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);
    return await client.generateShortCode(url);
  }
}
```

### 3. Show Results While Waiting for Better One

```javascript
async function showProgressiveResults(url) {
  const client = new ShortCodeClient();

  // Show fast result immediately
  client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
  const fast = await client.generateShortCode(url);
  showToUser(fast.shortCode); // Display immediately

  // Get better suggestion in background
  client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);
  const smart = await client.generateShortCode(url);

  if (smart.shortCode !== fast.shortCode) {
    offerAlternative(smart.shortCode); // "Try this instead?"
  }
}
```

### 4. Compare All Endpoints

```javascript
async function getAllSuggestions(url) {
  const client = new ShortCodeClient();
  const results = {};

  // Get all suggestions
  client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
  results.fast = await client.generateShortCode(url);

  client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);
  results.smart = await client.generateShortCode(url);

  return [
    results.fast.shortCode,
    ...results.fast.candidates || [],
    results.smart.shortCode,
  ].filter((v, i, a) => a.indexOf(v) === i); // Unique codes
}
```

### 5. Handle Collisions

```javascript
const result = await client.generateShortCode(url, domain);

if (result.wasModified) {
  console.log(`"${result.originalCode}" was taken`);
  console.log(`Using "${result.shortCode}" instead`);
}
```

---

## Error Handling

### Basic Error Handling

```javascript
try {
  const result = await client.generateShortCode(url);
  console.log(result.shortCode);
} catch (error) {
  console.error('Failed:', error.message);
}
```

### Detailed Error Handling

```javascript
try {
  const result = await client.generateShortCode(url);
} catch (error) {
  if (error.isValidationError()) {
    // 400 - Invalid URL format
    alert('Please enter a valid URL');
  } else if (error.isRateLimitError()) {
    // 429 - Too many requests
    alert('Slow down! Try again in a minute');
  } else if (error.isServerError()) {
    // 500+ - Server error
    alert('Server error, try again later');
  } else if (error.isNetworkError()) {
    // Network/timeout
    alert('Connection failed, check your internet');
  }
}
```

### Retry on Rate Limit

```javascript
async function generateWithRetry(url, maxRetries = 3) {
  const client = new ShortCodeClient();

  for (let i = 0; i < maxRetries; i++) {
    try {
      return await client.generateShortCode(url);
    } catch (error) {
      if (error.isRateLimitError() && i < maxRetries - 1) {
        await sleep(2000); // Wait 2 seconds
        continue;
      }
      throw error;
    }
  }
}

const sleep = (ms) => new Promise(r => setTimeout(r, ms));
```

---

## Response Fields

### All Endpoints

```javascript
const result = await client.generateShortCode(url);

result.shortCode      // "example"
result.method         // "rule-based" | "nlp-comprehend" | "gemini-ai"
result.wasModified    // true if collision occurred
result.message        // "Short code generated successfully"
```

### Fast Endpoint Only

```javascript
client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
const result = await client.generateShortCode(url);

result.candidates     // ["example", "example2", "example3"]
```

### Smart Endpoint Only

```javascript
client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);
const result = await client.generateShortCode(url);

result.candidates     // ["example", "example2"]
result.keyPhrases     // ["key phrase", "another phrase"]
result.entities       // ["Entity1", "Entity2"]
```

### AI Endpoint Only

```javascript
client.setEndpointType(ShortCodeClient.ENDPOINT_AI);
const result = await client.generateShortCode(url);

result.originalCode   // "example"
result.url            // "https://example.com"
```

### Check Endpoint Type

```javascript
if (result.isFast()) {
  console.log('Rule-based generation');
}
if (result.isSmart()) {
  console.log('NLP generation');
}
if (result.isAI()) {
  console.log('AI generation');
}
```

---

## Configuration

### Custom Base URL

```javascript
const client = new ShortCodeClient('https://custom-api.example.com/dev');
```

### Set Timeout

```javascript
const client = new ShortCodeClient();
client.setTimeout(10000); // 10 seconds

// For AI endpoint (slower)
client.setEndpointType(ShortCodeClient.ENDPOINT_AI);
client.setTimeout(30000); // 30 seconds
```

### Get Current Endpoint

```javascript
const endpoint = client.getFullEndpoint();
console.log(endpoint);
// "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/generate-short-code/fast"
```

---

## UI Integration Examples

### Show Loading State

```javascript
button.disabled = true;
button.textContent = 'Generating...';

try {
  const result = await client.generateShortCode(url);
  input.value = result.shortCode;
} finally {
  button.disabled = false;
  button.textContent = 'Generate';
}
```

### Show Alternative Suggestions

```javascript
const result = await client.generateShortCode(url);

// Show main result
mainInput.value = result.shortCode;

// Show alternatives
if (result.candidates) {
  alternativesList.innerHTML = result.candidates
    .filter(c => c !== result.shortCode)
    .map(c => `<li>${c}</li>`)
    .join('');
}
```

### Progress Indicator

```javascript
async function generateWithProgress(url) {
  progress.textContent = 'Generating...';

  client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
  const result = await client.generateShortCode(url);

  progress.textContent = `✓ Generated: ${result.shortCode}`;
  return result;
}
```

---

## Performance Tips

```javascript
// Fast endpoint - Use for instant suggestions
client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
// Response time: ~500ms

// Smart endpoint - Use for better quality
client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);
// Response time: ~800ms

// AI endpoint - Use for best quality (when quota available)
client.setEndpointType(ShortCodeClient.ENDPOINT_AI);
// Response time: ~3-5s
```

---

## Testing

### Run Unit Tests (Mocked)

```bash
npm run test:unit
```

### Run Integration Tests (Live API)

```bash
npm run test:integration
```

---

## Browser Requirements

- ES6 modules
- Fetch API
- AbortController
- Promises/async-await

**Supported:** Chrome 63+, Firefox 57+, Safari 11.1+, Edge 79+

---

## Common Mistakes

❌ **Don't do this:**
```javascript
// Calling without await
const result = client.generateShortCode(url);
console.log(result.shortCode); // undefined! (it's a Promise)
```

✅ **Do this:**
```javascript
// Use await or .then()
const result = await client.generateShortCode(url);
console.log(result.shortCode); // "example"
```

❌ **Don't do this:**
```javascript
// Not handling errors
const result = await client.generateShortCode(url);
// Could crash if API fails!
```

✅ **Do this:**
```javascript
// Always use try-catch
try {
  const result = await client.generateShortCode(url);
} catch (error) {
  console.error('Failed:', error.message);
}
```

---

## Complete Example

```javascript
import { ShortCodeClient, ShortCodeError } from './shortcode-client.js';

async function handleGenerate() {
  const url = document.getElementById('url').value;
  const resultDiv = document.getElementById('result');
  const button = document.getElementById('generate-btn');

  // Validation
  if (!url) {
    alert('Please enter a URL');
    return;
  }

  // UI loading state
  button.disabled = true;
  button.textContent = 'Generating...';
  resultDiv.textContent = '';

  try {
    // Initialize client
    const client = new ShortCodeClient();
    client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
    client.setTimeout(15000); // 15 seconds

    // Generate code
    const result = await client.generateShortCode(url, 'trfc.link');

    // Show result
    resultDiv.innerHTML = `
      <div class="success">
        <strong>Short code:</strong> ${result.shortCode}
        <br>
        <strong>Method:</strong> ${result.method}
        ${result.wasModified ? '<br><em>(Modified due to collision)</em>' : ''}
      </div>
    `;

    // Show alternatives
    if (result.candidates && result.candidates.length > 0) {
      resultDiv.innerHTML += `
        <div class="alternatives">
          <strong>Alternatives:</strong>
          ${result.candidates.join(', ')}
        </div>
      `;
    }

  } catch (error) {
    // Error handling
    let message = 'Failed to generate short code';

    if (error instanceof ShortCodeError) {
      if (error.isValidationError()) {
        message = 'Invalid URL format';
      } else if (error.isRateLimitError()) {
        message = 'Rate limit exceeded. Try again in a minute.';
      } else if (error.isServerError()) {
        message = 'Server error. Please try again later.';
      } else if (error.isNetworkError()) {
        message = 'Network error. Check your connection.';
      }
    }

    resultDiv.innerHTML = `<div class="error">${message}</div>`;
    console.error('Error:', error);

  } finally {
    // Reset UI
    button.disabled = false;
    button.textContent = 'Generate';
  }
}
```
