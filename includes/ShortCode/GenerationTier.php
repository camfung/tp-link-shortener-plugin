<?php

declare(strict_types=1);

namespace ShortCode;

enum GenerationTier: string
{
    case Fast = 'fast';
    case Smart = 'smart';
    case AI = 'ai';

    public function getPath(): string
    {
        return match ($this) {
            self::Fast => '/generate-short-code/fast',
            self::Smart => '/generate-short-code/smart',
            self::AI => '/generate-short-code/ai',
        };
    }
}
