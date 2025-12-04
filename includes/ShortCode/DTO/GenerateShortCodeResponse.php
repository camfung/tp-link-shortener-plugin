<?php

declare(strict_types=1);

namespace ShortCode\DTO;

class GenerateShortCodeResponse
{
    private string $shortCode;
    private string $originalCode;
    private bool $wasModified;
    private string $url;
    private string $message;

    public function __construct(
        string $shortCode,
        string $originalCode,
        bool $wasModified,
        string $url,
        string $message
    ) {
        $this->shortCode = $shortCode;
        $this->originalCode = $originalCode;
        $this->wasModified = $wasModified;
        $this->url = $url;
        $this->message = $message;
    }

    public function getShortCode(): string
    {
        return $this->shortCode;
    }

    public function getOriginalCode(): string
    {
        return $this->originalCode;
    }

    public function wasModified(): bool
    {
        return $this->wasModified;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
