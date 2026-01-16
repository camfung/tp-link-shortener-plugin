<?php

declare(strict_types=1);

namespace ShortCode\DTO;

use ShortCode\GenerationMethod;

class GenerateShortCodeResponse
{
    private string $shortCode;
    private bool $wasModified;
    private GenerationMethod $method;
    private string $message;
    private ?string $originalCode;
    private ?string $url;
    /** @var string[] */
    private array $candidates;
    /** @var string[] */
    private array $keyPhrases;
    /** @var string[] */
    private array $entities;

    /**
     * @param string[] $candidates
     * @param string[] $keyPhrases
     * @param string[] $entities
     */
    public function __construct(
        string $shortCode,
        bool $wasModified,
        GenerationMethod $method,
        string $message = '',
        ?string $originalCode = null,
        ?string $url = null,
        array $candidates = [],
        array $keyPhrases = [],
        array $entities = []
    ) {
        $this->shortCode = $shortCode;
        $this->wasModified = $wasModified;
        $this->method = $method;
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

    public function getOriginalCode(): ?string
    {
        return $this->originalCode;
    }

    public function wasModified(): bool
    {
        return $this->wasModified;
    }

    public function getMethod(): GenerationMethod
    {
        return $this->method;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return string[]
     */
    public function getCandidates(): array
    {
        return $this->candidates;
    }

    /**
     * @return string[]
     */
    public function getKeyPhrases(): array
    {
        return $this->keyPhrases;
    }

    /**
     * @return string[]
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
