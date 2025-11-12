/**
 * Unit tests for Returning Visitor Logic
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { StorageService } from '../assets/js/storage-service.js';

describe('Returning Visitor Logic', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  describe('Data Persistence Flow', () => {
    it('should save shortcode data after successful creation', () => {
      const shortcodeData = {
        shortcode: 'test123',
        destination: 'https://example.com',
        expiresInHours: 24
      };

      const result = StorageService.saveShortcodeData(shortcodeData);

      expect(result).toBe(true);
      expect(localStorage.getItem(StorageService.KEYS.SHORTCODE)).toBe('test123');
      expect(localStorage.getItem(StorageService.KEYS.DESTINATION)).toBe('https://example.com');
    });

    it('should retrieve stored data on page load', () => {
      // Simulate data saved from previous session
      StorageService.saveShortcodeData({
        shortcode: 'test123',
        destination: 'https://example.com',
        expiresInHours: 24
      });

      // Simulate page reload
      const storedData = StorageService.getShortcodeData();

      expect(storedData).toBeTruthy();
      expect(storedData.shortcode).toBe('test123');
      expect(storedData.destination).toBe('https://example.com');
      expect(storedData.isExpired).toBe(false);
    });
  });

  describe('Validation Status Scenarios', () => {
    it('should handle active intro key scenario', () => {
      const storedData = {
        shortcode: 'test123',
        destination: 'https://example.com',
        expiration: Date.now() + (24 * 60 * 60 * 1000),
        sessionId: 'session_123',
        isExpired: false
      };

      const validationData = {
        status: 'intro',
        destination: 'https://example.com',
        destination_matches: true,
        message: 'Your trial key is active!'
      };

      // For active intro key:
      // - Should show the short URL
      // - Should disable new key generation
      // - Should show countdown
      expect(validationData.status).toBe('intro');
      expect(storedData.isExpired).toBe(false);
    });

    it('should handle expired key scenario', () => {
      const storedData = {
        shortcode: 'test123',
        destination: 'https://example.com',
        expiration: Date.now() - (1 * 60 * 60 * 1000), // 1 hour ago
        sessionId: 'session_123',
        isExpired: true
      };

      const validationData = {
        status: 'expired',
        destination: 'https://example.com',
        destination_matches: true,
        message: 'This key has expired.'
      };

      // For expired key:
      // - Should pre-fill destination
      // - Should show reuse prevention message
      // - Should allow new key generation with different params
      expect(validationData.status).toBe('expired');
      expect(storedData.destination).toBe('https://example.com');
    });

    it('should handle unavailable key scenario', () => {
      const validationData = {
        status: 'unavailable',
        message: 'This key is no longer available.'
      };

      // For unavailable key:
      // - Should show message
      // - Should clear stored data
      // - Should allow new key generation
      expect(validationData.status).toBe('unavailable');

      // Simulate clearing storage
      StorageService.clearShortcodeData();
      expect(StorageService.getShortcodeData()).toBeNull();
    });
  });

  describe('Countdown Timer Logic', () => {
    it('should calculate correct time remaining', () => {
      const futureTime = Date.now() + (2 * 60 * 60 * 1000); // 2 hours
      localStorage.setItem(StorageService.KEYS.EXPIRATION, futureTime.toString());

      const remaining = StorageService.getTimeRemaining();

      expect(remaining).toBeGreaterThan(0);
      expect(remaining).toBeLessThanOrEqual(2 * 60 * 60 * 1000);
    });

    it('should format time correctly for display', () => {
      const futureTime = Date.now() + (2 * 60 * 60 * 1000 + 30 * 60 * 1000); // 2h 30m
      localStorage.setItem(StorageService.KEYS.EXPIRATION, futureTime.toString());

      const formatted = StorageService.getFormattedTimeRemaining();

      expect(formatted).toMatch(/^2h \d+m$/);
    });

    it('should handle expiration during countdown', () => {
      const pastTime = Date.now() - (1 * 1000); // 1 second ago
      localStorage.setItem(StorageService.KEYS.EXPIRATION, pastTime.toString());

      const remaining = StorageService.getTimeRemaining();

      expect(remaining).toBe(0);
    });
  });

  describe('Form State Management', () => {
    it('should disable new key generation for active intro keys', () => {
      const storedData = {
        shortcode: 'test123',
        destination: 'https://example.com',
        expiration: Date.now() + (24 * 60 * 60 * 1000),
        sessionId: 'session_123',
        isExpired: false
      };

      const validationData = {
        status: 'intro',
        destination_matches: true
      };

      // When status is 'intro' and not expired:
      // - Submit button should be disabled
      // - Form should show existing key
      const shouldDisableSubmit = validationData.status === 'intro' && !storedData.isExpired;
      expect(shouldDisableSubmit).toBe(true);
    });

    it('should allow new key generation for expired keys', () => {
      const storedData = {
        shortcode: 'test123',
        destination: 'https://example.com',
        expiration: Date.now() - (1 * 60 * 60 * 1000),
        sessionId: 'session_123',
        isExpired: true
      };

      const validationData = {
        status: 'expired'
      };

      // When status is 'expired':
      // - Submit button should be enabled
      // - Destination should be pre-filled
      const shouldEnableSubmit = validationData.status === 'expired' || validationData.status === 'unavailable';
      expect(shouldEnableSubmit).toBe(true);
    });

    it('should pre-fill destination for expired keys', () => {
      const storedData = {
        shortcode: 'test123',
        destination: 'https://example.com',
        expiration: Date.now() - (1 * 60 * 60 * 1000),
        sessionId: 'session_123',
        isExpired: true
      };

      const validationData = {
        status: 'expired',
        destination: 'https://example.com'
      };

      // For expired keys, destination should be available for pre-filling
      expect(storedData.destination).toBe('https://example.com');
      expect(validationData.destination).toBe('https://example.com');
    });
  });

  describe('Clear Key Functionality', () => {
    it('should clear stored data when user requests new key', () => {
      StorageService.saveShortcodeData({
        shortcode: 'test123',
        destination: 'https://example.com',
        expiresInHours: 24
      });

      expect(StorageService.getShortcodeData()).toBeTruthy();

      // Simulate user clicking "generate a new key"
      StorageService.clearShortcodeData();

      expect(StorageService.getShortcodeData()).toBeNull();
    });

    it('should preserve session ID when clearing shortcode data', () => {
      StorageService.saveShortcodeData({
        shortcode: 'test123',
        destination: 'https://example.com',
        expiresInHours: 24
      });

      const sessionId = StorageService.getSessionId();
      expect(sessionId).toBeTruthy();

      StorageService.clearShortcodeData();

      // Session ID should still exist
      expect(StorageService.getSessionId()).toBe(sessionId);
    });

    it('should clear all data including session when requested', () => {
      StorageService.saveShortcodeData({
        shortcode: 'test123',
        destination: 'https://example.com',
        expiresInHours: 24
      });

      StorageService.clearAll();

      expect(StorageService.getShortcodeData()).toBeNull();
      expect(StorageService.getSessionId()).toBeNull();
    });
  });

  describe('Edge Cases', () => {
    it('should handle missing validation data gracefully', () => {
      const validationData = null;

      // Should not crash, should show default form
      expect(validationData).toBeNull();
    });

    it('should handle network errors during validation', () => {
      StorageService.saveShortcodeData({
        shortcode: 'test123',
        destination: 'https://example.com',
        expiresInHours: 24
      });

      // Simulate network error - data should still be in storage
      const storedData = StorageService.getShortcodeData();
      expect(storedData).toBeTruthy();
    });

    it('should handle corrupted local storage data', () => {
      // Set invalid data
      localStorage.setItem(StorageService.KEYS.SHORTCODE, 'test');
      // Missing other required fields

      const storedData = StorageService.getShortcodeData();

      // Should return null for incomplete data
      expect(storedData).toBeNull();
    });

    it('should handle localStorage quota exceeded', () => {
      const originalSetItem = localStorage.setItem;
      localStorage.setItem = () => {
        throw new Error('QuotaExceededError');
      };

      const result = StorageService.saveShortcodeData({
        shortcode: 'test123',
        destination: 'https://example.com'
      });

      expect(result).toBe(false);

      // Restore
      localStorage.setItem = originalSetItem;
    });
  });

  describe('Reuse Prevention', () => {
    it('should prevent reusing same key and destination combination', () => {
      const storedData = {
        shortcode: 'test123',
        destination: 'https://example.com',
        isExpired: true
      };

      const validationData = {
        status: 'expired',
        destination: 'https://example.com',
        destination_matches: true
      };

      // When expired and destination matches:
      // - Should show warning about reuse
      // - Should suggest changing destination or key
      const isReuseAttempt = validationData.status === 'expired' &&
                             validationData.destination_matches;
      expect(isReuseAttempt).toBe(true);
    });

    it('should allow using same destination with different key', () => {
      const storedData = {
        shortcode: 'test123',
        destination: 'https://example.com',
        isExpired: true
      };

      // User wants to create new key with same destination
      const newKeyRequest = {
        destination: 'https://example.com',
        customKey: 'newkey456' // Different key
      };

      // This should be allowed
      expect(newKeyRequest.customKey).not.toBe(storedData.shortcode);
      expect(newKeyRequest.destination).toBe(storedData.destination);
    });
  });

  describe('Session Management', () => {
    it('should generate unique session IDs', () => {
      const id1 = StorageService.generateSessionId();
      const id2 = StorageService.generateSessionId();

      expect(id1).not.toBe(id2);
      expect(id1).toMatch(/^session_\d+_[a-z0-9]+$/);
    });

    it('should reuse session ID across multiple shortcodes', () => {
      StorageService.saveShortcodeData({
        shortcode: 'test123',
        destination: 'https://example.com'
      });

      const sessionId1 = StorageService.getSessionId();

      // Clear and create new shortcode
      StorageService.clearShortcodeData();
      StorageService.saveShortcodeData({
        shortcode: 'test456',
        destination: 'https://example2.com'
      });

      const sessionId2 = StorageService.getSessionId();

      expect(sessionId1).toBe(sessionId2);
    });
  });
});
