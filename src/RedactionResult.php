<?php

declare(strict_types=1);

namespace Redakte;

use JsonSerializable;
use Redakte\Exceptions\UnsafeUnmaskException;
use Redakte\Result\RedactionWarning;
use Redakte\Result\SafeSpan;

/**
 * Redaksiyon işleminin sonucu — redakte metin, span listesi, rapor özeti, token haritası ve metadata.
 *
 * GÜVENLİK İLKESİ (P0-01):
 * toArray() ve jsonSerialize() metotları varsayılan olarak hassas verileri (token_map ve original_value)
 * ASLA sızdırmaz. Orijinal hassas haritaya yalnızca açıkça toSensitiveArray() çağrılarak erişilebilir.
 */
final class RedactionResult implements JsonSerializable
{
    public function __construct(
        /** @var list<RedactionSpan> */
        public array $spans,
        public string $redactedText,
        /** @var array<string, int> entity_type => adet */
        public array $replacementsByType,
        public string $reportSummary,
        public string $riskLevel = 'low',
        /** @var list<string|RedactionWarning> */
        public array $warnings = [],
        public string $policyId = '',
        public string $policyVersion = '',
        /** @var list<string> */
        public array $appliedRules = [],
        public bool $requiresHumanReview = false,
        public ?RedactionMap $map = null,
        public string $status = 'complete', // 'complete' | 'review_required' | 'failed'
        public ?MaskStrategy $strategy = MaskStrategy::TAG,
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
     * Token içeren bir metni bu sonucun token haritası ile geri çözer.
     * Strateji TAG değilse güvenlik gereği UnsafeUnmaskException fırlatır.
     *
     * @throws UnsafeUnmaskException
     */
    public function unmask(string $text): string
    {
        if ($this->strategy !== null && $this->strategy !== MaskStrategy::TAG) {
            throw new UnsafeUnmaskException(
                sprintf('Yalnızca TAG (çift yönlü) stratejisinde unmask işlemi yapılabilir. Mevcut strateji: %s', $this->strategy->value)
            );
        }

        return $this->map?->unmask($text) ?? $text;
    }

    /**
     * Token => orijinal değer eşleşmelerini döndürür.
     * Dikkat: Bu metot hassas verileri doğrudan döner.
     *
     * @return array<string, string>
     */
    public function getTokenMap(): array
    {
        return $this->map?->all() ?? [];
    }

    /**
     * Hassas verileri içermeyen güvenli span listesi
     *
     * @return list<SafeSpan>
     */
    public function getSafeSpans(): array
    {
        return array_map(fn (RedactionSpan $s) => new SafeSpan(
            startOffset: $s->startOffset,
            endOffset: $s->endOffset,
            entityType: $s->entityType,
            replacement: $s->replacement,
            confidence: $s->confidence,
            ruleId: $s->source,
        ), $this->spans);
    }

    /**
     * Varsayılan güvenli dizi çıktısı (P0-01).
     * Hassas verileri (token_map ve original_value) sızdırmaz.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->toSafeArray();
    }

    /**
     * Açık güvenli çıktı metodu
     *
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        $warningsOutput = [];
        foreach ($this->warnings as $w) {
            $warningsOutput[] = $w instanceof RedactionWarning ? $w->toSafeArray() : $w;
        }

        return [
            'status' => $this->status,
            'redacted_text' => $this->redactedText,
            'report_summary' => $this->reportSummary,
            'replacements_by_type' => $this->replacementsByType,
            'total_replacements' => $this->totalReplacements(),
            'risk_level' => $this->riskLevel,
            'warnings' => $warningsOutput,
            'policy_id' => $this->policyId,
            'policy_version' => $this->policyVersion,
            'applied_rules' => $this->appliedRules,
            'requires_human_review' => $this->requiresHumanReview,
            'spans' => array_map(fn (RedactionSpan $s) => $s->toSafeArray(), $this->spans),
        ];
    }

    /**
     * Orijinal hassas değerleri ve token haritasını içeren açık çıktı (P0-01).
     * Yalnızca bilinçli ve güvenli alanlarda çağrılmalıdır.
     *
     * @return array<string, mixed>
     */
    public function toSensitiveArray(): array
    {
        $safe = $this->toSafeArray();
        $safe['token_map'] = $this->getTokenMap();
        $safe['spans'] = array_map(fn (RedactionSpan $s) => $s->toSensitiveArray(), $this->spans);

        return $safe;
    }

    /**
     * json_encode($result) çağrıldığında yalnızca güvenli alanlar serileştirilir.
     */
    public function jsonSerialize(): array
    {
        return $this->toSafeArray();
    }
}
