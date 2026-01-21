<?php

declare(strict_types=1);

namespace TrafficPortal\DTO;

/**
 * Fingerprint Search Response DTO
 *
 * Data Transfer Object for the response from searching by fingerprint
 *
 * @package TrafficPortal\DTO
 */
class FingerprintSearchResponse
{
    private string $message;
    private bool $success;
    private string $fingerprint;
    private int $count;
    /** @var FingerprintRecord[] */
    private array $records;

    /**
     * Constructor
     *
     * @param string $message Response message
     * @param bool $success Whether the operation was successful
     * @param string $fingerprint The searched fingerprint
     * @param int $count Number of records found
     * @param FingerprintRecord[] $records The found records
     */
    public function __construct(
        string $message,
        bool $success,
        string $fingerprint,
        int $count,
        array $records
    ) {
        $this->message = $message;
        $this->success = $success;
        $this->fingerprint = $fingerprint;
        $this->count = $count;
        $this->records = $records;
    }

    /**
     * Create from API response array
     *
     * @param array $data The response data from API
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $source = $data['source'] ?? [];
        $recordsData = $source['records'] ?? [];

        $records = array_map(
            fn(array $record) => FingerprintRecord::fromArray($record),
            $recordsData
        );

        return new self(
            $data['message'] ?? '',
            $data['success'] ?? false,
            $source['fingerprint'] ?? '',
            $source['count'] ?? 0,
            $records
        );
    }

    /**
     * Get the response message
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Check if the operation was successful
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get the searched fingerprint
     *
     * @return string
     */
    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * Get the number of records found
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Get the found records
     *
     * @return FingerprintRecord[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Check if any records were found
     *
     * @return bool
     */
    public function hasRecords(): bool
    {
        return $this->count > 0;
    }

    /**
     * Get the first record (convenience method for single-record responses)
     *
     * @return FingerprintRecord|null
     */
    public function getFirstRecord(): ?FingerprintRecord
    {
        return $this->records[0] ?? null;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'success' => $this->success,
            'source' => [
                'fingerprint' => $this->fingerprint,
                'count' => $this->count,
                'records' => array_map(
                    fn(FingerprintRecord $record) => $record->toArray(),
                    $this->records
                ),
            ],
        ];
    }
}
