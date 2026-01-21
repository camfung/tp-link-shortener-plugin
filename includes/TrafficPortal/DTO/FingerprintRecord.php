<?php

declare(strict_types=1);

namespace TrafficPortal\DTO;

/**
 * Fingerprint Record DTO
 *
 * Data Transfer Object for a single record returned from fingerprint search
 *
 * @package TrafficPortal\DTO
 */
class FingerprintRecord
{
    private int $mid;
    private string $tpKey;
    private string $domain;
    private string $destination;
    private string $status;
    private ?string $expiresAt;
    private ?string $createdByFingerprint;
    private ?string $updatedAt;
    private FingerprintRecordUsage $usage;

    /**
     * Constructor
     *
     * @param int $mid Map record ID
     * @param string $tpKey Short URL key
     * @param string $domain Domain
     * @param string $destination Target URL
     * @param string $status Record status
     * @param string|null $expiresAt Expiry datetime
     * @param string|null $createdByFingerprint Fingerprint that created this record
     * @param string|null $updatedAt Last update datetime
     * @param FingerprintRecordUsage $usage Usage statistics
     */
    public function __construct(
        int $mid,
        string $tpKey,
        string $domain,
        string $destination,
        string $status,
        ?string $expiresAt,
        ?string $createdByFingerprint,
        ?string $updatedAt,
        FingerprintRecordUsage $usage
    ) {
        $this->mid = $mid;
        $this->tpKey = $tpKey;
        $this->domain = $domain;
        $this->destination = $destination;
        $this->status = $status;
        $this->expiresAt = $expiresAt;
        $this->createdByFingerprint = $createdByFingerprint;
        $this->updatedAt = $updatedAt;
        $this->usage = $usage;
    }

    /**
     * Create from API response array
     *
     * @param array $data The record data from API
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['mid'] ?? 0),
            $data['tpKey'] ?? '',
            $data['domain'] ?? '',
            $data['destination'] ?? '',
            $data['status'] ?? '',
            $data['expires_at'] ?? null,
            $data['created_by_fingerprint'] ?? null,
            $data['updated_at'] ?? null,
            FingerprintRecordUsage::fromArray($data['usage'] ?? null)
        );
    }

    /**
     * Get the map record ID
     *
     * @return int
     */
    public function getMid(): int
    {
        return $this->mid;
    }

    /**
     * Get the short URL key
     *
     * @return string
     */
    public function getTpKey(): string
    {
        return $this->tpKey;
    }

    /**
     * Get the domain
     *
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Get the destination URL
     *
     * @return string
     */
    public function getDestination(): string
    {
        return $this->destination;
    }

    /**
     * Get the record status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get the expiry datetime
     *
     * @return string|null
     */
    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    /**
     * Get the fingerprint that created this record
     *
     * @return string|null
     */
    public function getCreatedByFingerprint(): ?string
    {
        return $this->createdByFingerprint;
    }

    /**
     * Get the last update datetime
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * Get the usage statistics
     *
     * @return FingerprintRecordUsage
     */
    public function getUsage(): FingerprintRecordUsage
    {
        return $this->usage;
    }

    /**
     * Get the full short URL
     *
     * @return string
     */
    public function getShortUrl(): string
    {
        return 'https://' . $this->domain . '/' . $this->tpKey;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'mid' => $this->mid,
            'tpKey' => $this->tpKey,
            'domain' => $this->domain,
            'destination' => $this->destination,
            'status' => $this->status,
            'expires_at' => $this->expiresAt,
            'created_by_fingerprint' => $this->createdByFingerprint,
            'updated_at' => $this->updatedAt,
            'usage' => $this->usage->toArray(),
        ];
    }
}
