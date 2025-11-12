/**
 * Unit tests for Storage Service
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { StorageService } from '../assets/js/storage-service.js';

describe('StorageService', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  describe('generateSessionId', () => {
    it('should generate a unique session ID', () => {
      const id1 = StorageService.generateSessionId();
      const id2 = StorageService.generateSessionId();

      expect(id1).toMatch(/^session_\d+_[a-z0-9]+$/);
      expect(id2).toMatch(/^session_\d+_[a-z0-9]+$/);
      expect(id1).not.toBe(id2);
    });
  });

  describe('saveShortcodeData', () => {
    it('should save shortcode data successfully', () => {
      const data = {
        shortcode: 'test123',
        destination: 'https://example.com',
        expiresInHours: 24
      };

      const result = StorageService.saveShortcodeData(data);

      expect(result).toBe(true);
      expect(localStorage.getItem(StorageService.KEYS.SHORTCODE)).toBe('test123');
      expect(localStorage.getItem(StorageService.KEYS.DESTINATION)).toBe('https://example.com');
      expect(localStorage.getItem(StorageService.KEYS.SESSION_ID)).toBeTruthy();
    });

    it('should use default expiration of 24 hours', () => {
      const data = {
        shortcode: 'test123',
        destination: 'https://example.com'
      };

      StorageService.saveShortcodeData(data);

      const expiration = parseInt(localStorage.getItem(StorageService.KEYS.EXPIRATION), 10);
      const now = Date.now();
      const expectedExpiration = now + (24 * 60 * 60 * 1000);

      // Allow 1 second tolerance for test execution time
      expect(expiration).toBeGreaterThanOrEqual(expectedExpiration - 1000);
      expect(expiration).toBeLessThanOrEqual(expectedExpiration + 1000);
    });

    it('should use custom expiration time', () => {
      const data = {
        shortcode: 'test123',
        destination: 'https://example.com',
        expiresInHours: 48
      };

      StorageService.saveShortcodeData(data);

      const expiration = parseInt(localStorage.getItem(StorageService.KEYS.EXPIRATION), 10);
      const now = Date.now();
      const expectedExpiration = now + (48 * 60 * 60 * 1000);

      expect(expiration).toBeGreaterThanOrEqual(expectedExpiration - 1000);
      expect(expiration).toBeLessThanOrEqual(expectedExpiration + 1000);
    });

    it('should reuse existing session ID', () => {
      const existingSessionId = 'session_existing_123';
      localStorage.setItem(StorageService.KEYS.SESSION_ID, existingSessionId);

      const data = {
        shortcode: 'test123',
        destination: 'https://example.com'
      };

      StorageService.saveShortcodeData(data);

      expect(localStorage.getItem(StorageService.KEYS.SESSION_ID)).toBe(existingSessionId);
    });

    it('should return false if shortcode is missing', () => {
      const data = {
        destination: 'https://example.com'
      };

      const result = StorageService.saveShortcodeData(data);

      expect(result).toBe(false);
    });

    it('should return false if destination is missing', () => {
      const data = {
        shortcode: 'test123'
      };

      const result = StorageService.saveShortcodeData(data);

      expect(result).toBe(false);
    });
  });

  describe('getShortcodeData', () => {
    it('should retrieve saved shortcode data', () => {
      const data = {
        shortcode: 'test123',
        destination: 'https://example.com',
        expiresInHours: 24
      };

      StorageService.saveShortcodeData(data);
      const retrieved = StorageService.getShortcodeData();

      expect(retrieved).toBeTruthy();
      expect(retrieved.shortcode).toBe('test123');
      expect(retrieved.destination).toBe('https://example.com');
      expect(retrieved.sessionId).toBeTruthy();
      expect(retrieved.isExpired).toBe(false);
    });

    it('should return null if no data is stored', () => {
      const retrieved = StorageService.getShortcodeData();

      expect(retrieved).toBeNull();
    });

    it('should return null if essential data is missing', () => {
      localStorage.setItem(StorageService.KEYS.SHORTCODE, 'test123');
      // Missing destination and expiration

      const retrieved = StorageService.getShortcodeData();

      expect(retrieved).toBeNull();
    });

    it('should include isExpired flag', () => {
      const data = {
        shortcode: 'test123',
        destination: 'https://example.com',
        expiresInHours: 24
      };

      StorageService.saveShortcodeData(data);
      const retrieved = StorageService.getShortcodeData();

      expect(retrieved.isExpired).toBeDefined();
      expect(typeof retrieved.isExpired).toBe('boolean');
    });
  });

  describe('getSessionId', () => {
    it('should return session ID if exists', () => {
      const sessionId = 'session_test_123';
      localStorage.setItem(StorageService.KEYS.SESSION_ID, sessionId);

      const retrieved = StorageService.getSessionId();

      expect(retrieved).toBe(sessionId);
    });

    it('should return null if session ID does not exist', () => {
      const retrieved = StorageService.getSessionId();

      expect(retrieved).toBeNull();
    });
  });

  describe('isExpired', () => {
    it('should return false for non-expired data', () => {
      const futureTime = Date.now() + (24 * 60 * 60 * 1000);
      localStorage.setItem(StorageService.KEYS.EXPIRATION, futureTime.toString());

      const result = StorageService.isExpired();

      expect(result).toBe(false);
    });

    it('should return true for expired data', () => {
      const pastTime = Date.now() - (1 * 60 * 60 * 1000);
      localStorage.setItem(StorageService.KEYS.EXPIRATION, pastTime.toString());

      const result = StorageService.isExpired();

      expect(result).toBe(true);
    });

    it('should return true if no expiration is set', () => {
      const result = StorageService.isExpired();

      expect(result).toBe(true);
    });
  });

  describe('getTimeRemaining', () => {
    it('should return time remaining in milliseconds', () => {
      const futureTime = Date.now() + (2 * 60 * 60 * 1000); // 2 hours
      localStorage.setItem(StorageService.KEYS.EXPIRATION, futureTime.toString());

      const remaining = StorageService.getTimeRemaining();

      expect(remaining).toBeGreaterThan(0);
      expect(remaining).toBeLessThanOrEqual(2 * 60 * 60 * 1000);
    });

    it('should return 0 for expired data', () => {
      const pastTime = Date.now() - (1 * 60 * 60 * 1000);
      localStorage.setItem(StorageService.KEYS.EXPIRATION, pastTime.toString());

      const remaining = StorageService.getTimeRemaining();

      expect(remaining).toBe(0);
    });

    it('should return null if no expiration is set', () => {
      const remaining = StorageService.getTimeRemaining();

      expect(remaining).toBeNull();
    });
  });

  describe('getFormattedTimeRemaining', () => {
    it('should format hours and minutes', () => {
      const futureTime = Date.now() + (2 * 60 * 60 * 1000 + 30 * 60 * 1000); // 2h 30m
      localStorage.setItem(StorageService.KEYS.EXPIRATION, futureTime.toString());

      const formatted = StorageService.getFormattedTimeRemaining();

      expect(formatted).toMatch(/^2h \d+m$/);
    });

    it('should format minutes and seconds when less than an hour', () => {
      const futureTime = Date.now() + (30 * 60 * 1000 + 15 * 1000); // 30m 15s
      localStorage.setItem(StorageService.KEYS.EXPIRATION, futureTime.toString());

      const formatted = StorageService.getFormattedTimeRemaining();

      expect(formatted).toMatch(/^\d+m \d+s$/);
    });

    it('should format seconds when less than a minute', () => {
      const futureTime = Date.now() + (30 * 1000); // 30s
      localStorage.setItem(StorageService.KEYS.EXPIRATION, futureTime.toString());

      const formatted = StorageService.getFormattedTimeRemaining();

      expect(formatted).toMatch(/^\d+s$/);
    });

    it('should return null if no expiration is set', () => {
      const formatted = StorageService.getFormattedTimeRemaining();

      expect(formatted).toBeNull();
    });
  });

  describe('clearShortcodeData', () => {
    it('should clear shortcode data but keep session ID', () => {
      const data = {
        shortcode: 'test123',
        destination: 'https://example.com'
      };

      StorageService.saveShortcodeData(data);
      const sessionId = StorageService.getSessionId();

      const result = StorageService.clearShortcodeData();

      expect(result).toBe(true);
      expect(localStorage.getItem(StorageService.KEYS.SHORTCODE)).toBeNull();
      expect(localStorage.getItem(StorageService.KEYS.DESTINATION)).toBeNull();
      expect(localStorage.getItem(StorageService.KEYS.EXPIRATION)).toBeNull();
      expect(localStorage.getItem(StorageService.KEYS.SESSION_ID)).toBe(sessionId);
    });
  });

  describe('clearAll', () => {
    it('should clear all data including session ID', () => {
      const data = {
        shortcode: 'test123',
        destination: 'https://example.com'
      };

      StorageService.saveShortcodeData(data);

      const result = StorageService.clearAll();

      expect(result).toBe(true);
      expect(localStorage.getItem(StorageService.KEYS.SHORTCODE)).toBeNull();
      expect(localStorage.getItem(StorageService.KEYS.DESTINATION)).toBeNull();
      expect(localStorage.getItem(StorageService.KEYS.EXPIRATION)).toBeNull();
      expect(localStorage.getItem(StorageService.KEYS.SESSION_ID)).toBeNull();
      expect(localStorage.getItem(StorageService.KEYS.CREATED_AT)).toBeNull();
    });
  });

  describe('isAvailable', () => {
    it('should return true when localStorage is available', () => {
      const result = StorageService.isAvailable();

      expect(result).toBe(true);
    });

    it('should return false when localStorage throws error', () => {
      const originalSetItem = localStorage.setItem;
      localStorage.setItem = () => {
        throw new Error('QuotaExceededError');
      };

      const result = StorageService.isAvailable();

      expect(result).toBe(false);

      // Restore
      localStorage.setItem = originalSetItem;
    });
  });
});
