<?php

declare(strict_types=1);

namespace Redakte\Policy;

final class RedactionPolicy
{
    public const STRICT = 'strict';
    public const BALANCED = 'balanced';
    public const VALIDATED_ONLY = 'validated_only';
    public const LOGS = 'logs';

    public function __construct(
        public readonly string $name = self::STRICT,
        public readonly bool $maskInvalidCandidates = true,
        public readonly bool $failClosed = false,
        public readonly float $minConfidence = 0.5,
    ) {}

    public static function strict(): self
    {
        return new self(
            name: self::STRICT,
            maskInvalidCandidates: true,
            failClosed: false,
            minConfidence: 0.5,
        );
    }

    public static function balanced(): self
    {
        return new self(
            name: self::BALANCED,
            maskInvalidCandidates: false,
            failClosed: false,
            minConfidence: 0.6,
        );
    }

    public static function validatedOnly(): self
    {
        return new self(
            name: self::VALIDATED_ONLY,
            maskInvalidCandidates: false,
            failClosed: false,
            minConfidence: 0.8,
        );
    }

    public static function logs(): self
    {
        return new self(
            name: self::LOGS,
            maskInvalidCandidates: true,
            failClosed: true,
            minConfidence: 0.4,
        );
    }

    public static function fromString(string $policy): self
    {
        return match (strtolower(trim($policy))) {
            self::BALANCED => self::balanced(),
            self::STRICT => self::strict(),
            self::VALIDATED_ONLY => self::validatedOnly(),
            self::LOGS => self::logs(),
            default => throw new \InvalidArgumentException(sprintf('Geçersiz redaksiyon güvenlik politikası: "%s". İzin verilenler: balanced, strict, validated_only, logs.', $policy)),
        };
    }
}
