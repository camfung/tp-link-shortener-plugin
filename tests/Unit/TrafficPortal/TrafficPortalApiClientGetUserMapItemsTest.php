<?php

declare(strict_types=1);

namespace Tests\Unit\TrafficPortal;

use PHPUnit\Framework\TestCase;
use TrafficPortal\TrafficPortalApiClient;
use TrafficPortal\DTO\PaginatedMapItemsResponse;
use TrafficPortal\Exception\ValidationException;
use TrafficPortal\Exception\PageNotFoundException;
use TrafficPortal\Exception\ApiException;
use TrafficPortal\Exception\NetworkException;
use TrafficPortal\Exception\AuthenticationException;
use TrafficPortal\Http\MockHttpClient;
use TrafficPortal\Http\HttpResponse;

class TrafficPortalApiClientGetUserMapItemsTest extends TestCase
{
    private MockHttpClient $httpClient;
    private TrafficPortalApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = new MockHttpClient();
        $this->client = new TrafficPortalApiClient(
            'https://api.example.com',
            'test-api-key',
            30,
            $this->httpClient
        );
    }

    public function testGetUserMapItemsSuccess(): void
    {
        $responseBody = json_encode([
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 1,
            'page_size' => 50,
            'total_records' => 1234,
            'total_pages' => 25,
            'source' => [
                [
                    'mid' => 14205,
                    'uid' => 123,
                    'tpKey' => 'mylink',
                    'domain' => 'dev.trfc.link',
                    'destination' => 'https://example.com/landing',
                    'status' => 'active',
                    'notes' => 'My link',
                    'created_at' => '2026-01-10T12:00:00Z',
                    'updated_at' => '2026-01-20T15:30:00Z',
                    'usage' => ['total' => 150, 'qr' => 45, 'regular' => 105],
                ],
            ],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $result = $this->client->getUserMapItems(123);

        $this->assertInstanceOf(PaginatedMapItemsResponse::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(50, $result->getPageSize());
        $this->assertEquals(1234, $result->getTotalRecords());
        $this->assertEquals(25, $result->getTotalPages());
        $this->assertCount(1, $result->getItems());
        $this->assertEquals('mylink', $result->getItems()[0]->getTpKey());
    }

    public function testGetUserMapItemsWithAllParameters(): void
    {
        $responseBody = json_encode([
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 2,
            'page_size' => 25,
            'total_records' => 100,
            'total_pages' => 4,
            'source' => [],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $result = $this->client->getUserMapItems(
            uid: 123,
            page: 2,
            pageSize: 25,
            sort: 'created_at:asc',
            includeUsage: false,
            status: 'active',
            search: 'test'
        );

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertStringContainsString('/items/user/123', $lastRequest['url']);
        $this->assertStringContainsString('page=2', $lastRequest['url']);
        $this->assertStringContainsString('page_size=25', $lastRequest['url']);
        $this->assertStringContainsString('sort=created_at%3Aasc', $lastRequest['url']);
        $this->assertStringContainsString('include_usage=false', $lastRequest['url']);
        $this->assertStringContainsString('status=active', $lastRequest['url']);
        $this->assertStringContainsString('search=test', $lastRequest['url']);
        $this->assertEquals('GET', $lastRequest['method']);
    }

    public function testGetUserMapItemsWithNegativeUid(): void
    {
        $responseBody = json_encode([
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 1,
            'page_size' => 50,
            'total_records' => 5,
            'total_pages' => 1,
            'source' => [],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $result = $this->client->getUserMapItems(-1);

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertStringContainsString('/items/user/-1', $lastRequest['url']);
        $this->assertTrue($result->isSuccess());
    }

    public function testGetUserMapItemsEmptyResults(): void
    {
        $responseBody = json_encode([
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 1,
            'page_size' => 50,
            'total_records' => 0,
            'total_pages' => 0,
            'source' => [],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $result = $this->client->getUserMapItems(999);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(0, $result->getTotalRecords());
        $this->assertEquals(0, $result->getTotalPages());
        $this->assertFalse($result->hasItems());
    }

    public function testGetUserMapItemsInvalidPageLessThanOne(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid page. Must be >= 1.');

        $this->client->getUserMapItems(123, page: 0);
    }

    public function testGetUserMapItemsInvalidPageNegative(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid page. Must be >= 1.');

        $this->client->getUserMapItems(123, page: -1);
    }

    public function testGetUserMapItemsInvalidPageSizeTooLow(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid page_size. Must be between 1 and 200.');

        $this->client->getUserMapItems(123, pageSize: 0);
    }

    public function testGetUserMapItemsInvalidPageSizeTooHigh(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid page_size. Must be between 1 and 200.');

        $this->client->getUserMapItems(123, pageSize: 201);
    }

    public function testGetUserMapItemsInvalidSortField(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid sort. Use one of: updated_at, created_at, tpKey with asc/desc.');

        $this->client->getUserMapItems(123, sort: 'invalid_field:asc');
    }

    public function testGetUserMapItemsInvalidSortDirection(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid sort. Use one of: updated_at, created_at, tpKey with asc/desc.');

        $this->client->getUserMapItems(123, sort: 'updated_at:invalid');
    }

    public function testGetUserMapItemsInvalidSortFormat(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid sort. Use one of: updated_at, created_at, tpKey with asc/desc.');

        $this->client->getUserMapItems(123, sort: 'invalid');
    }

    public function testGetUserMapItemsSearchTooLong(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Search term too long. Maximum 255 characters.');

        $longSearch = str_repeat('a', 256);
        $this->client->getUserMapItems(123, search: $longSearch);
    }

    public function testGetUserMapItemsValidSortOptions(): void
    {
        $responseBody = json_encode([
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 1,
            'page_size' => 50,
            'total_records' => 0,
            'total_pages' => 0,
            'source' => [],
        ]);

        $validSorts = [
            'updated_at:asc',
            'updated_at:desc',
            'created_at:asc',
            'created_at:desc',
            'tpKey:asc',
            'tpKey:desc',
        ];

        foreach ($validSorts as $sort) {
            $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));
            $result = $this->client->getUserMapItems(123, sort: $sort);
            $this->assertTrue($result->isSuccess(), "Sort '$sort' should be valid");
        }
    }

    public function testGetUserMapItemsPageOutOfRange(): void
    {
        $responseBody = json_encode([
            'message' => 'Page out of range.',
            'success' => false,
        ]);

        $this->httpClient->addResponse(new HttpResponse(404, [], $responseBody));

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionMessage('Page out of range.');

        $this->client->getUserMapItems(123, page: 999);
    }

    public function testGetUserMapItemsAuthenticationError(): void
    {
        $responseBody = json_encode([
            'message' => 'Invalid API key',
            'success' => false,
        ]);

        $this->httpClient->addResponse(new HttpResponse(401, [], $responseBody));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        $this->client->getUserMapItems(123);
    }

    public function testGetUserMapItemsValidationErrorFromApi(): void
    {
        $responseBody = json_encode([
            'message' => 'Invalid uid. Must be integer.',
            'success' => false,
        ]);

        $this->httpClient->addResponse(new HttpResponse(400, [], $responseBody));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid uid. Must be integer.');

        $this->client->getUserMapItems(123);
    }

    public function testGetUserMapItemsServerError(): void
    {
        $responseBody = json_encode([
            'message' => 'Server error. Please try again.',
            'success' => false,
            'source' => null,
        ]);

        $this->httpClient->addResponse(new HttpResponse(502, [], $responseBody));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Server error');

        $this->client->getUserMapItems(123);
    }

    public function testGetUserMapItemsNetworkError(): void
    {
        $this->httpClient->throwNext(new NetworkException('Connection refused'));

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->client->getUserMapItems(123);
    }

    public function testGetUserMapItemsEmptyResponse(): void
    {
        $this->httpClient->addResponse(new HttpResponse(200, [], ''));

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Empty response from API');

        $this->client->getUserMapItems(123);
    }

    public function testGetUserMapItemsInvalidJsonResponse(): void
    {
        $this->httpClient->addResponse(new HttpResponse(200, [], 'not valid json'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $this->client->getUserMapItems(123);
    }

    public function testGetUserMapItemsRequestContainsCorrectHeaders(): void
    {
        $responseBody = json_encode([
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 1,
            'page_size' => 50,
            'total_records' => 0,
            'total_pages' => 0,
            'source' => [],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $this->client->getUserMapItems(123);

        $lastRequest = $this->httpClient->getLastRequest();
        $headers = $lastRequest['options']['headers'];

        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('test-api-key', $headers['x-api-key']);
    }

    public function testGetUserMapItemsIncludeUsageNotSetWhenTrue(): void
    {
        $responseBody = json_encode([
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 1,
            'page_size' => 50,
            'total_records' => 0,
            'total_pages' => 0,
            'source' => [],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $this->client->getUserMapItems(123, includeUsage: true);

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertStringNotContainsString('include_usage', $lastRequest['url']);
    }

    public function testGetUserMapItemsDefaultParameters(): void
    {
        $responseBody = json_encode([
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 1,
            'page_size' => 50,
            'total_records' => 0,
            'total_pages' => 0,
            'source' => [],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $this->client->getUserMapItems(123);

        $lastRequest = $this->httpClient->getLastRequest();
        $url = $lastRequest['url'];

        $this->assertStringContainsString('page=1', $url);
        $this->assertStringContainsString('page_size=50', $url);
        $this->assertStringNotContainsString('sort=', $url);
        $this->assertStringNotContainsString('include_usage=', $url);
        $this->assertStringNotContainsString('status=', $url);
        $this->assertStringNotContainsString('search=', $url);
    }
}
