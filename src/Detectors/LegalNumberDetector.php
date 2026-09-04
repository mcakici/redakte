<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;

final class LegalNumberDetector implements DetectorInterface
{
    public function detect(string $text, DetectionContext $context): array
    {
        $detections = [];

        // 1. ESAS_NO: Örn: "2023/123 E.", "Esas No: 2023/456"
        if ($context->isEntityAllowed('ESAS_NO')) {
            $pattern = '/(?J)\b(?:Esas\s*No\s*[:\-\/]\s*)(?<num>\d{4}\s*[\/-]\s*\d+)\b|\b(?<num>\d{4}\s*[\/-]\s*\d+)\s*(?:E\.|Esas)\b/ui';
            if (preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) !== false) {
                foreach ($m as $match) {
                    $raw = $match[0][0];
                    $start = (int) $match[0][1];
                    $end = $start + strlen($raw);
                    if ($context->overlapsExclude($start, $end)) {
                        continue;
                    }

                    $detections[] = new Detection(
                        startOffset: $start,
                        endOffset: $end,
                        entityType: 'ESAS_NO',
                        originalValue: $raw,
                        normalizedValue: preg_replace('/\s+/', '', $raw) ?? $raw,
                        confidence: 0.90,
                        ruleId: 'LEGAL_ESAS_NO',
                        validationStatus: 'valid',
                        evidences: [DetectionEvidence::LABEL_MATCH->value],
                        priority: 75,
                    );
                }
            }
        }

        // 2. KARAR_NO: Örn: "2023/789 K.", "Karar No: 2023/789"
        if ($context->isEntityAllowed('KARAR_NO')) {
            $pattern = '/(?J)\b(?:Karar\s*No\s*[:\-\/]\s*)(?<num>\d{4}\s*[\/-]\s*\d+)\b|\b(?<num>\d{4}\s*[\/-]\s*\d+)\s*(?:K\.|Karar)\b/ui';
            if (preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) !== false) {
                foreach ($m as $match) {
                    $raw = $match[0][0];
                    $start = (int) $match[0][1];
                    $end = $start + strlen($raw);
                    if ($context->overlapsExclude($start, $end)) {
                        continue;
                    }

                    $detections[] = new Detection(
                        startOffset: $start,
                        endOffset: $end,
                        entityType: 'KARAR_NO',
                        originalValue: $raw,
                        normalizedValue: preg_replace('/\s+/', '', $raw) ?? $raw,
                        confidence: 0.90,
                        ruleId: 'LEGAL_KARAR_NO',
                        validationStatus: 'valid',
                        evidences: [DetectionEvidence::LABEL_MATCH->value],
                        priority: 74,
                    );
                }
            }
        }

        // 3. DOSYA_NO: Örn: "Dosya No: 2024/5432"
        if ($context->isEntityAllowed('DOSYA_NO')) {
            $pattern = '/\b(?:Dosya\s*No\s*[:\-\/]\s*)(?<num>\d{4}\s*[\/-]\s*\d+|\d{5,12})\b/ui';
            if (preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) !== false) {
                foreach ($m as $match) {
                    $raw = $match[0][0];
                    $start = (int) $match[0][1];
                    $end = $start + strlen($raw);
                    if ($context->overlapsExclude($start, $end)) {
                        continue;
                    }

                    $detections[] = new Detection(
                        startOffset: $start,
                        endOffset: $end,
                        entityType: 'DOSYA_NO',
                        originalValue: $raw,
                        normalizedValue: preg_replace('/\s+/', '', $raw) ?? $raw,
                        confidence: 0.90,
                        ruleId: 'LEGAL_DOSYA_NO',
                        validationStatus: 'valid',
                        evidences: [DetectionEvidence::LABEL_MATCH->value],
                        priority: 73,
                    );
                }
            }
        }

        return $detections;
    }
}
