# JavaScript Client Cheat Sheet - Short Code Generation API

## Quick Reference

### Three Endpoints

| Endpoint | Speed | Method | Use Case |
|----------|-------|--------|----------|
| `/generate-short-code/fast` | ~500ms | `rule-based` | Quick results, simple pages |
| `/generate-short-code/smart` | ~800ms | `nlp-comprehend` | Balanced quality & speed |
| `/generate-short-code/ai` | ~3-5s | `gemini-ai` | Best quality, creative codes |

**Base URL:** `https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev`

---

## Request Format (All Endpoints)

```javascript
const payload = {
  url: "https://example.com/page",    // Required: URL to analyze
  domain: "trfc.link"                  // Optional: Check availability
};
```

---

## Response Formats

### Common Fields (All Endpoints)

```javascript
{
  message: "Short code generated successfully",
  source: {
    short_code: "example",           // Generated code
    method: "rule-based",            // Endpoint type
    was_modified: false              // If suffix added (collision)
  },
  success: true
}
```

### Fast Endpoint - Additional Fields

```javascript
source: {
  candidates: ["example", "example2", "example3"]  // Alternative codes
}
```

### Smart Endpoint - Additional Fields

```javascript
source: {
  candidates: ["example", "example2"],
  key_phrases: ["example phrase", "another phrase"],
  entities: ["Entity1", "Entity2"]
}
```

### AI Endpoint - Additional Fields

```javascript
source: {
  original_code: "example",          // Before collision handling
  url: "https://example.com/page"    // Original URL
}
```

---

## JavaScript Client Implementation

### Basic Client Class

```javascript
class ShortCodeClient {
  constructor(baseUrl = 'https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev') {
    this.baseUrl = baseUrl;
    this.endpoint = '/generate-short-code';  // Default to legacy/AI
  }

  // Set endpoint type
  setEndpointType(type) {
    const endpoints = {
      fast: '/generate-short-code/fast',
      smart: '/generate-short-code/smart',
      ai: '/generate-short-code/ai',
      legacy: '/generate-short-code'
    };
    this.endpoint = endpoints[type] || endpoints.legacy;
  }

  // Generate short code
  async generateShortCode(url, domain = null) {
    const payload = { url };
    if (domain) payload.domain = domain;

    const response = await fetch(this.baseUrl + this.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message || 'API indicated failure');
    }

    return data.source;
  }
}
```

### Usage Examples

```javascript
// Initialize client
const client = new ShortCodeClient();

// Example 1: Fast endpoint
client.setEndpointType('fast');
const fast = await client.generateShortCode('https://example.com');
console.log(fast.short_code);      // "example"
console.log(fast.method);          // "rule-based"
console.log(fast.candidates);      // ["example", "example2", ...]

// Example 2: Smart endpoint
client.setEndpointType('smart');
const smart = await client.generateShortCode('https://example.com', 'trfc.link');
console.log(smart.short_code);     // "example"
console.log(smart.method);         // "nlp-comprehend"
console.log(smart.key_phrases);    // ["key phrase", ...]
console.log(smart.entities);       // ["Entity1", ...]

// Example 3: AI endpoint
client.setEndpointType('ai');
const ai = await client.generateShortCode('https://example.com');
console.log(ai.short_code);        // "example"
console.log(ai.method);            // "gemini-ai"
console.log(ai.original_code);     // "example" (before collision handling)
console.log(ai.url);               // "https://example.com"
```

---

## Progressive Enhancement Pattern

```javascript
async function generateWithFallback(url, domain) {
  const client = new ShortCodeClient();

  try {
    // Try fast first (~500ms)
    client.setEndpointType('fast');
    const result = await client.generateShortCode(url, domain);
    showResult(result.short_code);
    return result;
  } catch (error) {
    console.error('Fast endpoint failed:', error);

    try {
      // Fallback to smart (~800ms)
      client.setEndpointType('smart');
      const result = await client.generateShortCode(url, domain);
      showResult(result.short_code);
      return result;
    } catch (error) {
      console.error('Smart endpoint failed:', error);
      throw error;
    }
  }
}
```

---

## Error Handling

```javascript
async function generateSafely(url, domain) {
  try {
    const result = await client.generateShortCode(url, domain);
    return result;
  } catch (error) {
    if (error.message.includes('429')) {
      // Rate limit - retry after delay
      console.error('Rate limited, retry later');
    } else if (error.message.includes('400')) {
      // Validation error
      console.error('Invalid URL format');
    } else if (error.message.includes('500')) {
      // Server error
      console.error('Server error occurred');
    } else {
      // Network or other error
      console.error('Request failed:', error);
    }
    throw error;
  }
}
```

---

## Key Implementation Checklist

- [ ] Support all three endpoints (fast, smart, ai)
- [ ] Handle optional `domain` parameter
- [ ] Parse `method` field to identify endpoint type
- [ ] Handle endpoint-specific fields:
  - `candidates[]` (fast/smart)
  - `key_phrases[]`, `entities[]` (smart only)
  - `original_code`, `url` (ai only)
- [ ] Check `was_modified` for collision detection
- [ ] Implement error handling (400, 429, 500)
- [ ] Add timeout handling (fast: 5s, smart: 10s, ai: 30s)
- [ ] Support progressive enhancement (fast → smart → ai)

---

## Testing Checklist

- [ ] Test all three endpoints with live API
- [ ] Test with and without `domain` parameter
- [ ] Verify all response fields parse correctly
- [ ] Test error handling (invalid URL, rate limits)
- [ ] Test collision handling (`was_modified: true`)
- [ ] Measure actual response times
- [ ] Test timeout scenarios

---

## Common Pitfalls

1. **Forgetting to check `success` field** - Always validate before using response
2. **Not handling optional fields** - Check if field exists before accessing
3. **Wrong timeout values** - AI endpoint needs 30s+, not 5s
4. **Missing method field** - Required to identify which endpoint was used
5. **Ignoring `was_modified`** - Important for tracking collision handling
