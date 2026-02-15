# Testing Patterns

**Analysis Date:** 2026-02-15

## Test Framework

**Runner:**
- JavaScript: Vitest 3.2.4
- PHP: PHPUnit 9.5+
- Config: `vitest.config.js` at root, `phpunit.xml` at root

**Assertion Library:**
- JavaScript: Vitest (built-in expect)
- PHP: PHPUnit\Framework\TestCase assertions

**Run Commands:**
```bash
npm test                    # Run all JavaScript tests with Vitest
npm run test:ui             # Run tests in interactive UI mode
npm run test:coverage       # Generate coverage reports (text, json, html)

# PHP tests (not in package.json, assumed vendor/bin/phpunit):
vendor/bin/phpunit          # Run all PHP tests
vendor/bin/phpunit tests/Unit  # Run only unit tests
```

## Test File Organization

**Location:**

**JavaScript:**
- Co-located with source: `assets/js/url-validator.test.js` lives alongside `assets/js/url-validator.js`
- Separate directory: `tests/` directory for integration tests
  - `tests/rate-limit.test.js`
  - `tests/returning-visitor.test.js`
  - `tests/storage-service.test.js`

**PHP:**
- Separate `tests/` directory structure matching `includes/` namespace
  - `tests/Unit/TrafficPortal/CreateMapRequestTest.php`
  - `tests/Unit/TrafficPortal/PaginatedMapItemsResponseTest.php`
  - `tests/Unit/TrafficPortal/CreateMapResponseTest.php`
  - `tests/Unit/SnapCaptureClientTest.php`
  - `tests/Integration/` directory for integration tests (seen in phpunit.xml)
  - `tests/e2e/` directory for end-to-end tests (Python-based Selenium)

**Naming:**
- JavaScript: `{filename}.test.js` suffix
- PHP: `{ClassName}Test.php` suffix

**Structure:**
```
tests/
├── e2e/                          # End-to-end tests (Python/Selenium)
├── Integration/                  # PHP integration tests
├── Unit/
│   ├── SnapCaptureClientTest.php
│   └── TrafficPortal/
│       ├── CreateMapRequestTest.php
│       ├── CreateMapResponseTest.php
│       ├── PaginatedMapItemsResponseTest.php
│       └── MapItemUsageTest.php
├── rate-limit.test.js            # JavaScript unit/integration test
├── returning-visitor.test.js      # JavaScript feature test
├── storage-service.test.js        # JavaScript unit test
└── setup.js                       # Vitest setup file
```

## Test Structure

**JavaScript Suite Organization:**

Hierarchical describe blocks following feature area:
```javascript
describe('Rate Limit Error Handling', () => {
  let document, errorMessageDiv, loadingDiv, submitBtn;

  beforeEach(() => {
    // Set up test fixtures
    document = { createElement: (tag) => ({ tag, classList: new Set() }) };
    errorMessageDiv = { classList: new Set(['d-none']), ... };
  });

  describe('API Response Handling', () => {
    it('should detect 429 rate limit error from response', () => {
      const response = { success: false, data: { error_type: 'rate_limit', http_code: 429 } };
      expect(response.success).toBe(false);
      expect(response.data.error_type).toBe('rate_limit');
    });
  });

  describe('Error Message Display', () => {
    it('should show enhanced error message for rate limit', () => {
      const errorHtml = `<div>...</div>`;
      errorMessageDiv.html(errorHtml).removeClass('d-none');
      expect(errorMessageDiv.hasClass('d-none')).toBe(false);
    });
  });
});
```

**PHP Test Structure:**

Class-based test cases with setUp and test methods:
```php
class CreateMapRequestTest extends TestCase
{
    public function testConstructorWithRequiredParametersOnly(): void
    {
        $request = new CreateMapRequest(
            uid: 125,
            tpKey: 'mylink',
            domain: 'dev.trfc.link',
            destination: 'https://example.com'
        );

        $this->assertEquals(125, $request->getUid());
        $this->assertEquals('mylink', $request->getTpKey());
    }

    public function testToArrayWithExpiry(): void
    {
        $request = new CreateMapRequest(...);
        $array = $request->toArray();

        $this->assertArrayHasKey('expires_at', $array);
        $this->assertEquals($expiryDate, $array['expires_at']);
    }
}
```

