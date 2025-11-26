<?php

declare(strict_types=1);

namespace SnapCapture\DTO;

/**
 * Screenshot Response DTO
 *
 * Data Transfer Object for SnapCapture API screenshot responses
 *
 * @package SnapCapture\DTO
 */
class ScreenshotResponse
{
    private string $imageData;
    private bool $cached;
    private ?int $responseTimeMs;
    private string $contentType;

    /**
     * Constructor
     *
     * @param string $imageData Binary image data
     * @param bool $cached Whether the response was cached
     * @param int|null $responseTimeMs Response time in milliseconds
     * @param string $contentType Content type (e.g., 'image/jpeg', 'image/png')
     */
    public function __construct(
        string $imageData,
        bool $cached = false,
        ?int $responseTimeMs = null,
        string $contentType = 'image/jpeg'
    ) {
        $this->imageData = $imageData;
        $this->cached = $cached;
        $this->responseTimeMs = $responseTimeMs;
        $this->contentType = $contentType;
    }

    /**
     * Get image data
     *
     * @return string Binary image data
     */
    public function getImageData(): string
    {
        return $this->imageData;
    }

    /**
     * Check if response was cached
     *
     * @return bool
     */
    public function isCached(): bool
    {
        return $this->cached;
    }

    /**
     * Get response time in milliseconds
     *
     * @return int|null
     */
    public function getResponseTimeMs(): ?int
    {
        return $this->responseTimeMs;
    }

    /**
     * Get content type
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Save image to file
     *
     * @param string $filepath Path where to save the image
     * @return bool True on success, false on failure
     */
    public function saveToFile(string $filepath): bool
    {
        return file_put_contents($filepath, $this->imageData) !== false;
    }

    /**
     * Get base64 encoded image data
     *
     * @return string
     */
    public function getBase64(): string
    {
        return base64_encode($this->imageData);
    }

    /**
     * Get data URI for embedding in HTML
     *
     * @return string
     */
    public function getDataUri(): string
    {
        return sprintf(
            'data:%s;base64,%s',
            $this->contentType,
            $this->getBase64()
        );
    }
}
