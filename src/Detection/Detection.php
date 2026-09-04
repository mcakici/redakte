<?php

declare(strict_types=1);

namespace Redakte\Detection;

/**
 * Bir dedektör tarafından orijinal metin koordinatları üzerinde tespit edilen aday.
 */
final readonly class Detection
{
    public function __construct(
        public int $startOffset,
        public int $endOffset,
        public string $entityType,
        public string $originalValue,
        public string $normalizedValue,
        public float $confidence,
        public string $ruleId,
        public string $validationStatus = 'not_checked', // 'valid' | 'invalid' | 'not_checked'
        /** @var list<string> */
        public array $evidences = [],
        public int $priority = 50,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}

    public function length(): int
    {
        return $this->endOffset - $this->startOffset;
    }

    public function hasEvidence(DetectionEvidence|string $evidence): bool
    {
        $val = $evidence instanceof DetectionEvidence ? $evidence->value : $evidence;
        return in_array($val, $this->evidences, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'start_offset' => $this->startOffset,
            'end_offset' => $this->endOffset,
            'length' => $this->length(),
            'entity_type' => $this->entityType,
            'confidence' => $this->confidence,
            'rule_id' => $this->ruleId,
            'validation_status' => $this->validationStatus,
            'evidences' => $this->evidences,
            'priority' => $this->priority,
        ];
    }
}
