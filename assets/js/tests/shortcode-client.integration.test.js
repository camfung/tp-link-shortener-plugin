/**
 * Integration tests for ShortCodeClient
 * Tests against live API
 *
 * Run with: RUN_INTEGRATION=1 npm run test:integration
 */

import { describe, test, expect, beforeEach } from 'vitest';
import { ShortCodeClient, ShortCodeError } from '../shortcode-client.js';

// Skip these tests unless RUN_INTEGRATION=1 is set
const describeIntegration = process.env.RUN_INTEGRATION === '1' ? describe : describe.skip;

describeIntegration('ShortCodeClient - Integration Tests', () => {
  let client;
  const testUrl = 'https://docs.python.org/3/tutorial/index.html';
  const testDomain = 'trfc.link';

  beforeEach(() => {
    client = new ShortCodeClient();
    client.setTimeout(15000); // 15 second timeout for integration tests
  });

  describe('Fast Endpoint', () => {
    test('should generate short code using rule-based method', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);

      const result = await client.generateShortCode(testUrl, testDomain);

      expect(result.shortCode).toBeTruthy();
      expect(result.method).toBe('rule-based');
      expect(typeof result.wasModified).toBe('boolean');
      expect(result.message).toBeTruthy();

      // Fast endpoint should have candidates
      if (result.candidates) {
        expect(Array.isArray(result.candidates)).toBe(true);
        expect(result.candidates.length).toBeGreaterThan(0);
      }

      // Fast endpoint should not have these fields
      expect(result.keyPhrases).toBeNull();
      expect(result.entities).toBeNull();

      expect(result.isFast()).toBe(true);
    }, 10000); // 10 second timeout
  });

  describe('Smart Endpoint', () => {
    test('should generate short code using NLP method', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);

      const result = await client.generateShortCode(testUrl, testDomain);

      expect(result.shortCode).toBeTruthy();
      expect(result.method).toBe('nlp-comprehend');
      expect(typeof result.wasModified).toBe('boolean');
      expect(result.message).toBeTruthy();

      // Smart endpoint may have candidates, key_phrases, entities
      if (result.candidates) {
        expect(Array.isArray(result.candidates)).toBe(true);
      }
      if (result.keyPhrases) {
        expect(Array.isArray(result.keyPhrases)).toBe(true);
      }
      if (result.entities) {
        expect(Array.isArray(result.entities)).toBe(true);
      }

      expect(result.isSmart()).toBe(true);
    }, 10000); // 10 second timeout
  });

  describe('AI Endpoint', () => {
    test('should generate short code using Gemini AI', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_AI);

      try {
        const result = await client.generateShortCode(testUrl, testDomain);

        expect(result.shortCode).toBeTruthy();
        expect(result.method).toBe('gemini-ai');
        expect(typeof result.wasModified).toBe('boolean');
        expect(result.message).toBeTruthy();

        // AI endpoint should have original_code and url
        expect(result.originalCode).toBeTruthy();
        expect(result.url).toBe(testUrl);

        // AI endpoint should not have these fields
        expect(result.candidates).toBeNull();
        expect(result.keyPhrases).toBeNull();
        expect(result.entities).toBeNull();

        expect(result.isAI()).toBe(true);
      } catch (error) {
        // Handle Gemini quota errors gracefully
        if (error instanceof ShortCodeError && error.isRateLimitError()) {
          console.warn('Gemini API quota exceeded - test skipped');
          return;
        }
        throw error;
      }
    }, 15000); // 15 second timeout for AI
  });

  describe('Legacy Endpoint', () => {
    test('should generate short code using legacy endpoint', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_LEGACY);

      try {
        const result = await client.generateShortCode(testUrl, testDomain);

        expect(result.shortCode).toBeTruthy();
        expect(result.method).toBe('gemini-ai');
        expect(result.url).toBe(testUrl);
      } catch (error) {
        // Handle Gemini quota errors gracefully
        if (error instanceof ShortCodeError && error.isRateLimitError()) {
          console.warn('Gemini API quota exceeded - test skipped');
          return;
        }
        throw error;
      }
    }, 15000); // 15 second timeout
  });

  describe('Request without domain', () => {
    test('should successfully generate without domain parameter', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);

      const result = await client.generateShortCode(testUrl);

      expect(result.shortCode).toBeTruthy();
      expect(result.method).toBe('rule-based');
    }, 10000);
  });

  describe('Error Handling', () => {
    test('should handle invalid URL', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);

      try {
        await client.generateShortCode('not-a-url');
        fail('Should have thrown an error');
      } catch (error) {
        expect(error).toBeInstanceOf(ShortCodeError);
        expect(error.isValidationError()).toBe(true);
      }
    }, 10000);

    test('should handle non-existent URL', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);

      try {
        await client.generateShortCode('https://this-domain-definitely-does-not-exist-12345.com');
        // May or may not fail depending on backend behavior
      } catch (error) {
        expect(error).toBeInstanceOf(ShortCodeError);
      }
    }, 10000);
  });

  describe('Performance', () => {
    test('fast endpoint should respond quickly', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);

      const start = Date.now();
      await client.generateShortCode(testUrl);
      const duration = Date.now() - start;

      // Should complete within 3 seconds (documented as ~500ms)
      expect(duration).toBeLessThan(3000);
    }, 10000);

    test('smart endpoint should respond reasonably quickly', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);

      const start = Date.now();
      await client.generateShortCode(testUrl);
      const duration = Date.now() - start;

      // Should complete within 5 seconds (documented as ~800ms)
      expect(duration).toBeLessThan(5000);
    }, 10000);
  });

  describe('Multiple requests', () => {
    test('should handle multiple sequential requests', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);

      const result1 = await client.generateShortCode('https://example.com');
      const result2 = await client.generateShortCode('https://example.org');

      expect(result1.shortCode).toBeTruthy();
      expect(result2.shortCode).toBeTruthy();
    }, 15000);

    test('should handle switching between endpoints', async () => {
      // Fast endpoint
      client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
      const fastResult = await client.generateShortCode(testUrl);
      expect(fastResult.isFast()).toBe(true);

      // Smart endpoint
      client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);
      const smartResult = await client.generateShortCode(testUrl);
      expect(smartResult.isSmart()).toBe(true);
    }, 20000);
  });
});
