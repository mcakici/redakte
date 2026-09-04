<?php

declare(strict_types=1);

namespace Redakte;

use Redakte\Redactors\ModelIdentityRedactor;
use Redakte\Redactors\NameRedactor;

/**
 * Redakte paketinin ana orkestrasyon ve giriş kapısı (Gateway / Manager) sınıfı.
 *
 * Tüm redaksiyon akışını (İsim-soyisim, TCKN, VKN, IBAN, telefon, e-posta vb.)
 * koordine eder ve nihai RedactionResult nesnesini üretir.
 */
class Redactor
{
    public function __construct(
        private RedactionService $service,
        private NameRedactor $nameRedactor,
        private RedactionValidator $validator,
        private ReportSummaryBuilder $reportBuilder,
        private ?ModelIdentityRedactor $modelIdentityRedactor = null,
    ) {
        $this->modelIdentityRedactor ??= new ModelIdentityRedactor();
    }

    /**
     * Metni tarayarak hassas kişisel verileri redakte eder.
     *
     * @param string $text Redakte edilecek ham metin
     * @param RedactionOptions|array<string, mixed>|null $options Ayarlar
     */
    public function redact(string $text, RedactionOptions|array|null $options = null): RedactionResult
    {
        $options = $this->resolveOptions($options);
        $map = new RedactionMap();

        $nameResult = ['text' => $text, 'replacementsByType' => []];
        $textForPatterns = $text;
        $nameToIndex = [];
        $nameCounter = 0;
        $nameSpans = [];

        // 1. Aşama: İsim-Soyisim ve Rol redaksiyonu (aktifse)
        if ($options->redactNames && ($options->entityTypes === null || in_array('KISI', $options->entityTypes, true))) {
            $nameResult = $this->nameRedactor->redact(
                text: $text,
                nameToIndex: $nameToIndex,
                counter: $nameCounter,
                strategy: $options->strategy,
                map: $map,
                spans: $nameSpans,
            );
            $textForPatterns = $nameResult['text'];
        }

        // 2. Aşama: Regex desenleri (TCKN, VKN, IBAN, Telefon, E-posta, Plaka, Kredi Kartı vb.)
        $result = $this->service->redact($textForPatterns, $options, $map);

        // Span listelerini başlangıç ofsetine göre birleştir
        $mergedSpans = array_merge($nameSpans, $result->spans);
        usort($mergedSpans, fn (RedactionSpan $a, RedactionSpan $b) => $a->startOffset <=> $b->startOffset);

        // Değişiklik sayılarını birleştir
        $mergedReplacements = $result->replacementsByType;
        foreach ($nameResult['replacementsByType'] as $type => $count) {
            if ($count > 0) {
                $mergedReplacements[$type] = ($mergedReplacements[$type] ?? 0) + $count;
            }
        }

        $reportSummary = $this->reportBuilder->build($mergedReplacements);
        $appliedRules = $result->appliedRules;
        if (($nameResult['replacementsByType']['KISI'] ?? 0) > 0) {
            $appliedRules[] = 'name_and_role_patterns';
        }

        $combinedResult = new RedactionResult(
            spans: $mergedSpans,
            redactedText: $result->redactedText,
            replacementsByType: $mergedReplacements,
            reportSummary: $reportSummary,
            riskLevel: $result->riskLevel,
            warnings: $result->warnings,
            policyId: $result->policyId,
            policyVersion: $result->policyVersion,
            appliedRules: array_values(array_unique($appliedRules)),
            requiresHumanReview: $result->requiresHumanReview,
            map: $map,
        );

        // 3. Aşama: İkinci geçiş ve sızıntı denetimi
        if ($options->runValidator) {
            $combinedResult = $this->validator->validate($text, $combinedResult);
        }

        return $combinedResult;
    }

    /**
     * Sadece redakte edilmiş temiz metni döndürür.
     */
    public function clean(string $text, RedactionOptions|array|null $options = null): string
    {
        return $this->redact($text, $options)->redactedText;
    }

    /**
     * Kısmi (okunabilir) maskelenmiş temiz metni döndürür.
     * Örn: 123*****890, TR33 **** 12, A**** Y*****
     */
    public function partial(string $text, RedactionOptions|array|null $options = null): string
    {
        $resolved = $this->resolveOptions($options);
        $partialOptions = new RedactionOptions(
            mode: $resolved->mode,
            entityTypes: $resolved->entityTypes,
            runValidator: $resolved->runValidator,
            redactNames: $resolved->redactNames,
            checkHumanReview: $resolved->checkHumanReview,
            strategy: MaskStrategy::PARTIAL,
        );

        return $this->redact($text, $partialOptions)->redactedText;
    }

    /**
     * LLM / Yapay Zeka promptları için güvenli çift yönlü (reversible) maskeleme yapar.
     *
     * @return array{0: string, 1: RedactionMap, text: string, map: RedactionMap}
     */
    public function maskForLLM(string $text, RedactionOptions|array|null $options = null): array
    {
        $resolved = $this->resolveOptions($options);
        $tagOptions = new RedactionOptions(
            mode: $resolved->mode,
            entityTypes: $resolved->entityTypes,
            runValidator: $resolved->runValidator,
            redactNames: $resolved->redactNames,
            checkHumanReview: $resolved->checkHumanReview,
            strategy: MaskStrategy::TAG,
        );

        $result = $this->redact($text, $tagOptions);
        $map = $result->map ?? new RedactionMap();

        return [
            0 => $result->redactedText,
            1 => $map,
            'text' => $result->redactedText,
            'map' => $map,
        ];
    }

    /**
     * Token içeren bir metni haritadaki orijinal verileriyle takas ederek çözer.
     */
    public function unmask(string $text, RedactionMap|array $map): string
    {
        if (!$map instanceof RedactionMap) {
            $map = RedactionMap::fromArray($map);
        }

        return $map->unmask($text);
    }

    /**
     * Yapay zeka model kimliği sızıntılarını temizler
     *
     * @return array{text: string, replaced: int}
     */
    public function redactModelIdentity(string $text): array
    {
        return $this->modelIdentityRedactor->redact($text);
    }

    /**
     * Seçenekleri normalize eder
     */
    private function resolveOptions(RedactionOptions|array|null $options): RedactionOptions
    {
        if ($options instanceof RedactionOptions) {
            return $options;
        }

        if (is_array($options)) {
            return new RedactionOptions(
                mode: $options['mode'] ?? 'redacted',
                entityTypes: $options['only'] ?? $options['entity_types'] ?? null,
                runValidator: $options['validator'] ?? true,
                redactNames: $options['names'] ?? true,
                checkHumanReview: $options['human_review'] ?? true,
                strategy: $options['strategy'] ?? MaskStrategy::TAG,
            );
        }

        return new RedactionOptions();
    }
}
