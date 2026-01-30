<?php

declare(strict_types=1);

namespace TrafficPortal\DTO;

/**
 * Paginated Map Items Response DTO
 *
 * Data Transfer Object for the paginated map items API response
 *
 * @package TrafficPortal\DTO
 */
class PaginatedMapItemsResponse
{
    private string $message;
    private bool $success;
    private int $page;
    private int $pageSize;
    private int $totalRecords;
    private int $totalPages;
    /** @var MapItem[] */
    private array $items;

    /**
     * @param MapItem[] $items
     */
    public function __construct(
        string $message,
        bool $success,
        int $page,
        int $pageSize,
        int $totalRecords,
        int $totalPages,
        array $items
    ) {
        $this->message = $message;
        $this->success = $success;
        $this->page = $page;
        $this->pageSize = $pageSize;
        $this->totalRecords = $totalRecords;
        $this->totalPages = $totalPages;
        $this->items = $items;
    }

    /**
     * Create from API response array
     */
    public static function fromArray(array $data): self
    {
        $sourceData = $data['source'] ?? [];
        $items = array_map(
            fn(array $item) => MapItem::fromArray($item),
            $sourceData
        );

        return new self(
            $data['message'] ?? '',
            $data['success'] ?? false,
            (int) ($data['page'] ?? 1),
            (int) ($data['page_size'] ?? 50),
            (int) ($data['total_records'] ?? 0),
            (int) ($data['total_pages'] ?? 0),
            $items
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function getTotalRecords(): int
    {
        return $this->totalRecords;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * @return MapItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function hasItems(): bool
    {
        return count($this->items) > 0;
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->totalPages;
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'success' => $this->success,
            'page' => $this->page,
            'page_size' => $this->pageSize,
            'total_records' => $this->totalRecords,
            'total_pages' => $this->totalPages,
            'source' => array_map(fn(MapItem $item) => $item->toArray(), $this->items),
        ];
    }
}
