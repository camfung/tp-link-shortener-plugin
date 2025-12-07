/**
 * Local Storage Service - Standalone version for WordPress
 * Manages storing and retrieving shortcode data for returning visitors
 */

(function(window) {
  'use strict';

  window.TPStorageService = {
    // Storage key constants
    KEYS: {
      SHORTCODE: 'tp_shortcode',
      DESTINATION: 'tp_destination',
      EXPIRATION: 'tp_expiration',
      SESSION_ID: 'tp_session_id',
      CREATED_AT: 'tp_created_at',
      UID: 'tp_uid',
      SCREENSHOT: 'tp_screenshot'
    },
    MAX_SCREENSHOT_BYTES: 5 * 1024 * 1024,

    /**
     * Generate a unique session ID
     */
    generateSessionId: function() {
      return 'session_' + Date.now() + '_' + Math.random().toString(36).substring(2, 15);
    },

    /**
     * Store shortcode data locally
     */
    saveShortcodeData: function(data) {
      try {
        var shortcode = data.shortcode;
        var destination = data.destination;
        var expiresInHours = data.expiresInHours || 24;
        var uid = data.uid;
        var screenshot = data.screenshot;

        if (!shortcode || !destination) {
          throw new Error('Shortcode and destination are required');
        }

        // Calculate expiration timestamp
        var now = Date.now();
        var expirationTime = now + (expiresInHours * 60 * 60 * 1000);

        // Get or generate session ID
        var sessionId = this.getSessionId();
        if (!sessionId) {
          sessionId = this.generateSessionId();
        }

        // Store all data
        localStorage.setItem(this.KEYS.SHORTCODE, shortcode);
        localStorage.setItem(this.KEYS.DESTINATION, destination);
        localStorage.setItem(this.KEYS.EXPIRATION, expirationTime.toString());
        localStorage.setItem(this.KEYS.SESSION_ID, sessionId);
        localStorage.setItem(this.KEYS.CREATED_AT, now.toString());

        // Store UID if provided
        if (uid) {
          localStorage.setItem(this.KEYS.UID, uid.toString());
        }

        if (screenshot) {
          var screenshotSizeBytes = this.getScreenshotSizeBytes(screenshot);
          if (screenshotSizeBytes > this.MAX_SCREENSHOT_BYTES) {
            console.warn('StorageService: Screenshot too large for localStorage', {
              screenshotSizeBytes: screenshotSizeBytes,
              maxBytes: this.MAX_SCREENSHOT_BYTES
            });
          } else {
            try {
              localStorage.setItem(this.KEYS.SCREENSHOT, screenshot);
              console.log('StorageService: Screenshot saved to localStorage', {
                length: screenshot.length,
                bytes: screenshotSizeBytes,
                preview: screenshot.substring(0, 50) + '...'
              });
            } catch (screenshotError) {
              console.error('Failed to save screenshot to localStorage (may be too large):', screenshotError);
            }
          }
        }

        return true;
      } catch (error) {
        console.error('Failed to save shortcode data:', error);
        return false;
      }
    },

    /**
     * Get stored shortcode data
     */
    getShortcodeData: function() {
      try {
        var shortcode = localStorage.getItem(this.KEYS.SHORTCODE);
        var destination = localStorage.getItem(this.KEYS.DESTINATION);
        var expiration = localStorage.getItem(this.KEYS.EXPIRATION);
        var sessionId = localStorage.getItem(this.KEYS.SESSION_ID);
        var createdAt = localStorage.getItem(this.KEYS.CREATED_AT);
        var uid = localStorage.getItem(this.KEYS.UID);
        var screenshot = localStorage.getItem(this.KEYS.SCREENSHOT);

        // Return null if essential data is missing
        if (!shortcode || !destination || !expiration) {
          return null;
        }

        return {
          shortcode: shortcode,
          destination: destination,
          expiration: parseInt(expiration, 10),
          sessionId: sessionId,
          createdAt: createdAt ? parseInt(createdAt, 10) : null,
          uid: uid,
          screenshot: screenshot,
          isExpired: this.isExpired()
        };
      } catch (error) {
        console.error('Failed to get shortcode data:', error);
        return null;
      }
    },

    /**
     * Get session ID
     */
    getSessionId: function() {
      try {
        return localStorage.getItem(this.KEYS.SESSION_ID);
      } catch (error) {
        return null;
      }
    },

    /**
     * Check if stored data is expired
     */
    isExpired: function() {
      try {
        var expiration = localStorage.getItem(this.KEYS.EXPIRATION);
        if (!expiration) {
          return true;
        }

        var expirationTime = parseInt(expiration, 10);
        return Date.now() > expirationTime;
      } catch (error) {
        return true;
      }
    },

    /**
     * Estimate screenshot size in bytes from a data URI
     */
    getScreenshotSizeBytes: function(screenshotDataUri) {
      try {
        var base64String = screenshotDataUri.split(',')[1] || screenshotDataUri;
        var paddingMatch = base64String.match(/=+$/) || [''];
        var padding = paddingMatch[0].length;
        return Math.max(0, (base64String.length * 3) / 4 - padding);
      } catch (error) {
        console.error('Failed to calculate screenshot size:', error);
        return 0;
      }
    },

    /**
     * Get time remaining until expiration
     */
    getTimeRemaining: function() {
      try {
        var expiration = localStorage.getItem(this.KEYS.EXPIRATION);
        if (!expiration) {
          return null;
        }

        var expirationTime = parseInt(expiration, 10);
        var remaining = expirationTime - Date.now();

        return remaining > 0 ? remaining : 0;
      } catch (error) {
        return null;
      }
    },

    /**
     * Format time remaining for display
     */
    getFormattedTimeRemaining: function() {
      var remaining = this.getTimeRemaining();
      if (remaining === null) {
        return null;
      }

      var hours = Math.floor(remaining / (1000 * 60 * 60));
      var minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
      var seconds = Math.floor((remaining % (1000 * 60)) / 1000);

      if (hours > 0) {
        return hours + 'h ' + minutes + 'm';
      } else if (minutes > 0) {
        return minutes + 'm ' + seconds + 's';
      } else {
        return seconds + 's';
      }
    },

    /**
     * Clear all stored data
     */
    clearShortcodeData: function() {
      try {
        localStorage.removeItem(this.KEYS.SHORTCODE);
        localStorage.removeItem(this.KEYS.DESTINATION);
        localStorage.removeItem(this.KEYS.EXPIRATION);
        localStorage.removeItem(this.KEYS.CREATED_AT);
        localStorage.removeItem(this.KEYS.SCREENSHOT);
        // Note: Keep session ID and UID for tracking purposes
        return true;
      } catch (error) {
        console.error('Failed to clear shortcode data:', error);
        return false;
      }
    },

    /**
     * Clear all data including session ID
     */
    clearAll: function() {
      try {
        var keys = Object.keys(this.KEYS);
        for (var i = 0; i < keys.length; i++) {
          localStorage.removeItem(this.KEYS[keys[i]]);
        }
        return true;
      } catch (error) {
        console.error('Failed to clear all data:', error);
        return false;
      }
    },

    /**
     * Check if storage is available
     */
    isAvailable: function() {
      try {
        var test = '__storage_test__';
        localStorage.setItem(test, test);
        localStorage.removeItem(test);
        return true;
      } catch (error) {
        return false;
      }
    }
  };

})(window);
