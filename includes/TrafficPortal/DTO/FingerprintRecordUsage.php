<?php

declare(strict_types=1);

namespace TrafficPortal\DTO;

/**
 * Fingerprint Record Usage DTO
 *
 * Data Transfer Object for usage statistics on a fingerprint record
 *
 * @package TrafficPortal\DTO
 */
class FingerprintRecordUsage
{
    private int $total;
    private int $qr;
    private int $regular;

    /**
     * Constructor
     *
     * @param int $total Total clicks/visits
     * @param int $qr QR code scans
     * @param int $regular Direct/regular visits
     */
    public function __construct(int $total, int $qr, int $regular)
    {
        $this->total = $total;
        $this->qr = $qr;
        $this->regular = $regular;
    }

    /**
     * Create from API response array
     *
     * @param array|null $data The usage data from API
     * @return self
     */
    public static function fromArray(?array $data): self
    {
        return new self(
            $data['total'] ?? 0,
            $data['qr'] ?? 0,
            $data['regular'] ?? 0
        );
    }

    /**
     * Get total clicks/visits
     *
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Get QR code scans
     *
     * @return int
     */
    public function getQr(): int
    {
        return $this->qr;
    }

    /**
     * Get direct/regular visits
     *
     * @return int
     */
    public function getRegular(): int
    {
        return $this->regular;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'qr' => $this->qr,
            'regular' => $this->regular,
        ];
    }
}
