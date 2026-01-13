# Generate Short Code API

Generate meaningful, memorable short codes from webpage content. Three tiered endpoints provide different speed/accuracy tradeoffs.

All endpoints work the same way:
1. Client sends URL
2. Server fetches the page
3. Server extracts title, description, and text from HTML
4. **Fallback:** If no title found, extracts keywords from URL path
5. Server generates short code using the extracted data

## Endpoints Overview

| Endpoint | Speed | Method | Best For |
|----------|-------|--------|----------|
| `/generate-short-code/fast` | ~500ms | Rule-based keyword extraction | Simple pages, quick results |
| `/generate-short-code/smart` | ~800ms | AWS Comprehend NLP | Balance of speed and quality |
| `/generate-short-code/ai` | ~3-5s | Gemini AI | Best quality, creative codes |
| `/generate-short-code` | ~3-5s | Gemini AI (legacy path) | Backwards compatibility |

**Base URL (Dev):** `https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev`

---

## Request Format (All Endpoints)

All endpoints accept the same request format:

```json
{
  "url": "https://example.com/page",
  "domain": "trfc.link"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| url | string | **Yes** | URL to fetch and analyze. Must start with `http://` or `https://` |
| domain | string | No | Domain to check short code availability against |

### Example Request (cURL)

```bash
curl -X POST "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/generate-short-code/fast" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com/chocolate-cake-recipe"}'
```

### Example Request (JavaScript)

```javascript
const response = await fetch('/generate-short-code/fast', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    url: 'https://example.com/chocolate-cake-recipe',
    domain: 'trfc.link'
  })
});

const data = await response.json();
console.log(data.source.short_code);  // "chocolatecake"
```

---

## Endpoint 1: Fast (Rule-Based)

**`POST /generate-short-code/fast`**

Server fetches URL, extracts metadata, generates short code using rule-based keyword extraction.

### How it works
1. Fetches HTML from URL
2. Extracts title, meta description, meta keywords from HTML
3. Scores and ranks words by importance
4. Combines top words into short code candidates
5. Checks availability and returns first available code

### Response

```json
{
  "message": "Short code generated successfully",
  "source": {
    "short_code": "chocolatecake",
    "method": "rule-based",
    "was_modified": false,
    "candidates": ["chocolatecake", "bestchocolate", "cakerecipe"]
  },
  "success": true
}
```

---

## Endpoint 2: Smart (AWS Comprehend NLP)

**`POST /generate-short-code/smart`**

Server fetches URL, extracts metadata, uses AWS Comprehend for NLP-based keyword extraction.

### How it works
1. Fetches HTML from URL
2. Extracts title, meta description, body text from HTML
3. Sends clean text to AWS Comprehend
4. Extracts key phrases and named entities
5. Combines into short code candidates
6. Checks availability and returns first available code

### Response

```json
{
  "message": "Short code generated successfully",
  "source": {
    "short_code": "chocolatecake",
    "method": "nlp-comprehend",
    "was_modified": false,
    "candidates": ["chocolatecake", "cakerecipe", "bestchocolate"],
    "key_phrases": ["chocolate cake", "best recipe", "homemade"],
    "entities": ["chocolate", "cake"]
  },
  "success": true
}
```

---

## Endpoint 3: AI (Gemini)

**`POST /generate-short-code/ai`** (or `/generate-short-code`)

Server fetches URL, extracts metadata, uses Google Gemini AI for creative code generation.

### How it works
1. Fetches HTML from URL
2. Extracts title, meta description, body text from HTML
3. Sends clean extracted data to Gemini (NOT raw HTML)
4. Gemini generates creative, context-aware short code
5. Checks availability and returns available code

### Response

```json
{
  "message": "Short code generated successfully",
  "source": {
    "short_code": "perfectchocolate",
    "original_code": "perfectchocolate",
    "method": "gemini-ai",
    "was_modified": false,
    "url": "https://example.com/chocolate-cake-recipe"
  },
  "success": true
}
```

---

## Short Code Characteristics

All endpoints generate codes with these properties:

- **Length:** 6-16 characters
- **Characters:** Lowercase letters (a-z) and numbers (0-9) only
- **Full words:** Prefers complete words over abbreviations
- **Memorable:** Easy to type and remember

---

## Fallback Behavior

If the page has no `<title>` tag or meta tags, the system falls back to extracting keywords from the URL path:

**Example:**
```
URL: https://example.com/blog/best-chocolate-cake-recipe

Extracted keywords: ["blog", "best", "chocolate", "cake", "recipe"]
Generated code: "chocolatecake" or "bestchocolate"
```

**Extraction order:**
1. `<title>` tag
2. `og:title` meta tag
3. URL path keywords (fallback)

This ensures short codes can be generated even for pages with minimal HTML metadata.

---

## Collision Handling

All endpoints automatically handle code collisions:

1. Generate initial code based on content
2. Check availability against database
3. If taken, append numeric suffixes: `code1`, `code2`, `code3`...
4. Try up to 100 variations before failing
5. Truncate base if code exceeds 16 characters

**Response when collision occurred:**
```json
{
  "source": {
    "short_code": "chocolatecake2",
    "was_modified": true
  }
}
```

---

## Error Responses

### Missing URL (400)

```json
{
  "message": "Missing required field: url",
  "source": null,
  "success": false
}
```

### Invalid URL Format (400)

```json
{
  "message": "Invalid URL format. Must start with http:// or https://",
  "source": null,
  "success": false
}
```

### Failed to Fetch URL (400)

```json
{
  "message": "Failed to fetch URL: HTTP Error 404: Not Found",
  "source": null,
  "success": false
}
```

### Could Not Extract Content (400)

Returned only when both HTML metadata AND URL path contain no usable keywords.

```json
{
  "message": "Could not extract content from URL",
  "source": null,
  "success": false
}
```

### No Available Code (500)

```json
{
  "message": "Could not find available short code",
  "source": null,
  "success": false
}
```

---

## Cost Comparison

Lambda memory: 1024MB (~400-500 Mbps network speed)

| Endpoint | Lambda | External API | Total/request | Monthly (10K req) |
|----------|--------|--------------|---------------|-------------------|
| **Fast** | ~$0.000005 | $0 | **~$0.000005** | **~$0.05** |
| **Smart** | ~$0.00001 | ~$0.0002 | **~$0.0002** | **~$2.10** |
| **AI** | ~$0.00005 | ~$0.0005 | **~$0.00055** | **~$5.50** |

---

## Recommended Usage

Call multiple endpoints for progressive enhancement:

```javascript
async function generateShortCode(url) {
  // Show fast result immediately (~500ms)
  const fast = await fetch('/generate-short-code/fast', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ url })
  }).then(r => r.json());

  showResult(fast.source.short_code);

  // Optionally fetch better suggestion in background
  const smart = await fetch('/generate-short-code/smart', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ url })
  }).then(r => r.json());

  if (smart.source.short_code !== fast.source.short_code) {
    offerAlternative(smart.source.short_code);
  }
}
```
