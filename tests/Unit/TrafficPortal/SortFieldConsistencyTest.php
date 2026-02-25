<?php

declare(strict_types=1);

namespace Tests\Unit\TrafficPortal;

use PHPUnit\Framework\TestCase;
use TrafficPortal\TrafficPortalApiClient;
use TrafficPortal\Exception\ValidationException;
use TrafficPortal\Http\MockHttpClient;
use TrafficPortal\Http\HttpResponse;

/**
 * TP-105: Sorting by column returns an error on Client Links table.
 *
 * The Client Links template exposes sortable columns (destination, clicks)
 * that pass the API handler's validation but are rejected by the API client,
 * causing a ValidationException when users click those column headers.
 *
 * These tests will FAIL until the external API adds support for sorting
 * by 'destination' and 'clicks'. See API change request in Jira.
 */
class SortFieldConsistencyTest extends TestCase
{
    private MockHttpClient $httpClient;
    private TrafficPortalApiClient $client;

    /**
     * Sort fields that the API handler (class-tp-api-handler.php) allows.
     * These are the fields users can trigger via the Client Links UI.
     * Once the API supports all of these, the TrafficPortalApiClient's
     * allowed list must be updated to match.
     */
    private const API_HANDLER_ALLOWED_SORT_FIELDS = [
        'updated_at',
        'created_at',
        'tpKey',
        'destination',
        'clicks',
    ];

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

    private function enqueueSuccessResponse(): void
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
    }

    /**
     * TP-105: Sorting by 'destination' should not throw a ValidationException.
     *
     * FAILS until API supports 'destination' sort field.
     * Fix: add 'destination' to TrafficPortalApiClient::$allowedSortFields
     */
    public function testSortByDestinationDoesNotThrowValidationException(): void
    {
        $this->enqueueSuccessResponse();

        $result = $this->client->getUserMapItems(123, sort: 'destination:asc');

        $this->assertTrue($result->isSuccess());
    }

    /**
     * TP-105: Sorting by 'clicks' should not throw a ValidationException.
     *
     * FAILS until API supports 'clicks' sort field.
     * Fix: add 'clicks' to TrafficPortalApiClient::$allowedSortFields
     */
    public function testSortByClicksDoesNotThrowValidationException(): void
    {
        $this->enqueueSuccessResponse();

        $result = $this->client->getUserMapItems(123, sort: 'clicks:desc');

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Verify all handler-allowed sort fields are accepted by the API client.
     *
     * FAILS until API supports 'destination' and 'clicks' sort fields
     * and TrafficPortalApiClient is updated to allow them.
     */
    public function testAllHandlerSortFieldsAcceptedByApiClient(): void
    {
        $failedFields = [];

        foreach (self::API_HANDLER_ALLOWED_SORT_FIELDS as $field) {
            $this->enqueueSuccessResponse();

            try {
                $this->client->getUserMapItems(123, sort: $field . ':asc');
            } catch (ValidationException $e) {
                $failedFields[] = $field;
            }
        }

        $this->assertEmpty(
            $failedFields,
            'These sort fields are allowed by the API handler but rejected by the API client: '
            . implode(', ', $failedFields)
            . '. Add them to TrafficPortalApiClient once the API supports them.'
        );
    }
}
