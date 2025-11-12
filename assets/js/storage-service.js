/**
 * Local Storage Service
 * Manages storing and retrieving shortcode data for returning visitors
 */

export const StorageService = {
  // Storage key constants
  KEYS: {
    SHORTCODE: 'tp_shortcode',
    DESTINATION: 'tp_destination',
    EXPIRATION: 'tp_expiration',
    SESSION_ID: 'tp_session_id',
    CREATED_AT: 'tp_created_at',
    UID: 'tp_uid'
  },

  /**
   * Generate a unique session ID
   */
  generateSessionId() {
    return 'session_' + Date.now() + '_' + Math.random().toString(36).substring(2, 15);
  },

  /**
   * Store shortcode data locally
   * @param {Object} data - Data to store
   * @param {string} data.shortcode - The short key/code
   * @param {string} data.destination - The destination URL
   * @param {number} data.expiresInHours - Hours until expiration (default 24)
   * @param {string} data.uid - User ID (optional)
   */
  saveShortcodeData(data) {
    try {
      const { shortcode, destination, expiresInHours = 24, uid } = data;

      if (!shortcode || !destination) {
        throw new Error('Shortcode and destination are required');
      }

      // Calculate expiration timestamp
      const now = Date.now();
      const expirationTime = now + (expiresInHours * 60 * 60 * 1000);

      // Get or generate session ID
      let sessionId = this.getSessionId();
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

      return true;
    } catch (error) {
      console.error('Failed to save shortcode data:', error);
      return false;
    }
  },

  /**
   * Get stored shortcode data
   * @returns {Object|null} Stored data or null if not found
   */
  getShortcodeData() {
    try {
      const shortcode = localStorage.getItem(this.KEYS.SHORTCODE);
      const destination = localStorage.getItem(this.KEYS.DESTINATION);
      const expiration = localStorage.getItem(this.KEYS.EXPIRATION);
      const sessionId = localStorage.getItem(this.KEYS.SESSION_ID);
      const createdAt = localStorage.getItem(this.KEYS.CREATED_AT);
      const uid = localStorage.getItem(this.KEYS.UID);

      // Return null if essential data is missing
      if (!shortcode || !destination || !expiration) {
        return null;
      }

      return {
        shortcode,
        destination,
        expiration: parseInt(expiration, 10),
        sessionId,
        createdAt: createdAt ? parseInt(createdAt, 10) : null,
        uid,
        isExpired: this.isExpired()
      };
    } catch (error) {
      console.error('Failed to get shortcode data:', error);
      return null;
    }
  },

  /**
   * Get session ID
   * @returns {string|null}
   */
  getSessionId() {
    try {
      return localStorage.getItem(this.KEYS.SESSION_ID);
    } catch (error) {
      return null;
    }
  },

  /**
   * Check if stored data is expired
   * @returns {boolean}
   */
  isExpired() {
    try {
      const expiration = localStorage.getItem(this.KEYS.EXPIRATION);
      if (!expiration) {
        return true;
      }

      const expirationTime = parseInt(expiration, 10);
      return Date.now() > expirationTime;
    } catch (error) {
      return true;
    }
  },

  /**
   * Get time remaining until expiration
   * @returns {number|null} Milliseconds remaining or null if not set/expired
   */
  getTimeRemaining() {
    try {
      const expiration = localStorage.getItem(this.KEYS.EXPIRATION);
      if (!expiration) {
        return null;
      }

      const expirationTime = parseInt(expiration, 10);
      const remaining = expirationTime - Date.now();

      return remaining > 0 ? remaining : 0;
    } catch (error) {
      return null;
    }
  },

  /**
   * Format time remaining for display
   * @returns {string|null} Formatted time string or null
   */
  getFormattedTimeRemaining() {
    const remaining = this.getTimeRemaining();
    if (remaining === null) {
      return null;
    }

    const hours = Math.floor(remaining / (1000 * 60 * 60));
    const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((remaining % (1000 * 60)) / 1000);

    if (hours > 0) {
      return `${hours}h ${minutes}m`;
    } else if (minutes > 0) {
      return `${minutes}m ${seconds}s`;
    } else {
      return `${seconds}s`;
    }
  },

  /**
   * Clear all stored data
   */
  clearShortcodeData() {
    try {
      localStorage.removeItem(this.KEYS.SHORTCODE);
      localStorage.removeItem(this.KEYS.DESTINATION);
      localStorage.removeItem(this.KEYS.EXPIRATION);
      localStorage.removeItem(this.KEYS.CREATED_AT);
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
  clearAll() {
    try {
      Object.values(this.KEYS).forEach(key => {
        localStorage.removeItem(key);
      });
      return true;
    } catch (error) {
      console.error('Failed to clear all data:', error);
      return false;
    }
  },

  /**
   * Check if storage is available
   * @returns {boolean}
   */
  isAvailable() {
    try {
      const test = '__storage_test__';
      localStorage.setItem(test, test);
      localStorage.removeItem(test);
      return true;
    } catch (error) {
      return false;
    }
  }
};

// For CommonJS/WordPress environments
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { StorageService };
}
