<?php

declare(strict_types=1);

namespace ShortCode;

enum GenerationMethod: string
{
    case RuleBased = 'rule-based';
    case NlpComprehend = 'nlp-comprehend';
    case GeminiAI = 'gemini-ai';

    public static function fromString(string $method): self
    {
        return match ($method) {
            'rule-based' => self::RuleBased,
            'nlp-comprehend' => self::NlpComprehend,
            'gemini-ai' => self::GeminiAI,
            default => throw new \InvalidArgumentException("Unknown generation method: $method"),
        };
    }
}
