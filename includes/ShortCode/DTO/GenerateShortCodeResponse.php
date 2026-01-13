<?php

declare(strict_types=1);

namespace ShortCode\DTO;

class GenerateShortCodeResponse
{
    private string $shortCode;
    private string $method;
    private bool $wasModified;
    private string $message;
    private ?string $originalCode;
    private ?string $url;
    private ?array $candidates;
    private ?array $keyPhrases;
    private ?array $entities;

    public function __construct(
        string $shortCode,
        string $method,
        bool $wasModified,
        string $message,
        ?string $originalCode = null,
        ?string $url = null,
        ?array $candidates = null,
        ?array $keyPhrases = null,
        ?array $entities = null
    ) {
        $this->shortCode = $shortCode;
        $this->method = $method;
        $this->wasModified = $wasModified;
        $this->message = $message;
        $this->originalCode = $originalCode;
        $this->url = $url;
        $this->candidates = $candidates;
        $this->keyPhrases = $keyPhrases;
        $this->entities = $entities;
    }

    public function getShortCode(): string
    {
        return $this->shortCode;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function wasModified(): bool
    {
        return $this->wasModified;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getOriginalCode(): ?string
    {
        return $this->originalCode;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getCandidates(): ?array
    {
        return $this->candidates;
    }

    public function getKeyPhrases(): ?array
    {
        return $this->keyPhrases;
    }

    public function getEntities(): ?array
    {
        return $this->entities;
    }
}