**Patterns:**

**Setup:**
- JavaScript: `beforeEach()` hook to reset state before each test
- PHP: `protected function setUp(): void` method called before each test method

**Teardown:**
- JavaScript: Implicit via beforeEach
- PHP: No explicit tearDown observed (setUp-only pattern)

**Assertion:**
- JavaScript: `expect(value).toBe(expected)`, `expect(array).toContain(item)`, `expect(fn).not.toContain(item)`
- PHP: `$this->assertEquals()`, `$this->assertArrayHasKey()`, `$this->assertArrayNotHasKey()`, `$this->assertTrue()`, `$this->assertNull()`

## Mocking

**Framework:** Vitest native `vi` for JavaScript, PHPUnit for PHP

**JavaScript Patterns:**

localStorage mocking in setup file:
```javascript
// tests/setup.js
global.localStorage = {
  store: {},
  getItem(key) { return this.store[key] || null; },
  setItem(key, value) { this.store[key] = String(value); },
  removeItem(key) { delete this.store[key]; },
  clear() { this.store = {}; }
};

// Mock console methods
global.console = {
  ...console,
  error: vi.fn(),
  warn: vi.fn(),
  log: vi.fn(),
};

// Clean up after each test
beforeEach(() => {
  localStorage.clear();
});
```

Manual mock object construction for DOM elements:
```javascript
// Simulate DOM element with chainable methods
errorMessageDiv = {
  classList: new Set(['d-none']),
  hasClass: function(className) { return this.classList.has(className); },
  addClass: function(className) {
    this.classList.add(className);
    return this;  // Chainable
  },
  removeClass: function(className) {
    this.classList.delete(className);
    return this;  // Chainable
  },
  html: function(content) {
    if (content !== undefined) {
      this.innerHTML = content;
      return this;  // Chainable
    }
    return this.innerHTML;
  }
};
```

**PHP Patterns:**

MockHttpClient for HTTP testing:
```php
// tests/Unit/SnapCaptureClientTest.php
protected function setUp(): void
{
    parent::setUp();
    $this->mockHttpClient = new MockHttpClient();
    $this->client = new SnapCaptureClient('test-api-key', $this->mockHttpClient);
}
```

Dependency injection for testability:
```php
// Constructor accepts optional MockHttpClient for testing
public function __construct(string $apiKey, ?HttpClientInterface $httpClient = null) {
    $this->httpClient = $httpClient ?? new HttpClient();
}
```

**What to Mock:**
- External API clients (TrafficPortalApiClient, SnapCaptureClient, GenerateShortCodeClient)
- HTTP responses (use MockHttpClient or HTTP response fixtures)
- Browser storage (localStorage)
- Console methods (to reduce test output noise)

**What NOT to Mock:**
- Data Transfer Objects (DTOs) - test with real instances
- Validation logic - test actual implementation
- Business logic methods - test real behavior
- Database queries (when integration testing)

## Fixtures and Factories

**Test Data:**

Named test objects for common scenarios:
```javascript
// From rate-limit.test.js
const rateLimitResponse = {
  success: false,
  data: {
    message: 'Anonymous users can only create 1 short URL...',
    error_type: 'rate_limit',
    http_code: 429
  }
};

const validationResponse = {
  success: false,
  data: {
    message: 'This shortcode is already taken.'
  }
};
```

PHP DTO test fixtures:
```php
// From CreateMapRequestTest.php
$request = new CreateMapRequest(
    uid: 125,
    tpKey: 'mylink',
    domain: 'dev.trfc.link',
    destination: 'https://example.com',
    status: 'active',
    type: 'redirect',
    tags: 'tag1,tag2'
);
```

**Location:**
- Inline within test files (no separate fixture files observed)
- Test data created within beforeEach or test functions
- Common mocks defined in global setup file (`tests/setup.js`)

## Coverage

**Requirements:**
- Not enforced in package.json or phpunit.xml
- Coverage reporting available but not required

