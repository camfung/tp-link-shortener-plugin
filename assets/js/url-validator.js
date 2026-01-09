/**
 * URL Validation Library
 *
 * Provides comprehensive URL validation with support for:
 * - HTTP header validation
 * - Redirect handling (301, 302, 307, 308)
 * - Content type validation
 * - User authentication awareness
 * - SSL/TLS validation
 *
 * @version 1.0.0
 */

class URLValidator {
  /**
   * Validation error types
   */
  static ErrorTypes = {
    INVALID_URL: 'invalid_url',
    NOT_AVAILABLE: 'not_available',
    PROTECTED: 'protected',
    SSL_ERROR: 'ssl_error',
    REDIRECT_PERMANENT: 'redirect_permanent',
    REDIRECT_TEMPORARY: 'redirect_temporary',
    INVALID_CONTENT_TYPE: 'invalid_content_type',
    NETWORK_ERROR: 'network_error'
  };

  /**
   * Border color styles for different error states
   */
  static BorderColors = {
    ERROR: '#dc3545',      // Red for errors
    WARNING: '#ffc107',    // Yellow/amber for warnings
    SUCCESS: '#28a745',    // Green for success
    DEFAULT: '#ced4da'     // Default gray
  };

  /**
   * HTTP status codes
   */
  static StatusCodes = {
    PERMANENT_REDIRECT: [301, 308],
    TEMPORARY_REDIRECT: [302, 303, 307],
    SUCCESS: [200, 201, 202, 203, 204, 205, 206]
  };

