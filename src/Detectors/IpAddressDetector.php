<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;

final class IpAddressDetector implements DetectorInterface
{
    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->isEntityAllowed('IP_ADRESI')) {
            return [];
        }

        $detections = [];

        // IPv4 regex
        $pattern = '/(?<![0-9.])(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(?![0-9.])/u';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) !== false) {
            foreach ($matches as $m) {
                $raw = $m[0][0];
                $start = (int) $m[0][1];
                $end = $start + strlen($raw);

                if ($context->overlapsExclude($start, $end)) {
                    continue;
                }

                $detections[] = new Detection(
                    startOffset: $start,
                    endOffset: $end,
                    entityType: 'IP_ADRESI',
                    originalValue: $raw,
                    normalizedValue: $raw,
                    confidence: 0.95,
                    ruleId: 'IP_V4',
                    validationStatus: 'valid',
                    evidences: [DetectionEvidence::FORMAT_MATCH->value],
                    priority: 68,
                );
            }
        }

        return $detections;
    }
}