**View Coverage:**
```bash
npm run test:coverage       # Generate coverage reports: text, json, html
# Reports saved to coverage/ directory (likely)
```

**Configuration:**
- Vitest config in `vitest.config.js`:
  ```javascript
  coverage: {
    provider: 'v8',
    reporter: ['text', 'json', 'html'],
    exclude: [
      'node_modules/',
      'tests/',
      '*.config.js'
    ]
  }
  ```

- PHPUnit config in `phpunit.xml`:
  ```xml
  <coverage>
    <include>
      <directory suffix=".php">includes/SnapCapture</directory>
      <directory suffix=".php">includes/ShortCode</directory>
      <directory suffix=".php">includes/TrafficPortal</directory>
    </include>
  </coverage>
  ```

## Test Types

**Unit Tests:**
- Scope: Individual classes, methods, functions
- Approach: Test one piece of functionality in isolation
- Examples:
  - `CreateMapRequestTest.php` - Tests DTO constructor and toArray conversion
  - `storage-service.test.js` - Tests StorageService methods with mocked localStorage
  - `SnapCaptureClientTest.php` - Tests client with mock HTTP responses
- Pattern: Simple input → assertion on output

**Integration Tests:**
- Scope: Multiple components working together
- Approach: Test workflows and interactions
- Examples:
  - `rate-limit.test.js` - Tests rate limit error handling flow
  - `returning-visitor.test.js` - Tests data persistence and retrieval across page loads
  - `tests/Integration/` directory for full integration scenarios
- Pattern: Setup state → trigger action → verify side effects

**E2E Tests:**
- Framework: Python with Selenium (based on `tests/e2e/` structure)
- Scope: Full user workflows through web browser
- Examples:
  - Form submission to link creation
  - User navigation and link management
- Not detailed in codebase analysis (Python test files not read)

## Common Patterns

**Async Testing:**
```javascript
// From frontend.js tests
it('should retrieve uid from localStorage if available', () => {
  localStorage.setItem('tpUid', '123');
  const storedUid = localStorage.getItem('tpUid');
  expect(storedUid).toBe('123');
});

// From async function tests
init: async function() {
  // Tests would use await or promise chains
  await this.initializeFingerprintJS();
  this.bindEvents();
}
```

**Error Testing:**

Testing error responses and edge cases:
```javascript
// From rate-limit.test.js
describe('Edge Cases', () => {
  it('should handle missing error_type gracefully', () => {
    const response = {
      success: false,
      data: { message: 'Some error occurred' }
    };
    expect(response.data.error_type).toBeUndefined();
  });

  it('should handle malformed response data', () => {
    const response = { success: false, data: null };
    expect(response.data).toBeNull();

    // Should not crash when accessing properties
    const errorType = response.data && response.data.error_type;
    expect(errorType).toBeFalsy();
  });
});
```

**Anonymous vs Authenticated Testing:**
```javascript
// Testing different user states
describe('Anonymous User Detection', () => {
  it('should detect when user is not logged in', () => {
    const isLoggedIn = false;
    expect(isLoggedIn).toBe(false);
  });

  it('should use uid=-1 for anonymous users', () => {
    const uid = -1;
    expect(uid).toBe(-1);
  });
});
```

**Data Transformation Testing:**
```php
// From CreateMapRequestTest.php
public function testToArrayWithoutExpiry(): void
{
    $request = new CreateMapRequest(...);
    $array = $request->toArray();

    $this->assertIsArray($array);
    $this->assertEquals('mylink', $array['tpKey']);
    $this->assertArrayNotHasKey('expires_at', $array);
}
```

## Test Coverage Areas

**Well Tested:**
- `includes/TrafficPortal/DTO/` - All DTO classes have unit tests
- `assets/js/storage-service.js` - localStorage interactions tested
- Error handling and edge cases in rate limiting
- User state detection (anonymous vs authenticated)

**Partially Tested:**
- API client behavior (mock-based, not live API)
- End-to-end workflows (E2E tests in separate Python framework)

**Not Observed:**
- Admin settings page tests
- Dashboard UI component tests
- Full form submission integration tests
- SnapCapture screenshot capture tests (mocked only)

---

*Testing analysis: 2026-02-15*