  /**
   * Content types allowed for different user types
   */
  static ContentTypes = {
    GUEST: ['text/html', 'text/plain', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
    REGISTERED: [] // Registered users can access all content types
  };

  /**
   * Create a new URL validator instance
   * @param {Object} options - Configuration options
   * @param {boolean} options.isUserRegistered - Whether the current user is registered/logged in
   * @param {string} options.proxyUrl - Optional proxy URL for CORS handling (e.g., '/api/validate-url')
   * @param {number} options.timeout - Request timeout in milliseconds (default: 10000)
   */
  constructor(options = {}) {
    this.isUserRegistered = options.isUserRegistered || false;
    this.proxyUrl = options.proxyUrl || null;
    this.timeout = options.timeout || 10000;
  }

  /**
   * Validate if a string is a properly formatted URL
   * @param {string} urlString - The URL string to validate
   * @returns {boolean} True if valid URL format
   */
  isValidURLFormat(urlString) {
    if (!urlString || typeof urlString !== 'string') {
      return false;
    }

    try {
      const url = new URL(urlString);
      return url.protocol === 'http:' || url.protocol === 'https:';
    } catch (error) {
      return false;
    }
  }

  /**
   * Perform online validation by fetching URL headers
   * @param {string} urlString - The URL to validate
   * @returns {Promise<Object>} Validation result object
   */
  async validateURL(urlString) {
    return this.validateURLWithAbort(urlString, null);
  }

  /**
   * Perform online validation by fetching URL headers with abort signal
   * @param {string} urlString - The URL to validate
   * @param {AbortSignal} abortSignal - Optional abort signal to cancel the request
   * @returns {Promise<Object>} Validation result object
   */
  async validateURLWithAbort(urlString, abortSignal = null) {
    // First, check URL format
    if (!this.isValidURLFormat(urlString)) {
      return this.createErrorResult(
        URLValidator.ErrorTypes.INVALID_URL,
        'Invalid URL format. Please enter a valid HTTP or HTTPS URL.',
        URLValidator.BorderColors.ERROR
      );
    }

    try {
      // Perform HEAD request to get headers
      const response = await this.fetchHeaders(urlString, abortSignal);

      // Check for authentication/protected resources FIRST
      if (response.status === 401 || response.status === 403) {
        if (this.isUserRegistered) {
          return this.createWarningResult(
            URLValidator.ErrorTypes.PROTECTED,
            'This is a protected resource. Ensure you have proper access.',
            URLValidator.BorderColors.WARNING
          );
        } else {
          return this.createErrorResult(
            URLValidator.ErrorTypes.PROTECTED,
            'Protected links are not allowed for guest users. Please log in.',
            URLValidator.BorderColors.ERROR
          );
        }
      }

      // Check if URL is available (other 4xx errors)
      if (!response.ok && response.status >= 400) {
        return this.createErrorResult(
          URLValidator.ErrorTypes.NOT_AVAILABLE,
          `URL not available (Status: ${response.status})`,
          URLValidator.BorderColors.ERROR
        );
      }

      // Check for permanent redirects
      if (URLValidator.StatusCodes.PERMANENT_REDIRECT.includes(response.status)) {
        const location = response.headers.get('Location');
        return this.createWarningResult(
          URLValidator.ErrorTypes.REDIRECT_PERMANENT,
          `Permanent redirect detected. Consider replacing with: ${location || 'target URL'}`,
          URLValidator.BorderColors.WARNING,
          { redirectLocation: location }
        );
      }

      // Check for temporary redirects
      if (URLValidator.StatusCodes.TEMPORARY_REDIRECT.includes(response.status)) {
        if (!this.isUserRegistered) {
          return this.createErrorResult(
            URLValidator.ErrorTypes.REDIRECT_TEMPORARY,
            'Temporary redirects are not allowed for guest users.',
            URLValidator.BorderColors.ERROR
          );
        }
      }

      // Check content type
      const contentType = response.headers.get('Content-Type');
      const contentTypeValidation = this.validateContentType(contentType);
      if (!contentTypeValidation.valid) {
        return contentTypeValidation.result;
      }

      // Check if protocol was updated (HTTPS -> HTTP fallback)
      if (response.protocolUpdated) {
        return this.createWarningResult(
          URLValidator.ErrorTypes.REDIRECT_PERMANENT,
          `URL changed from HTTPS to HTTP. ${response.updateReason || 'SSL certificate error detected.'}`,
          URLValidator.BorderColors.WARNING,
          {
            updatedUrl: response.updatedUrl,
            originalUrl: response.originalUrl,
            protocolUpdated: true
          }
        );
      }

      // All validations passed
      return this.createSuccessResult(
        'URL is valid and accessible.',
        URLValidator.BorderColors.SUCCESS
      );

    } catch (error) {
      // Handle SSL/TLS errors
      if (error.message && error.message.includes('SSL')) {
        return this.createErrorResult(
          URLValidator.ErrorTypes.SSL_ERROR,
          'SSL/TLS certificate error. The URL has an invalid or untrusted certificate.',
          URLValidator.BorderColors.ERROR
        );
      }

      // Handle network errors with user-friendly messages
      let friendlyMessage = 'Unable to reach this URL. Please check the address and try again.';

      // Check for common error patterns and provide specific messages
      if (error.message) {
        const msg = error.message.toLowerCase();
        if (msg.includes('could not resolve host') || msg.includes('enotfound') || msg.includes('getaddrinfo')) {
          friendlyMessage = 'This website address doesn\'t exist. Please check for typos.';
        } else if (msg.includes('connection refused') || msg.includes('econnrefused')) {
          friendlyMessage = 'Unable to connect to this website. The server may be down.';
        } else if (msg.includes('timeout') || msg.includes('etimedout')) {
          friendlyMessage = 'Connection timed out. The website is taking too long to respond.';
        } else if (msg.includes('reset') || msg.includes('econnreset')) {
          friendlyMessage = 'Connection was reset. Please try again.';
        }
      }

      return this.createErrorResult(
        URLValidator.ErrorTypes.NETWORK_ERROR,
        friendlyMessage,
        URLValidator.BorderColors.ERROR
      );
    }
  }

  /**
   * Fetch headers from a URL using HEAD request
   * @param {string} urlString - The URL to fetch headers from
   * @param {AbortSignal} externalSignal - Optional external abort signal
   * @returns {Promise<Response>} Fetch response object
   * @private
   */
  async fetchHeaders(urlString, externalSignal = null) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.timeout);

    // If external abort signal is provided, also abort on external signal
    let externalAbortHandler = null;
    if (externalSignal) {
      externalAbortHandler = () => controller.abort();
      externalSignal.addEventListener('abort', externalAbortHandler);
    }

