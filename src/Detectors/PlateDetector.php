<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;
use Redakte\Normalization\Canonicalizers\PlateCanonicalizer;
use Redakte\Support\PlateContext;

final class PlateDetector implements DetectorInterface
{
    private PlateCanonicalizer $canonicalizer;

    public function __construct(?PlateCanonicalizer $canonicalizer = null)
    {
        $this->canonicalizer = $canonicalizer ?? new PlateCanonicalizer();
    }

    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->isEntityAllowed('PLAKA')) {
            return [];
        }

        $detections = [];

        if (preg_match_all(PlateContext::pattern(), $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $raw = $match[0][0];
            $start = (int) $match[0][1];
            $end = $start + strlen($raw);

            [$originalStart, $originalEnd] = $context->normalizedText->mapSpanToOriginal($start, $end);
            if ($context->overlapsExclude($originalStart, $originalEnd)) {
                continue;
            }

            if (preg_match('/\A(\d{2})[\s-]*([A-Za-z]{1,3})[\s-]*(\d{2,4})\z/u', $raw, $parts) !== 1) {
                continue;
            }
            $provinceCode = (int) $parts[1];
            $letters = $parts[2];
            $digits = $parts[3];
            $canonical = sprintf('%02d %s %s', $provinceCode, strtoupper($letters), $digits);

            // Çevreleyen bağlamda plaka etiketi var mı?
            $beforeText = substr($text, max(0, $start - 25), min(25, $start));
            $hasLabel = (bool) preg_match('/(?:Plaka(?:sı)?|Araç)\s*[:\-\/]?\s*$/ui', $beforeText);

            $evidences = [DetectionEvidence::FORMAT_MATCH->value];
            if ($hasLabel) {
                $evidences[] = DetectionEvidence::LABEL_MATCH->value;
            }

            $isCompact = !str_contains($raw, ' ') && !str_contains($raw, '-');
            $confidence = $hasLabel ? 0.95 : ($isCompact ? 0.70 : 0.85);

            $detections[] = new Detection(
                startOffset: $start,
                endOffset: $end,
                entityType: 'PLAKA',
                originalValue: $raw,
                normalizedValue: $canonical,
                confidence: $confidence,
                ruleId: 'PLAKA_CLASSIC',
                validationStatus: 'not_checked',
                evidences: $evidences,
                priority: 60,
            );
        }

        return $detections;
    }
}
