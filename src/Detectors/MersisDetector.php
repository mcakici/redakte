<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;

final class MersisDetector implements DetectorInterface
{
    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->isEntityAllowed('MERSIS')) {
            return [];
        }

        $detections = [];

        // 16 haneli 0 ile başlayan MERSİS numarası
        $pattern = '/(?<!\d)0\d{15}(?!\d)/u';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $raw = $match[0][0];
            $start = (int) $match[0][1];
            $end = $start + strlen($raw);

            if ($context->overlapsExclude($start, $end)) {
                continue;
            }

            // Çevreleyen bağlamda MERSİS etiketi var mı?
            $beforeText = substr($text, max(0, $start - 30), min(30, $start));
            $hasLabel = (bool) preg_match('/(?:MERSİS|Mersis\s*(?:No(?:su)?)?)\s*[:\-\/]?\s*$/ui', $beforeText);

            $evidences = [DetectionEvidence::FORMAT_MATCH->value];
            if ($hasLabel) {
                $evidences[] = DetectionEvidence::LABEL_MATCH->value;
            }

            $confidence = $hasLabel ? 0.95 : 0.80;

            $detections[] = new Detection(
                startOffset: $start,
                endOffset: $end,
                entityType: 'MERSIS',
                originalValue: $raw,
                normalizedValue: $raw,
                confidence: $confidence,
                ruleId: 'MERSIS_16',
                validationStatus: 'valid',
                evidences: $evidences,
                priority: $hasLabel ? 92 : 88,
            );
        }

        return $detections;
    }
}