    try {
      let fetchUrl = urlString;
      let options = {
        method: 'HEAD',
        signal: controller.signal,
        redirect: 'manual', // Don't follow redirects automatically
        mode: 'cors'
      };

      // If proxy URL is configured, use it to avoid CORS issues
      if (this.proxyUrl) {
        fetchUrl = `${this.proxyUrl}&url=${encodeURIComponent(urlString)}`;
        options.method = 'GET'; // Proxy uses GET
      }

      const response = await fetch(fetchUrl, options);
      clearTimeout(timeoutId);

      // If using proxy, transform the response to match expected format
      if (this.proxyUrl) {
        const data = await response.json();

        // Check if proxy returned an error (e.g., DNS resolution failure, connection refused)
        if (data.error) {
          throw new Error(data.error);
        }

        // Check if status is 0 or undefined (indicates network/DNS failure)
        if (!data.status || data.status === 0) {
          throw new Error('Unable to reach the destination URL');
        }

        // Create a mock Response-like object with protocol update info
        return {
          ok: data.ok,
          status: data.status,
          headers: {
            get: (key) => {
              // Case-insensitive header lookup
              const lowerKey = key.toLowerCase();
              for (const headerKey in data.headers) {
                if (headerKey.toLowerCase() === lowerKey) {
                  return data.headers[headerKey];
                }
              }
              return null;
            }
          },
          // Pass through protocol update information
          protocolUpdated: data.protocol_updated || false,
          updatedUrl: data.updated_url || null,
          originalUrl: data.original_url || null,
          updateReason: data.reason || null
        };
      }

      return response;
    } catch (error) {
      clearTimeout(timeoutId);

      if (error.name === 'AbortError') {
        throw new Error('Request timeout');
      }
      throw error;
    } finally {
      // Clean up external abort listener
      if (externalSignal && externalAbortHandler) {
        externalSignal.removeEventListener('abort', externalAbortHandler);
      }
    }
  }

  /**
   * Validate content type against allowed types
   * @param {string} contentType - Content-Type header value
   * @returns {Object} Validation result with valid flag and optional result object
   * @private
   */
  validateContentType(contentType) {
    if (!contentType) {
      // If no content type is specified, allow it (could be a redirect or other resource)
      return { valid: true };
    }

    // Extract the base content type (without charset and other parameters)
    const baseContentType = contentType.split(';')[0].trim().toLowerCase();

    // Registered users can access any content type
    if (this.isUserRegistered) {
      return { valid: true };
    }

    // For guest users, check against allowed content types
    const isStaticPage = baseContentType.startsWith('text/html') || baseContentType.startsWith('text/plain');
    const isImage = baseContentType.startsWith('image/');
    const isAllowedForGuest = URLValidator.ContentTypes.GUEST.some(type =>
      baseContentType.startsWith(type)
    );

    if (!isStaticPage && !isImage && !isAllowedForGuest) {
      return {
        valid: false,
        result: this.createErrorResult(
          URLValidator.ErrorTypes.INVALID_CONTENT_TYPE,
          `Content type '${baseContentType}' is not allowed for guest users. Only static pages and images are permitted.`,
          URLValidator.BorderColors.ERROR
        )
      };
    }

    return { valid: true };
  }

  /**
   * Create an error result object
   * @param {string} errorType - The type of error
   * @param {string} message - Error message
   * @param {string} borderColor - Border color for UI
   * @param {Object} additionalData - Additional data to include
   * @returns {Object} Error result object
   * @private
   */
  createErrorResult(errorType, message, borderColor, additionalData = {}) {
    return {
      valid: false,
      isError: true,
      isWarning: false,
      errorType,
      message,
      borderColor,
      ...additionalData
    };
  }

  /**
   * Create a warning result object
   * @param {string} errorType - The type of warning
   * @param {string} message - Warning message
   * @param {string} borderColor - Border color for UI
   * @param {Object} additionalData - Additional data to include
   * @returns {Object} Warning result object
   * @private
   */
  createWarningResult(errorType, message, borderColor, additionalData = {}) {
    return {
      valid: true,
      isError: false,
      isWarning: true,
      errorType,
      message,
      borderColor,
      ...additionalData
    };
  }

  /**
   * Create a success result object
   * @param {string} message - Success message
   * @param {string} borderColor - Border color for UI
   * @returns {Object} Success result object
   * @private
   */
  createSuccessResult(message, borderColor) {
    return {
      valid: true,
      isError: false,
      isWarning: false,
      errorType: null,
      message,
      borderColor
    };
  }

  /**
   * Apply validation result to a form input element
   * @param {HTMLInputElement} inputElement - The input element to apply validation to
   * @param {Object} validationResult - The validation result from validateURL()
   * @param {HTMLElement} messageElement - Optional element to display the message
   */
  applyValidationToElement(inputElement, validationResult, messageElement = null) {
    if (!inputElement) {
      console.error('Input element is required');
      return;
    }

    // Apply border color
    inputElement.style.borderColor = validationResult.borderColor;
    inputElement.style.borderWidth = '2px';

    // Set custom validity for HTML5 validation
    if (validationResult.isError) {
      inputElement.setCustomValidity(validationResult.message);
    } else {
      inputElement.setCustomValidity('');
    }

    // Display message if message element is provided
    if (messageElement) {
      messageElement.textContent = validationResult.message;
      messageElement.className = validationResult.isError ? 'error-message' :
                                 validationResult.isWarning ? 'warning-message' :
                                 'success-message';
    }
  }

  /**
   * Create a debounced validation function for real-time input validation
   * @param {Function} callback - Callback function to execute after validation
   * @param {number} delay - Debounce delay in milliseconds (default: 500)
   * @returns {Function} Debounced validation function
   */
  createDebouncedValidator(callback, delay = 500) {
    let timeoutId = null;
    let currentAbortController = null;
    let currentValidationUrl = null;

    return async (urlString, inputElement, messageElement) => {
      // Clear previous timeout
      if (timeoutId) {
        clearTimeout(timeoutId);
      }

      // Abort any in-flight validation request
      if (currentAbortController) {
        currentAbortController.abort();
        currentAbortController = null;
      }

      // Reset to default while typing
      if (inputElement) {
        inputElement.style.borderColor = URLValidator.BorderColors.DEFAULT;
      }
      if (messageElement) {
        messageElement.textContent = '';
      }

      // Only validate if URL format is valid
      if (!this.isValidURLFormat(urlString)) {
        if (urlString.length > 0) {
          const invalidResult = this.createErrorResult(
            URLValidator.ErrorTypes.INVALID_URL,
            'Invalid URL format',
            URLValidator.BorderColors.ERROR
          );

          if (inputElement) {
            inputElement.style.borderColor = URLValidator.BorderColors.ERROR;
          }
          if (messageElement) {
            messageElement.textContent = 'Invalid URL format';
            messageElement.className = 'error-message';
          }

          // Call callback even for invalid format
          if (callback) {
            callback(invalidResult, urlString);
          }
        }
        return;
      }

      // Set new timeout for validation
      timeoutId = setTimeout(async () => {
        // Create new AbortController for this validation
        currentAbortController = new AbortController();
        currentValidationUrl = urlString;

        try {
          const result = await this.validateURLWithAbort(urlString, currentAbortController.signal);

          // Ignore results for old URLs (race condition protection)
          if (urlString !== currentValidationUrl) {
            console.log('Ignoring stale validation result for:', urlString);
            return;
          }

          if (inputElement && messageElement) {
            this.applyValidationToElement(inputElement, result, messageElement);
          }

          if (callback) {
            callback(result, urlString);
          }
        } catch (error) {
          // Ignore AbortError - it's intentional when user types new URL
          if (error.name === 'AbortError') {
            console.log('Validation aborted for:', urlString);
            return;
          }

          // Ignore errors for stale URLs
          if (urlString !== currentValidationUrl) {
            console.log('Ignoring stale validation error for:', urlString);
            return;
          }

          // Handle any uncaught errors (like CORS issues)
          console.error('Validation error:', error);
          const errorResult = this.createErrorResult(
            URLValidator.ErrorTypes.NETWORK_ERROR,
            'Unable to validate URL: ' + error.message,
            URLValidator.BorderColors.ERROR
          );

          if (inputElement && messageElement) {
            this.applyValidationToElement(inputElement, errorResult, messageElement);
          }

          if (callback) {
            callback(errorResult, urlString);
          }
        } finally {
          // Clear the abort controller if this was the current validation
          if (currentValidationUrl === urlString) {
            currentAbortController = null;
          }
        }
      }, delay);
    };
  }
}

// Export for different module systems
if (typeof module !== 'undefined' && module.exports) {
  module.exports = URLValidator;
}

if (typeof window !== 'undefined') {
  window.URLValidator = URLValidator;
}
