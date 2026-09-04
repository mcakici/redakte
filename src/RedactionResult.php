<?php

declare(strict_types=1);

namespace Redakte;

/**
 * Redaksiyon işleminin sonucu — redakte metin, span listesi, rapor özeti, token haritası ve metadata.
 */
final class RedactionResult
{
    public function __construct(
        /** @var list<RedactionSpan> */
        public array $spans,
        public string $redactedText,
        /** @var array<string, int> entity_type => adet */
        public array $replacementsByType,
        public string $reportSummary,
        public string $riskLevel = 'low',
        /** @var list<string> */
        public array $warnings = [],
        public string $policyId = '',
        public string $policyVersion = '',
        /** @var list<string> */
        public array $appliedRules = [],
        public bool $requiresHumanReview = false,
        public ?RedactionMap $map = null,
    ) {}

    public function totalReplacements(): int
    {
        return array_sum($this->replacementsByType);
    }

    public function hasReplacements(): bool
    {
        return $this->totalReplacements() > 0;
    }

    /**
     * Token içeren bir metni (örneğin LLM yanıtını) bu sonucun token haritası ile geri çözer
     */
    public function unmask(string $text): string
    {
        return $this->map?->unmask($text) ?? $text;
    }

    /**
     * Token => orijinal değer eşleşmelerini döndürür
     *
     * @return array<string, string>
     */
    public function getTokenMap(): array
    {
        return $this->map?->all() ?? [];
    }

    /**
     * Dizi formatında çıktı
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'redacted_text' => $this->redactedText,
            'report_summary' => $this->reportSummary,
            'replacements_by_type' => $this->replacementsByType,
            'total_replacements' => $this->totalReplacements(),
            'risk_level' => $this->riskLevel,
            'warnings' => $this->warnings,
            'policy_id' => $this->policyId,
            'policy_version' => $this->policyVersion,
            'applied_rules' => $this->appliedRules,
            'requires_human_review' => $this->requiresHumanReview,
            'token_map' => $this->getTokenMap(),
            'spans' => array_map(fn (RedactionSpan $s) => $s->toArray(), $this->spans),
        ];
    }
}
