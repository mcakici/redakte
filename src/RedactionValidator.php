<?php

declare(strict_types=1);

namespace Redakte;

/**
 * İkinci geçiş doğrulayıcı — Metinde kalan potansiyel veri sızıntılarını ve
 * hassas kelimeleri (çocuk, sağlık, şiddet vb.) kontrol eder.
 */
class RedactionValidator
{
    public function __construct(
        private PatternRegistry $registry,
    ) {}

    public function validate(string $originalText, RedactionResult $result): RedactionResult
    {
        $warnings = $result->warnings;
        $requiresHumanReview = $result->requiresHumanReview;
        $riskLevel = $result->riskLevel;

        // Kalan örüntü kontrolü — redakte metinde hâlâ yakalanmamış pattern kaldı mı?
        foreach ($this->registry->getPatterns() as $pattern) {
            $regex = $pattern['regex'] ?? '';
            if ($regex === '') {
                continue;
            }
            if (preg_match_all($regex, $result->redactedText, $m) && count($m[0]) > 0) {
                $warnings[] = 'Kalan olası ' . ($pattern['entity_type'] ?? 'PII') . ' örüntüsü tespit edildi; manuel kontrol önerilir.';
                $requiresHumanReview = true;
            }
        }

        // İnsan incelemesi anahtar kelimeleri
        $keywords = $this->registry->getHumanReviewKeywords();
        $lower = mb_strtolower($originalText, 'UTF-8');
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                $riskLevel = $riskLevel === 'high' ? 'high' : 'medium';
                $requiresHumanReview = true;
                $warnings[] = "Hassas bağlam tespit edildi ({$kw}); insan incelemesi önerilir.";
                break;
            }
        }

        return new RedactionResult(
            spans: $result->spans,
            redactedText: $result->redactedText,
            replacementsByType: $result->replacementsByType,
            reportSummary: $result->reportSummary,
            riskLevel: $riskLevel,
            warnings: array_values(array_unique($warnings)),
            policyId: $result->policyId,
            policyVersion: $result->policyVersion,
            appliedRules: $result->appliedRules,
            requiresHumanReview: $requiresHumanReview,
            map: $result->map,
        );
    }
}
