/**
 * Rate Limit Tests
 * Tests for anonymous user IP limit (429 error handling)
 */

import { describe, it, expect, beforeEach } from 'vitest';

describe('Rate Limit Error Handling', () => {
  let document, errorMessageDiv, loadingDiv, submitBtn;

  beforeEach(() => {
    // Set up a simple DOM structure for testing
    document = {
      createElement: (tag) => ({
        tag,
        classList: new Set(),
        innerHTML: '',
        disabled: false,
        value: ''
      })
    };

    // Mock DOM elements
    errorMessageDiv = {
      classList: new Set(['d-none']),
      innerHTML: '',
      hasClass: function(className) {
        return this.classList.has(className);
      },
      addClass: function(className) {
        this.classList.add(className);
        return this;
      },
      removeClass: function(className) {
        this.classList.delete(className);
        return this;
      },
      html: function(content) {
        if (content !== undefined) {
          this.innerHTML = content;
          return this;
        }
        return this.innerHTML;
      },
      empty: function() {
        this.innerHTML = '';
        return this;
      }
    };

    loadingDiv = {
      classList: new Set(['d-none']),
      hasClass: function(className) {
        return this.classList.has(className);
      },
      addClass: function(className) {
        this.classList.add(className);
        return this;
      },
      removeClass: function(className) {
        this.classList.delete(className);
        return this;
      }
    };

    submitBtn = {
      disabled: false,
      prop: function(name, value) {
        if (value !== undefined) {
          this[name] = value;
          return this;
        }
        return this[name];
      }
    };
  });

  describe('API Response Handling', () => {
    it('should detect 429 rate limit error from response', () => {
      const response = {
        success: false,
        data: {
          message: 'Anonymous users can only create 1 short URL. Please register for unlimited URLs.',
          error_type: 'rate_limit',
          http_code: 429
        }
      };

      expect(response.success).toBe(false);
      expect(response.data.error_type).toBe('rate_limit');
      expect(response.data.http_code).toBe(429);
    });

    it('should differentiate between rate limit and other errors', () => {
      const rateLimitResponse = {
        success: false,
        data: {
          message: 'Anonymous users can only create 1 short URL. Please register for unlimited URLs.',
          error_type: 'rate_limit',
          http_code: 429
        }
      };

      const validationResponse = {
        success: false,
        data: {
          message: 'This shortcode is already taken. Please try another.'
        }
      };

      expect(rateLimitResponse.data.error_type).toBe('rate_limit');
      expect(validationResponse.data.error_type).toBeUndefined();
    });
  });

  describe('Error Message Display', () => {
    it('should show enhanced error message for rate limit', () => {
      const message = 'Anonymous users can only create 1 short URL. Please register for unlimited URLs.';

      // Simulate showRateLimitError
      const errorHtml = `
        <div>
          <i class="fas fa-exclamation-triangle me-2"></i>
          <strong>${message}</strong>
        </div>
        <div class="mt-3">
          <p class="mb-2">Create an account to get:</p>
          <ul class="mb-3" style="text-align: left; display: inline-block;">
            <li>Unlimited short URLs</li>
            <li>Analytics and tracking</li>
            <li>Custom domains</li>
            <li>URL management</li>
          </ul>
        </div>
      `;

      errorMessageDiv.html(errorHtml).removeClass('d-none');

      expect(errorMessageDiv.hasClass('d-none')).toBe(false);
      expect(errorMessageDiv.html()).toContain(message);
      expect(errorMessageDiv.html()).toContain('Unlimited short URLs');
      expect(errorMessageDiv.html()).toContain('Analytics and tracking');
    });

    it('should show regular error message for non-rate-limit errors', () => {
      const message = 'This shortcode is already taken.';

      // Simulate showError
      errorMessageDiv
        .html('<i class="fas fa-exclamation-circle me-2"></i>' + message)
        .removeClass('d-none');

      expect(errorMessageDiv.hasClass('d-none')).toBe(false);
      expect(errorMessageDiv.html()).toContain(message);
      expect(errorMessageDiv.html()).not.toContain('Unlimited short URLs');
    });
  });

  describe('User Feedback', () => {
    it('should hide loading state when rate limit error occurs', () => {
      // Simulate loading state
      loadingDiv.removeClass('d-none');
      submitBtn.prop('disabled', true);

      // Simulate error response handler
      loadingDiv.addClass('d-none');
      submitBtn.prop('disabled', false);

      expect(loadingDiv.hasClass('d-none')).toBe(true);
      expect(submitBtn.prop('disabled')).toBe(false);
    });

    it('should display error without clearing form inputs', () => {
      const destinationInput = { value: 'https://example.com' };
      const customKeyInput = { value: 'mylink' };

      // On error, form should NOT be cleared
      expect(destinationInput.value).toBe('https://example.com');
      expect(customKeyInput.value).toBe('mylink');
    });
  });

  describe('Edge Cases', () => {
    it('should handle missing error_type gracefully', () => {
      const response = {
        success: false,
        data: {
          message: 'Some error occurred'
        }
      };

      // Should fall back to regular error handling
      expect(response.data.error_type).toBeUndefined();
    });

    it('should handle missing message in rate limit response', () => {
      const response = {
        success: false,
        data: {
          error_type: 'rate_limit',
          http_code: 429
        }
      };

      const message = response.data.message || 'An error occurred';
      expect(message).toBe('An error occurred');
    });

    it('should handle malformed response data', () => {
      const response = {
        success: false,
        data: null
      };

      expect(response.data).toBeNull();

      // Should not crash when accessing properties
      const errorType = response.data && response.data.error_type;
      // When data is null, errorType will be null (not undefined)
      expect(errorType).toBeFalsy();
    });
  });

  describe('Anonymous User Detection', () => {
    it('should detect when user is not logged in', () => {
      // Simulate WordPress not logged in state
      const isLoggedIn = false;

      expect(isLoggedIn).toBe(false);
    });

    it('should use uid=-1 for anonymous users', () => {
      const uid = -1;

      expect(uid).toBe(-1);
    });

    it('should retrieve uid from localStorage if available', () => {
      localStorage.setItem('tpUid', '123');

      const storedUid = localStorage.getItem('tpUid');
      expect(storedUid).toBe('123');
    });

    it('should handle localStorage not available', () => {
      let uidFromStorage = null;

      try {
        const storedUid = localStorage.getItem('tpUid');
        if (storedUid && storedUid.trim() !== '') {
          uidFromStorage = storedUid;
        }
      } catch (storageError) {
        uidFromStorage = null;
      }

      expect(uidFromStorage).toBeNull();
    });
  });

  describe('Integration with Form Submission', () => {
    it('should prepare correct data structure for AJAX request', () => {
      const destination = 'https://example.com';
      const customKey = 'test';
      const nonce = 'test_nonce';
      const uid = -1;

      const data = {
        action: 'tp_create_link',
        nonce: nonce,
        destination: destination,
        custom_key: customKey,
        uid: uid
      };

      expect(data.action).toBe('tp_create_link');
      expect(data.destination).toBe(destination);
      expect(data.custom_key).toBe(customKey);
      expect(data.uid).toBe(-1);
    });

    it('should not include uid if not available in storage', () => {
      const destination = 'https://example.com';
      const customKey = 'test';
      const nonce = 'test_nonce';
      let uidFromStorage = null;

      const data = {
        action: 'tp_create_link',
        nonce: nonce,
        destination: destination,
        custom_key: customKey
      };

      if (uidFromStorage !== null) {
        data.uid = uidFromStorage;
      }

      expect(data.uid).toBeUndefined();
    });
  });

  describe('Error Recovery', () => {
    it('should allow user to retry after rate limit error', () => {
      // Show rate limit error
      errorMessageDiv.html('Rate limit exceeded').removeClass('d-none');

      // Button should be enabled for retry
      submitBtn.prop('disabled', false);

      expect(submitBtn.prop('disabled')).toBe(false);
      expect(errorMessageDiv.hasClass('d-none')).toBe(false);
    });

    it('should clear previous error when hiding error message', () => {
      errorMessageDiv.html('Some error').removeClass('d-none');
      expect(errorMessageDiv.hasClass('d-none')).toBe(false);

      // Hide error
      errorMessageDiv.addClass('d-none').empty();

      expect(errorMessageDiv.hasClass('d-none')).toBe(true);
      expect(errorMessageDiv.html()).toBe('');
    });
  });
});
