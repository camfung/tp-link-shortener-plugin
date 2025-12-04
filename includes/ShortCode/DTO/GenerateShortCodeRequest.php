<?php

declare(strict_types=1);

namespace ShortCode\DTO;

use ShortCode\Exception\ValidationException;

class GenerateShortCodeRequest
{
    private string $url;
    private ?string $domain;

    public function __construct(string $url, ?string $domain = null)
    {
        $this->url = $url;
        $this->domain = $domain;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function validate(): void
    {
        if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw new ValidationException('URL must start with http:// or https://');
        }
    }

    public function toArray(): array
    {
        $this->validate();

        $payload = [
            'url' => $this->url,
        ];

        if ($this->domain !== null && $this->domain !== '') {
            $payload['domain'] = $this->domain;
        }

        return $payload;
    }
}
