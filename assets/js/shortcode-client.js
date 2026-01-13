/**
 * Short Code Generation API Client
 *
 * Supports three endpoint types:
 * - fast: Rule-based keyword extraction (~500ms)
 * - smart: AWS Comprehend NLP (~800ms)
 * - ai: Gemini AI generation (~3-5s)
 */
class ShortCodeClient {
  /**
   * @param {string} baseUrl - Base URL for the API
   */
  constructor(baseUrl = 'https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev') {
    this.baseUrl = baseUrl.replace(/\/$/, ''); // Remove trailing slash
    this.endpointPath = '/generate-short-code'; // Default to legacy/AI
    this.timeout = 30000; // 30 seconds default
  }

  /**
   * Endpoint type constants
   */
  static get ENDPOINT_FAST() { return '/generate-short-code/fast'; }
  static get ENDPOINT_SMART() { return '/generate-short-code/smart'; }
  static get ENDPOINT_AI() { return '/generate-short-code/ai'; }
  static get ENDPOINT_LEGACY() { return '/generate-short-code'; }

  /**
   * Set the endpoint type
   * @param {string} endpointPath - One of the ENDPOINT_* constants
   */
  setEndpointType(endpointPath) {
    this.endpointPath = endpointPath;
  }

  /**
   * Set request timeout in milliseconds
   * @param {number} timeout - Timeout in ms
   */
  setTimeout(timeout) {
    this.timeout = timeout;
  }

  /**
   * Get the full endpoint URL
   * @returns {string}
   */
  getFullEndpoint() {
    return this.baseUrl + this.endpointPath;
  }

  /**
   * Generate a short code from a URL
   * @param {string} url - URL to generate short code from
   * @param {string|null} domain - Optional domain to check availability
   * @returns {Promise<ShortCodeResponse>}
   * @throws {ShortCodeError}
   */
  async generateShortCode(url, domain = null) {
    // Build request payload
    const payload = { url };
    if (domain) {
      payload.domain = domain;
    }

    // Create abort controller for timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.timeout);

    try {
      const response = await fetch(this.getFullEndpoint(), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      // Parse response
      const data = await response.json();

      // Handle HTTP errors
      if (!response.ok) {
        throw new ShortCodeError(
          data.message || `HTTP ${response.status}: ${response.statusText}`,
          response.status,
          data
        );
      }

      // Handle API-level errors
      if (!data.success) {
        throw new ShortCodeError(
          data.message || 'API indicated failure',
          response.status,
          data
        );
      }

      // Validate required fields
      if (!data.source || !data.source.short_code || !data.source.method || data.source.was_modified === undefined) {
        throw new ShortCodeError(
          'Response missing required fields (short_code, method, was_modified)',
          response.status,
          data
        );
      }

      return new ShortCodeResponse(data);

    } catch (error) {
      clearTimeout(timeoutId);

      // Handle abort/timeout
      if (error.name === 'AbortError') {
        throw new ShortCodeError(
          `Request timeout after ${this.timeout}ms`,
          0,
          null
        );
      }

      // Re-throw ShortCodeError as-is
      if (error instanceof ShortCodeError) {
        throw error;
      }

      // Wrap network errors
      throw new ShortCodeError(
        `Network error: ${error.message}`,
        0,
        null
      );
    }
  }
}

/**
 * Short Code Response wrapper
 */
class ShortCodeResponse {
  constructor(data) {
    this.message = data.message || '';
    this.success = data.success || false;

    const source = data.source || {};
    this.shortCode = source.short_code;
    this.method = source.method;
    this.wasModified = source.was_modified;

    // Optional fields (endpoint-specific)
    this.originalCode = source.original_code || null;
    this.url = source.url || null;
    this.candidates = source.candidates || null;
    this.keyPhrases = source.key_phrases || null;
    this.entities = source.entities || null;
  }

  /**
   * Check if this is a Fast endpoint response
   */
  isFast() {
    return this.method === 'rule-based';
  }

  /**
   * Check if this is a Smart endpoint response
   */
  isSmart() {
    return this.method === 'nlp-comprehend';
  }

  /**
   * Check if this is an AI endpoint response
   */
  isAI() {
    return this.method === 'gemini-ai';
  }
}

/**
 * Custom error class for Short Code API errors
 */
class ShortCodeError extends Error {
  constructor(message, statusCode, data) {
    super(message);
    this.name = 'ShortCodeError';
    this.statusCode = statusCode;
    this.data = data;
  }

  /**
   * Check if this is a validation error (400)
   */
  isValidationError() {
    return this.statusCode === 400;
  }

  /**
   * Check if this is a rate limit error (429)
   */
  isRateLimitError() {
    return this.statusCode === 429;
  }

  /**
   * Check if this is a server error (500+)
   */
  isServerError() {
    return this.statusCode >= 500;
  }

  /**
   * Check if this is a network/timeout error
   */
  isNetworkError() {
    return this.statusCode === 0;
  }
}

// Export for ES modules
export { ShortCodeClient, ShortCodeResponse, ShortCodeError };
