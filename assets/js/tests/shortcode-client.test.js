/**
 * Unit tests for ShortCodeClient
 * Uses Vitest for testing and mocking
 */

import { describe, test, expect, beforeEach, vi } from 'vitest';
import { ShortCodeClient, ShortCodeResponse, ShortCodeError } from '../shortcode-client.js';

// Mock fetch globally
global.fetch = vi.fn();

describe('ShortCodeClient', () => {
  let client;

  beforeEach(() => {
    client = new ShortCodeClient();
    fetch.mockClear();
  });

  describe('Constructor', () => {
    test('should use default base URL', () => {
      expect(client.baseUrl).toBe('https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev');
    });

    test('should accept custom base URL', () => {
      const customClient = new ShortCodeClient('https://custom.example.com');
      expect(customClient.baseUrl).toBe('https://custom.example.com');
    });

    test('should remove trailing slash from base URL', () => {
      const customClient = new ShortCodeClient('https://example.com/');
      expect(customClient.baseUrl).toBe('https://example.com');
    });

    test('should default to legacy endpoint', () => {
      expect(client.endpointPath).toBe('/generate-short-code');
    });
  });

  describe('Endpoint Type', () => {
    test('should set fast endpoint', () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
      expect(client.endpointPath).toBe('/generate-short-code/fast');
    });

    test('should set smart endpoint', () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);
      expect(client.endpointPath).toBe('/generate-short-code/smart');
    });

    test('should set AI endpoint', () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_AI);
      expect(client.endpointPath).toBe('/generate-short-code/ai');
    });

    test('should get full endpoint URL', () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);
      expect(client.getFullEndpoint()).toBe(
        'https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/generate-short-code/fast'
      );
    });
  });

  describe('generateShortCode - Success', () => {
    test('should generate short code with AI endpoint', async () => {
      const mockResponse = {
        message: 'Short code generated successfully',
        source: {
          short_code: 'pythontut',
          method: 'gemini-ai',
          was_modified: false,
          original_code: 'pythontut',
          url: 'https://docs.python.org/3/tutorial/index.html',
        },
        success: true,
      };

      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockResponse,
      });

      const result = await client.generateShortCode('https://docs.python.org/3/tutorial/index.html', 'trfc.link');

      expect(fetch).toHaveBeenCalledTimes(1);
      expect(fetch).toHaveBeenCalledWith(
        'https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/generate-short-code',
        expect.objectContaining({
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            url: 'https://docs.python.org/3/tutorial/index.html',
            domain: 'trfc.link',
          }),
        })
      );

      expect(result).toBeInstanceOf(ShortCodeResponse);
      expect(result.shortCode).toBe('pythontut');
      expect(result.method).toBe('gemini-ai');
      expect(result.wasModified).toBe(false);
      expect(result.originalCode).toBe('pythontut');
      expect(result.url).toBe('https://docs.python.org/3/tutorial/index.html');
      expect(result.isAI()).toBe(true);
    });

    test('should generate short code with Fast endpoint', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_FAST);

      const mockResponse = {
        message: 'Short code generated successfully',
        source: {
          short_code: 'pythontut',
          method: 'rule-based',
          was_modified: false,
          candidates: ['pythontut', 'python3', 'tutorial'],
        },
        success: true,
      };

      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockResponse,
      });

      const result = await client.generateShortCode('https://docs.python.org/3/tutorial/index.html');

      expect(result.shortCode).toBe('pythontut');
      expect(result.method).toBe('rule-based');
      expect(result.candidates).toEqual(['pythontut', 'python3', 'tutorial']);
      expect(result.isFast()).toBe(true);
      expect(result.originalCode).toBeNull();
      expect(result.url).toBeNull();
    });

    test('should generate short code with Smart endpoint', async () => {
      client.setEndpointType(ShortCodeClient.ENDPOINT_SMART);

      const mockResponse = {
        message: 'Short code generated successfully',
        source: {
          short_code: 'pythontut',
          method: 'nlp-comprehend',
          was_modified: false,
          candidates: ['pythontut', 'python3'],
          key_phrases: ['python tutorial', 'documentation'],
          entities: ['Python', 'tutorial'],
        },
        success: true,
      };

      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockResponse,
      });

      const result = await client.generateShortCode('https://docs.python.org/3/tutorial/index.html');

      expect(result.shortCode).toBe('pythontut');
      expect(result.method).toBe('nlp-comprehend');
      expect(result.candidates).toEqual(['pythontut', 'python3']);
      expect(result.keyPhrases).toEqual(['python tutorial', 'documentation']);
      expect(result.entities).toEqual(['Python', 'tutorial']);
      expect(result.isSmart()).toBe(true);
    });

    test('should handle collision (was_modified: true)', async () => {
      const mockResponse = {
        message: 'Short code generated successfully',
        source: {
          short_code: 'example2',
          method: 'gemini-ai',
          was_modified: true,
          original_code: 'example',
          url: 'https://example.com',
        },
        success: true,
      };

      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockResponse,
      });

      const result = await client.generateShortCode('https://example.com');

      expect(result.shortCode).toBe('example2');
      expect(result.wasModified).toBe(true);
      expect(result.originalCode).toBe('example');
    });

    test('should omit domain when not provided', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          message: 'Success',
          source: { short_code: 'test', method: 'gemini-ai', was_modified: false },
          success: true,
        }),
      });

      await client.generateShortCode('https://example.com');

      const callArgs = fetch.mock.calls[0][1];
      const body = JSON.parse(callArgs.body);
      expect(body).toEqual({ url: 'https://example.com' });
      expect(body.domain).toBeUndefined();
    });
  });

  describe('generateShortCode - Errors', () => {
    test('should throw validation error (400)', async () => {
      fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        statusText: 'Bad Request',
        json: async () => ({
          message: 'Invalid URL format',
          success: false,
        }),
      });

      try {
        await client.generateShortCode('not-a-url');
        throw new Error('Should have thrown');
      } catch (error) {
        expect(error).toBeInstanceOf(ShortCodeError);
        expect(error.message).toBe('Invalid URL format');
        expect(error.statusCode).toBe(400);
        expect(error.isValidationError()).toBe(true);
      }
    });

    test('should throw rate limit error (429)', async () => {
      fetch.mockResolvedValueOnce({
        ok: false,
        status: 429,
        statusText: 'Too Many Requests',
        json: async () => ({
          message: 'Rate limit exceeded',
          success: false,
        }),
      });

      try {
        await client.generateShortCode('https://example.com');
        throw new Error('Should have thrown');
      } catch (error) {
        expect(error).toBeInstanceOf(ShortCodeError);
        expect(error.statusCode).toBe(429);
        expect(error.isRateLimitError()).toBe(true);
      }
    });

    test('should throw server error (500)', async () => {
      fetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        statusText: 'Internal Server Error',
        json: async () => ({
          message: 'Server error occurred',
          success: false,
        }),
      });

      try {
        await client.generateShortCode('https://example.com');
        throw new Error('Should have thrown');
      } catch (error) {
        expect(error).toBeInstanceOf(ShortCodeError);
        expect(error.statusCode).toBe(500);
        expect(error.isServerError()).toBe(true);
      }
    });

    test('should throw error when success=false', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          message: 'Could not generate short code',
          success: false,
        }),
      });

      await expect(
        client.generateShortCode('https://example.com')
      ).rejects.toThrow('Could not generate short code');
    });

    test('should throw error when required fields missing', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          message: 'Success',
          source: {
            short_code: 'test',
            // Missing method and was_modified
          },
          success: true,
        }),
      });

      await expect(
        client.generateShortCode('https://example.com')
      ).rejects.toThrow('Response missing required fields');
    });

    test('should handle network errors', async () => {
      fetch.mockRejectedValueOnce(new Error('Network failure'));

      await expect(
        client.generateShortCode('https://example.com')
      ).rejects.toThrow(ShortCodeError);

      try {
        await client.generateShortCode('https://example.com');
      } catch (error) {
        expect(error.message).toContain('Network error');
        expect(error.isNetworkError()).toBe(true);
      }
    });

    test('should handle timeout', async () => {
      client.setTimeout(100); // 100ms timeout

      fetch.mockImplementationOnce(
        (url, options) => new Promise((resolve, reject) => {
          // Listen to abort signal
          options.signal.addEventListener('abort', () => {
            reject(new DOMException('The operation was aborted', 'AbortError'));
          });

          // Simulate long delay (longer than timeout)
          setTimeout(() => resolve({
            ok: true,
            status: 200,
            json: async () => ({
              success: true,
              source: { short_code: 'test', method: 'test', was_modified: false }
            }),
          }), 500);
        })
      );

      try {
        await client.generateShortCode('https://example.com');
        throw new Error('Should have thrown timeout error');
      } catch (error) {
        expect(error).toBeInstanceOf(ShortCodeError);
        expect(error.message).toContain('timeout');
        expect(error.isNetworkError()).toBe(true);
      }
    });
  });

  describe('ShortCodeResponse', () => {
    test('should correctly identify endpoint type', () => {
      const fastResponse = new ShortCodeResponse({
        source: { short_code: 'test', method: 'rule-based', was_modified: false },
        success: true,
      });
      expect(fastResponse.isFast()).toBe(true);
      expect(fastResponse.isSmart()).toBe(false);
      expect(fastResponse.isAI()).toBe(false);

      const smartResponse = new ShortCodeResponse({
        source: { short_code: 'test', method: 'nlp-comprehend', was_modified: false },
        success: true,
      });
      expect(smartResponse.isSmart()).toBe(true);
      expect(smartResponse.isFast()).toBe(false);
      expect(smartResponse.isAI()).toBe(false);

      const aiResponse = new ShortCodeResponse({
        source: { short_code: 'test', method: 'gemini-ai', was_modified: false },
        success: true,
      });
      expect(aiResponse.isAI()).toBe(true);
      expect(aiResponse.isFast()).toBe(false);
      expect(aiResponse.isSmart()).toBe(false);
    });
  });
});
