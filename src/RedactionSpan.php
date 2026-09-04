<?php

declare(strict_types=1);

namespace Redakte;

/**
 * Tespit edilen tek bir redaksiyon parçasının metadata ve konum bilgisi.
 */
final readonly class RedactionSpan
{
    public function __construct(
        public int $startOffset,
        public int $endOffset,
        public string $entityType,
        public string $replacement,
        public float $confidence = 1.0,
        public string $source = 'regex',
        public ?string $originalValue = null,
    ) {}

    public function length(): int
    {
        return $this->endOffset - $this->startOffset;
    }

    /**
     * Güvenli dizi formatı (hassas original_value içermez).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->toSafeArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'start_offset' => $this->startOffset,
            'end_offset' => $this->endOffset,
            'entity_type' => $this->entityType,
            'replacement' => $this->replacement,
            'confidence' => $this->confidence,
            'source' => $this->source,
        ];
    }

    /**
     * Orijinal hassas değeri içeren açık dizi formatı.
     *
     * @return array<string, mixed>
     */
    public function toSensitiveArray(): array
    {
        return [
            'start_offset' => $this->startOffset,
            'end_offset' => $this->endOffset,
            'entity_type' => $this->entityType,
            'replacement' => $this->replacement,
            'confidence' => $this->confidence,
            'source' => $this->source,
            'original_value' => $this->originalValue,
        ];
    }
}
