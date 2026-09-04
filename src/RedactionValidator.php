<?php

declare(strict_types=1);

namespace Redakte;

use Redakte\Result\RedactionWarning;

/**
 * İkinci geçiş doğrulayıcı — Metinde kalan potansiyel veri sızıntılarını ve
 * hassas kelimeleri (çocuk, sağlık, şiddet vb.) kontrol eder.
 */
class RedactionValidator
{
    public function __construct(
        private PatternRegistry $registry,
    ) {}

    public function validate(string $originalText, RedactionResult $result, ?RedactionOptions $options = null): RedactionResult
    {
        $warnings = $result->warnings;
        $requiresHumanReview = $result->requiresHumanReview;
        $riskLevel = $result->riskLevel;
        $status = $result->status;

        // 1. Kalan örüntü denetimi — redakte metinde sızan desen var mı?
        // (Token yer tutucuları ve maskelenmiş alanlar regex'e takılmamalı)
        $cleanRedacted = preg_replace('/⟦RDT:[^⟧]+⟧|\[[A-Z_]+_\d+\]|\[[A-Z_]+\]|\*{3,}/u', ' ', $result->redactedText) ?? $result->redactedText;

        foreach ($this->registry->getPatterns() as $pattern) {
            $regex = $pattern['regex'] ?? '';
            if ($regex === '') {
                continue;
            }

            $matches = [];
            if (@preg_match_all($regex, $cleanRedacted, $matches) && count($matches[0]) > 0) {
                $entityType = $pattern['entity_type'] ?? 'PII';
                $warnings[] = new RedactionWarning(
                    code: 'UNREDACTED_HIGH_RISK_CANDIDATE',
                    entityType: $entityType,
                    severity: 'high',
                    message: "Kalan olası {$entityType} örüntüsü tespit edildi; manuel kontrol önerilir.",
                );
                $requiresHumanReview = true;
                $riskLevel = 'high';
                $status = 'review_required';
            }
        }

        // 2. İnsan incelemesi anahtar kelimeleri (P0-05: checkHumanReview=false ise atla)
        $checkReview = $options?->checkHumanReview ?? true;
        if ($checkReview) {
            $keywords = $this->registry->getHumanReviewKeywords();
            $lower = mb_strtolower($originalText, 'UTF-8');
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $riskLevel = ($riskLevel === 'high') ? 'high' : 'medium';
                    $requiresHumanReview = true;
                    $warnings[] = new RedactionWarning(
                        code: 'SENSITIVE_CONTEXT_KEYWORD',
                        severity: 'medium',
                        message: "Hassas bağlam tespit edildi ({$kw}); insan incelemesi önerilir.",
                    );
                    break;
                }
            }
        }

        return new RedactionResult(
            spans: $result->spans,
            redactedText: $result->redactedText,
            replacementsByType: $result->replacementsByType,
            reportSummary: $result->reportSummary,
            riskLevel: $riskLevel,
            warnings: $warnings,
            policyId: $result->policyId,
            policyVersion: $result->policyVersion,
            appliedRules: $result->appliedRules,
            requiresHumanReview: $requiresHumanReview,
            map: $result->map,
            status: $status,
            strategy: $result->strategy,
        );
    }
}
