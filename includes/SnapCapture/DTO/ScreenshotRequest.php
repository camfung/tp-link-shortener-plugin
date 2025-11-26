<?php

declare(strict_types=1);

namespace SnapCapture\DTO;

/**
 * Screenshot Request DTO
 *
 * Data Transfer Object for SnapCapture API screenshot requests
 *
 * @package SnapCapture\DTO
 */
class ScreenshotRequest
{
    private string $url;
    private string $format;
    private int $quality;
    private array $viewport;
    private bool $fullPage;
    private bool $mobile;

    /**
     * Constructor
     *
     * @param string $url Full URL to capture (must include http:// or https://)
     * @param string $format Image format: 'jpeg' or 'png' (default: 'jpeg')
     * @param int $quality JPEG quality 1-100 (only for JPEG format, default: 80)
     * @param array $viewport Browser viewport size (default: ['width' => 1920, 'height' => 1080])
     * @param bool $fullPage Capture entire scrollable page (default: false)
     * @param bool $mobile Use mobile User-Agent (default: false)
     */
    public function __construct(
        string $url,
        string $format = 'jpeg',
        int $quality = 80,
        array $viewport = ['width' => 1920, 'height' => 1080],
        bool $fullPage = false,
        bool $mobile = false
    ) {
        $this->url = $url;
        $this->format = $format;
        $this->quality = $quality;
        $this->viewport = $viewport;
        $this->fullPage = $fullPage;
        $this->mobile = $mobile;
    }

    /**
     * Get URL
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get format
     *
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Get quality
     *
     * @return int
     */
    public function getQuality(): int
    {
        return $this->quality;
    }

    /**
     * Get viewport
     *
     * @return array
     */
    public function getViewport(): array
    {
        return $this->viewport;
    }

    /**
     * Get fullPage setting
     *
     * @return bool
     */
    public function isFullPage(): bool
    {
        return $this->fullPage;
    }

    /**
     * Get mobile setting
     *
     * @return bool
     */
    public function isMobile(): bool
    {
        return $this->mobile;
    }

    /**
     * Convert to array for API request
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'format' => $this->format,
            'quality' => $this->quality,
            'viewport' => $this->viewport,
            'fullPage' => $this->fullPage,
            'mobile' => $this->mobile,
        ];
    }

    /**
     * Create a desktop screenshot request
     *
     * @param string $url URL to capture
     * @param string $format Image format (default: 'jpeg')
     * @param int $quality JPEG quality (default: 80)
     * @return self
     */
    public static function desktop(
        string $url,
        string $format = 'jpeg',
        int $quality = 80
    ): self {
        return new self($url, $format, $quality);
    }

    /**
     * Create a mobile screenshot request
     *
     * @param string $url URL to capture
     * @param string $format Image format (default: 'jpeg')
     * @param int $quality JPEG quality (default: 80)
     * @return self
     */
    public static function mobile(
        string $url,
        string $format = 'jpeg',
        int $quality = 80
    ): self {
        return new self(
            $url,
            $format,
            $quality,
            ['width' => 375, 'height' => 667],
            false,
            true
        );
    }

    /**
     * Create a full page screenshot request
     *
     * @param string $url URL to capture
     * @param string $format Image format (default: 'jpeg')
     * @param int $quality JPEG quality (default: 80)
     * @return self
     */
    public static function fullPage(
        string $url,
        string $format = 'jpeg',
        int $quality = 80
    ): self {
        return new self($url, $format, $quality, ['width' => 1920, 'height' => 1080], true);
    }
}
