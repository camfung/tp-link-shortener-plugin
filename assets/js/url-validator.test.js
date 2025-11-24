/**
 * Unit tests for URL Validation Library
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { JSDOM } from 'jsdom';

// Setup DOM environment
const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
global.window = dom.window;
global.document = dom.window.document;
global.HTMLInputElement = dom.window.HTMLInputElement;

// Import the URLValidator after setting up the DOM
const URLValidator = (await import('./url-validator.js')).default || require('./url-validator.js');

describe('URLValidator - Constructor and Configuration', () => {
  it('should create instance with default options', () => {
    const validator = new URLValidator();
    expect(validator.isUserRegistered).toBe(false);
    expect(validator.proxyUrl).toBe(null);
    expect(validator.timeout).toBe(10000);
  });

  it('should create instance with custom options', () => {
    const validator = new URLValidator({
      isUserRegistered: true,
      proxyUrl: '/api/validate',
      timeout: 5000
    });
    expect(validator.isUserRegistered).toBe(true);
    expect(validator.proxyUrl).toBe('/api/validate');
    expect(validator.timeout).toBe(5000);
  });
});

describe('URLValidator - isValidURLFormat()', () => {
  let validator;

  beforeEach(() => {
    validator = new URLValidator();
  });

  it('should validate correct HTTP URL', () => {
    expect(validator.isValidURLFormat('http://example.com')).toBe(true);
  });

  it('should validate correct HTTPS URL', () => {
    expect(validator.isValidURLFormat('https://example.com')).toBe(true);
  });

  it('should validate URL with path', () => {
    expect(validator.isValidURLFormat('https://example.com/path/to/page')).toBe(true);
  });

  it('should validate URL with query parameters', () => {
    expect(validator.isValidURLFormat('https://example.com?param=value&foo=bar')).toBe(true);
  });

  it('should validate URL with hash', () => {
    expect(validator.isValidURLFormat('https://example.com/page#section')).toBe(true);
  });

  it('should validate URL with port', () => {
    expect(validator.isValidURLFormat('https://example.com:8080/path')).toBe(true);
  });

  it('should reject invalid URL string', () => {
    expect(validator.isValidURLFormat('not a url')).toBe(false);
  });

  it('should reject empty string', () => {
    expect(validator.isValidURLFormat('')).toBe(false);
  });

  it('should reject null', () => {
    expect(validator.isValidURLFormat(null)).toBe(false);
  });

  it('should reject undefined', () => {
    expect(validator.isValidURLFormat(undefined)).toBe(false);
  });

  it('should reject non-HTTP protocols', () => {
    expect(validator.isValidURLFormat('ftp://example.com')).toBe(false);
    expect(validator.isValidURLFormat('file:///path/to/file')).toBe(false);
  });

  it('should reject URL without protocol', () => {
    expect(validator.isValidURLFormat('example.com')).toBe(false);
  });
});

describe('URLValidator - validateContentType()', () => {
  it('should allow HTML content for guest users', () => {
    const validator = new URLValidator({ isUserRegistered: false });
    const result = validator.validateContentType('text/html; charset=utf-8');
    expect(result.valid).toBe(true);
  });

  it('should allow image content for guest users', () => {
    const validator = new URLValidator({ isUserRegistered: false });
    const result = validator.validateContentType('image/png');
    expect(result.valid).toBe(true);
  });

  it('should allow various image types for guest users', () => {
    const validator = new URLValidator({ isUserRegistered: false });
    expect(validator.validateContentType('image/jpeg').valid).toBe(true);
    expect(validator.validateContentType('image/gif').valid).toBe(true);
    expect(validator.validateContentType('image/webp').valid).toBe(true);
    expect(validator.validateContentType('image/svg+xml').valid).toBe(true);
  });

  it('should reject video content for guest users', () => {
    const validator = new URLValidator({ isUserRegistered: false });
    const result = validator.validateContentType('video/mp4');
    expect(result.valid).toBe(false);
    expect(result.result.errorType).toBe(URLValidator.ErrorTypes.INVALID_CONTENT_TYPE);
    expect(result.result.message).toContain('video/mp4');
  });

  it('should reject application content for guest users', () => {
    const validator = new URLValidator({ isUserRegistered: false });
    const result = validator.validateContentType('application/pdf');
    expect(result.valid).toBe(false);
    expect(result.result.errorType).toBe(URLValidator.ErrorTypes.INVALID_CONTENT_TYPE);
  });

  it('should allow all content types for registered users', () => {
    const validator = new URLValidator({ isUserRegistered: true });
    expect(validator.validateContentType('video/mp4').valid).toBe(true);
    expect(validator.validateContentType('application/pdf').valid).toBe(true);
    expect(validator.validateContentType('audio/mpeg').valid).toBe(true);
    expect(validator.validateContentType('application/zip').valid).toBe(true);
  });

  it('should allow missing content type', () => {
    const validator = new URLValidator({ isUserRegistered: false });
    const result = validator.validateContentType(null);
    expect(result.valid).toBe(true);
  });

  it('should handle content type with charset', () => {
    const validator = new URLValidator({ isUserRegistered: false });
    const result = validator.validateContentType('text/html; charset=UTF-8');
    expect(result.valid).toBe(true);
  });
});

describe('URLValidator - createErrorResult()', () => {
  let validator;

  beforeEach(() => {
    validator = new URLValidator();
  });

  it('should create error result with correct properties', () => {
    const result = validator.createErrorResult(
      URLValidator.ErrorTypes.INVALID_URL,
      'Test error message',
      URLValidator.BorderColors.ERROR
    );

    expect(result.valid).toBe(false);
    expect(result.isError).toBe(true);
    expect(result.isWarning).toBe(false);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.INVALID_URL);
    expect(result.message).toBe('Test error message');
    expect(result.borderColor).toBe(URLValidator.BorderColors.ERROR);
  });

  it('should include additional data in error result', () => {
    const result = validator.createErrorResult(
      URLValidator.ErrorTypes.NETWORK_ERROR,
      'Network error',
      URLValidator.BorderColors.ERROR,
      { customField: 'customValue' }
    );

    expect(result.customField).toBe('customValue');
  });
});

describe('URLValidator - createWarningResult()', () => {
  let validator;

  beforeEach(() => {
    validator = new URLValidator();
  });

  it('should create warning result with correct properties', () => {
    const result = validator.createWarningResult(
      URLValidator.ErrorTypes.REDIRECT_PERMANENT,
      'Warning message',
      URLValidator.BorderColors.WARNING
    );

    expect(result.valid).toBe(true);
    expect(result.isError).toBe(false);
    expect(result.isWarning).toBe(true);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.REDIRECT_PERMANENT);
    expect(result.message).toBe('Warning message');
    expect(result.borderColor).toBe(URLValidator.BorderColors.WARNING);
  });

  it('should include redirect location in warning result', () => {
    const result = validator.createWarningResult(
      URLValidator.ErrorTypes.REDIRECT_PERMANENT,
      'Redirect warning',
      URLValidator.BorderColors.WARNING,
      { redirectLocation: 'https://example.com/new-url' }
    );

    expect(result.redirectLocation).toBe('https://example.com/new-url');
  });
});

describe('URLValidator - createSuccessResult()', () => {
  let validator;

  beforeEach(() => {
    validator = new URLValidator();
  });

  it('should create success result with correct properties', () => {
    const result = validator.createSuccessResult(
      'URL is valid',
      URLValidator.BorderColors.SUCCESS
    );

    expect(result.valid).toBe(true);
    expect(result.isError).toBe(false);
    expect(result.isWarning).toBe(false);
    expect(result.errorType).toBe(null);
    expect(result.message).toBe('URL is valid');
    expect(result.borderColor).toBe(URLValidator.BorderColors.SUCCESS);
  });
});

describe('URLValidator - validateURL() with mocked fetch', () => {
  let validator;

  beforeEach(() => {
    validator = new URLValidator();
    // Clear all mocks before each test
    vi.clearAllMocks();
  });

  it('should return error for invalid URL format', async () => {
    const result = await validator.validateURL('not-a-url');
    expect(result.valid).toBe(false);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.INVALID_URL);
    expect(result.isError).toBe(true);
  });

  it('should return success for valid URL with 200 status', async () => {
    // Mock fetch
    global.fetch = vi.fn(() =>
      Promise.resolve({
        ok: true,
        status: 200,
        headers: new Map([['Content-Type', 'text/html']])
      })
    );

    // Override the headers.get method
    global.fetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      headers: {
        get: (key) => {
          if (key === 'Content-Type') return 'text/html';
          return null;
        }
      }
    });

    const result = await validator.validateURL('https://example.com');
    expect(result.valid).toBe(true);
    expect(result.isError).toBe(false);
    expect(result.borderColor).toBe(URLValidator.BorderColors.SUCCESS);
  });

  it('should return error for 404 status', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: false,
      status: 404,
      headers: {
        get: () => null
      }
    });

    const result = await validator.validateURL('https://example.com/notfound');
    expect(result.valid).toBe(false);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.NOT_AVAILABLE);
    expect(result.message).toContain('404');
  });

  it('should return error for protected URL (401) for guest users', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: false,
      status: 401,
      headers: {
        get: () => null
      }
    });

    const result = await validator.validateURL('https://example.com/protected');
    expect(result.valid).toBe(false);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.PROTECTED);
    expect(result.message).toContain('guest');
  });

  it('should return warning for protected URL (401) for registered users', async () => {
    const registeredValidator = new URLValidator({ isUserRegistered: true });
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: false,
      status: 401,
      headers: {
        get: () => null
      }
    });

    const result = await registeredValidator.validateURL('https://example.com/protected');
    expect(result.valid).toBe(true);
    expect(result.isWarning).toBe(true);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.PROTECTED);
  });

  it('should return warning for permanent redirect (301)', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 301,
      headers: {
        get: (key) => {
          if (key === 'Location') return 'https://example.com/new-location';
          return null;
        }
      }
    });

    const result = await validator.validateURL('https://example.com/old');
    expect(result.valid).toBe(true);
    expect(result.isWarning).toBe(true);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.REDIRECT_PERMANENT);
    expect(result.message).toContain('https://example.com/new-location');
  });

  it('should return warning for permanent redirect (308)', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 308,
      headers: {
        get: (key) => {
          if (key === 'Location') return 'https://example.com/permanent';
          return null;
        }
      }
    });

    const result = await validator.validateURL('https://example.com/old');
    expect(result.valid).toBe(true);
    expect(result.isWarning).toBe(true);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.REDIRECT_PERMANENT);
  });

  it('should return error for temporary redirect (302) for guest users', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 302,
      headers: {
        get: () => null
      }
    });

    const result = await validator.validateURL('https://example.com/temp');
    expect(result.valid).toBe(false);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.REDIRECT_TEMPORARY);
    expect(result.message).toContain('guest');
  });

  it('should allow temporary redirect (307) for registered users', async () => {
    const registeredValidator = new URLValidator({ isUserRegistered: true });
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 307,
      headers: {
        get: (key) => {
          if (key === 'Content-Type') return 'text/html';
          return null;
        }
      }
    });

    const result = await registeredValidator.validateURL('https://example.com/temp');
    expect(result.valid).toBe(true);
  });

  it('should handle network errors', async () => {
    global.fetch = vi.fn().mockRejectedValueOnce(new Error('Network failure'));

    const result = await validator.validateURL('https://example.com');
    expect(result.valid).toBe(false);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.NETWORK_ERROR);
    expect(result.message).toContain('Network failure');
  });

  it('should handle SSL errors', async () => {
    global.fetch = vi.fn().mockRejectedValueOnce(new Error('SSL certificate error'));

    const result = await validator.validateURL('https://example.com');
    expect(result.valid).toBe(false);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.SSL_ERROR);
    expect(result.message).toContain('SSL');
  });

  it('should reject URLs with invalid/non-existent TLDs like .c', async () => {
    // This URL has a syntactically valid format but an invalid TLD (.c doesn't exist)
    // The network request should fail with DNS resolution error
    global.fetch = vi.fn().mockRejectedValueOnce(new Error('getaddrinfo ENOTFOUND smartenupinstitute.c'));

    const result = await validator.validateURL('http://smartenupinstitute.c');
    expect(result.valid).toBe(false);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.NETWORK_ERROR);
    expect(result.isError).toBe(true);
  });

  it('should reject URLs with incomplete domains', async () => {
    // URLs that pass format check but fail network validation
    global.fetch = vi.fn().mockRejectedValueOnce(new Error('getaddrinfo ENOTFOUND incomplete-domain.x'));

    const result = await validator.validateURL('https://incomplete-domain.x');
    expect(result.valid).toBe(false);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.NETWORK_ERROR);
  });

  it('should reject URLs to non-existent domains', async () => {
    global.fetch = vi.fn().mockRejectedValueOnce(new Error('getaddrinfo ENOTFOUND this-domain-definitely-does-not-exist-12345.com'));

    const result = await validator.validateURL('https://this-domain-definitely-does-not-exist-12345.com');
    expect(result.valid).toBe(false);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.NETWORK_ERROR);
    expect(result.message).toContain('Unable to reach URL');
  });
});

describe('URLValidator - Proxy Error Handling', () => {
  let validator;

  beforeEach(() => {
    validator = new URLValidator({
      proxyUrl: '/api/validate-url?action=tp_validate_url'
    });
  });

  it('should reject when proxy returns error field (DNS failure)', async () => {
    // Simulate proxy response for invalid domain like smartenupinstitute.c
    global.fetch = vi.fn().mockResolvedValueOnce({
      json: () => Promise.resolve({
        ok: false,
        status: 0,
        headers: {},
        error: 'cURL error: Could not resolve host: smartenupinstitute.c'
      })
    });

    const result = await validator.validateURL('http://smartenupinstitute.c');
    expect(result.valid).toBe(false);
    expect(result.isError).toBe(true);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.NETWORK_ERROR);
  });

  it('should reject when proxy returns status 0 (network failure)', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      json: () => Promise.resolve({
        ok: false,
        status: 0,
        headers: {}
      })
    });

    const result = await validator.validateURL('http://invalid-domain.x');
    expect(result.valid).toBe(false);
    expect(result.isError).toBe(true);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.NETWORK_ERROR);
    expect(result.message).toContain('Unable to reach');
  });

  it('should reject when proxy returns undefined status', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      json: () => Promise.resolve({
        ok: false,
        headers: {}
        // status is undefined
      })
    });

    const result = await validator.validateURL('http://missing-status.test');
    expect(result.valid).toBe(false);
    expect(result.errorType).toBe(URLValidator.ErrorTypes.NETWORK_ERROR);
  });

  it('should accept when proxy returns valid response', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      json: () => Promise.resolve({
        ok: true,
        status: 200,
        headers: {
          'content-type': 'text/html'
        }
      })
    });

    const result = await validator.validateURL('https://example.com');
    expect(result.valid).toBe(true);
  });
});

describe('URLValidator - applyValidationToElement()', () => {
  let validator;
  let inputElement;
  let messageElement;

  beforeEach(() => {
    validator = new URLValidator();
    inputElement = document.createElement('input');
    messageElement = document.createElement('div');
  });

  it('should apply error styling to input element', () => {
    const errorResult = validator.createErrorResult(
      URLValidator.ErrorTypes.INVALID_URL,
      'Invalid URL',
      URLValidator.BorderColors.ERROR
    );

    validator.applyValidationToElement(inputElement, errorResult, messageElement);

    // JSDOM converts hex to RGB
    expect(inputElement.style.borderColor).toMatch(/(#dc3545|rgb\(220,\s*53,\s*69\))/);
    expect(inputElement.style.borderWidth).toBe('2px');
    expect(messageElement.textContent).toBe('Invalid URL');
    expect(messageElement.className).toBe('error-message');
  });

  it('should apply warning styling to input element', () => {
    const warningResult = validator.createWarningResult(
      URLValidator.ErrorTypes.REDIRECT_PERMANENT,
      'Permanent redirect',
      URLValidator.BorderColors.WARNING
    );

    validator.applyValidationToElement(inputElement, warningResult, messageElement);

    // JSDOM converts hex to RGB
    expect(inputElement.style.borderColor).toMatch(/(#ffc107|rgb\(255,\s*193,\s*7\))/);
    expect(messageElement.className).toBe('warning-message');
  });

  it('should apply success styling to input element', () => {
    const successResult = validator.createSuccessResult(
      'Valid URL',
      URLValidator.BorderColors.SUCCESS
    );

    validator.applyValidationToElement(inputElement, successResult, messageElement);

    // JSDOM converts hex to RGB
    expect(inputElement.style.borderColor).toMatch(/(#28a745|rgb\(40,\s*167,\s*69\))/);
    expect(messageElement.className).toBe('success-message');
  });

  it('should set custom validity for errors', () => {
    const errorResult = validator.createErrorResult(
      URLValidator.ErrorTypes.INVALID_URL,
      'Invalid URL',
      URLValidator.BorderColors.ERROR
    );

    validator.applyValidationToElement(inputElement, errorResult);

    expect(inputElement.validationMessage).toBe('Invalid URL');
  });

  it('should clear custom validity for success', () => {
    const successResult = validator.createSuccessResult(
      'Valid URL',
      URLValidator.BorderColors.SUCCESS
    );

    validator.applyValidationToElement(inputElement, successResult);

    expect(inputElement.validationMessage).toBe('');
  });

  it('should work without message element', () => {
    const errorResult = validator.createErrorResult(
      URLValidator.ErrorTypes.INVALID_URL,
      'Invalid URL',
      URLValidator.BorderColors.ERROR
    );

    expect(() => {
      validator.applyValidationToElement(inputElement, errorResult);
    }).not.toThrow();

    // JSDOM converts hex to RGB
    expect(inputElement.style.borderColor).toMatch(/(#dc3545|rgb\(220,\s*53,\s*69\))/);
  });
});

describe('URLValidator - createDebouncedValidator()', () => {
  let validator;
  let inputElement;
  let messageElement;
  let callbackMock;

  beforeEach(() => {
    validator = new URLValidator();
    inputElement = document.createElement('input');
    messageElement = document.createElement('div');
    callbackMock = vi.fn();
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('should create a debounced function', () => {
    const debouncedValidator = validator.createDebouncedValidator(callbackMock, 500);
    expect(typeof debouncedValidator).toBe('function');
  });

  it('should not validate immediately', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 200,
      headers: {
        get: () => 'text/html'
      }
    });

    const debouncedValidator = validator.createDebouncedValidator(callbackMock, 500);
    debouncedValidator('https://example.com', inputElement, messageElement);

    expect(callbackMock).not.toHaveBeenCalled();
  });

  it('should validate after debounce delay', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 200,
      headers: {
        get: () => 'text/html'
      }
    });

    const debouncedValidator = validator.createDebouncedValidator(callbackMock, 500);
    debouncedValidator('https://example.com', inputElement, messageElement);

    vi.advanceTimersByTime(500);
    await vi.runAllTimersAsync();

    expect(callbackMock).toHaveBeenCalled();
  });

  it('should reset on multiple rapid calls', async () => {
    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      headers: {
        get: () => 'text/html'
      }
    });

    const debouncedValidator = validator.createDebouncedValidator(callbackMock, 500);

    debouncedValidator('https://example.com', inputElement, messageElement);
    vi.advanceTimersByTime(200);

    debouncedValidator('https://example.com/page', inputElement, messageElement);
    vi.advanceTimersByTime(200);

    debouncedValidator('https://example.com/page2', inputElement, messageElement);
    vi.advanceTimersByTime(500);
    await vi.runAllTimersAsync();

    // Should only be called once (for the last URL)
    expect(callbackMock).toHaveBeenCalledTimes(1);
  });

  it('should show error for invalid URL format', async () => {
    const debouncedValidator = validator.createDebouncedValidator(callbackMock, 500);
    debouncedValidator('not-a-url', inputElement, messageElement);

    // JSDOM converts hex to RGB
    expect(inputElement.style.borderColor).toMatch(/(#dc3545|rgb\(220,\s*53,\s*69\))/);
    expect(messageElement.textContent).toBe('Invalid URL format');
  });

  it('should reset styling to default while typing', async () => {
    const debouncedValidator = validator.createDebouncedValidator(callbackMock, 500);

    inputElement.style.borderColor = URLValidator.BorderColors.ERROR;
    messageElement.textContent = 'Previous error';

    debouncedValidator('https://example.com', inputElement, messageElement);

    // JSDOM converts hex to RGB
    expect(inputElement.style.borderColor).toMatch(/(#ced4da|rgb\(206,\s*212,\s*218\))/);
    expect(messageElement.textContent).toBe('');
  });
});

describe('URLValidator - Static Constants', () => {
  it('should have correct error types', () => {
    expect(URLValidator.ErrorTypes.INVALID_URL).toBe('invalid_url');
    expect(URLValidator.ErrorTypes.NOT_AVAILABLE).toBe('not_available');
    expect(URLValidator.ErrorTypes.PROTECTED).toBe('protected');
    expect(URLValidator.ErrorTypes.SSL_ERROR).toBe('ssl_error');
    expect(URLValidator.ErrorTypes.REDIRECT_PERMANENT).toBe('redirect_permanent');
    expect(URLValidator.ErrorTypes.REDIRECT_TEMPORARY).toBe('redirect_temporary');
    expect(URLValidator.ErrorTypes.INVALID_CONTENT_TYPE).toBe('invalid_content_type');
    expect(URLValidator.ErrorTypes.NETWORK_ERROR).toBe('network_error');
  });

  it('should have correct border colors', () => {
    expect(URLValidator.BorderColors.ERROR).toBe('#dc3545');
    expect(URLValidator.BorderColors.WARNING).toBe('#ffc107');
    expect(URLValidator.BorderColors.SUCCESS).toBe('#28a745');
    expect(URLValidator.BorderColors.DEFAULT).toBe('#ced4da');
  });

  it('should have correct status codes', () => {
    expect(URLValidator.StatusCodes.PERMANENT_REDIRECT).toEqual([301, 308]);
    expect(URLValidator.StatusCodes.TEMPORARY_REDIRECT).toEqual([302, 303, 307]);
    expect(URLValidator.StatusCodes.SUCCESS).toEqual([200, 201, 202, 203, 204, 205, 206]);
  });
});
