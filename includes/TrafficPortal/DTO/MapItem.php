<?php

declare(strict_types=1);

namespace TrafficPortal\DTO;

/**
 * Map Item DTO
 *
 * Data Transfer Object for a single map item in paginated results
 *
 * @package TrafficPortal\DTO
 */
class MapItem
{
    private int $mid;
    private int $uid;
    private string $tpKey;
    private string $domain;
    private string $destination;
    private string $status;
    private string $notes;
    private string $createdAt;
    private string $updatedAt;
    private ?MapItemUsage $usage;

    public function __construct(
        int $mid,
        int $uid,
        string $tpKey,
        string $domain,
        string $destination,
        string $status,
        string $notes,
        string $createdAt,
        string $updatedAt,
        ?MapItemUsage $usage
    ) {
        $this->mid = $mid;
        $this->uid = $uid;
        $this->tpKey = $tpKey;
        $this->domain = $domain;
        $this->destination = $destination;
        $this->status = $status;
        $this->notes = $notes;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->usage = $usage;
    }

    /**
     * Create from API response array
     */
    public static function fromArray(array $data): self
    {
        $usage = isset($data['usage']) ? MapItemUsage::fromArray($data['usage']) : null;

        return new self(
            (int) ($data['mid'] ?? 0),
            (int) ($data['uid'] ?? 0),
            $data['tpKey'] ?? '',
            $data['domain'] ?? '',
            $data['destination'] ?? '',
            $data['status'] ?? '',
            $data['notes'] ?? '',
            $data['created_at'] ?? '',
            $data['updated_at'] ?? '',
            $usage
        );
    }

    public function getMid(): int
    {
        return $this->mid;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getTpKey(): string
    {
        return $this->tpKey;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    public function getUsage(): ?MapItemUsage
    {
        return $this->usage;
    }

    public function getShortUrl(): string
    {
        return 'https://' . $this->domain . '/' . $this->tpKey;
    }

    public function toArray(): array
    {
        return [
            'mid' => $this->mid,
            'uid' => $this->uid,
            'tpKey' => $this->tpKey,
            'domain' => $this->domain,
            'destination' => $this->destination,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'usage' => $this->usage?->toArray(),
        ];
    }
}
