<?php

declare(strict_types=1);

namespace Redakte\Result;

/**
 * Hassas orijinal veriyi içermeyen, güvenli span metadata nesnesi.
 */
final readonly class SafeSpan
{
    public function __construct(
        public int $startOffset,
        public int $endOffset,
        public string $entityType,
        public string $replacement,
        public float $confidence = 1.0,
        public string $ruleId = '',
    ) {}

    public function length(): int
    {
        return $this->endOffset - $this->startOffset;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start_offset' => $this->startOffset,
            'end_offset' => $this->endOffset,
            'length' => $this->length(),
            'entity_type' => $this->entityType,
            'replacement' => $this->replacement,
            'confidence' => $this->confidence,
            'rule_id' => $this->ruleId,
        ];
    }
}
