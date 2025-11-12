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
      CREATED_AT: 'tp_created_at'
    },

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
