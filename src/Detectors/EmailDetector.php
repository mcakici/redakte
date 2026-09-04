<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;
use Redakte\Normalization\Canonicalizers\EmailCanonicalizer;

final class EmailDetector implements DetectorInterface
{
    private EmailCanonicalizer $canonicalizer;

    public function __construct(?EmailCanonicalizer $canonicalizer = null)
    {
        $this->canonicalizer = $canonicalizer ?? new EmailCanonicalizer();
    }

    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->isEntityAllowed('EPOSTA')) {
            return [];
        }

        $detections = [];

        // E-posta regex'i
        $pattern = '/(?<![a-zA-Z0-9._%+-])[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(?![a-zA-Z0-9])/u';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $raw = $match[0][0];
            $start = (int) $match[0][1];

            // Cümle sonundaki noktalama işaretlerini (., ;, :, !) e-posta span'ından çıkar
            $trimmed = rtrim($raw, '.,;:!?');
            $end = $start + strlen($trimmed);

            if ($context->overlapsExclude($start, $end)) {
                continue;
            }

            $isValid = filter_var($trimmed, FILTER_VALIDATE_EMAIL) !== false;
            $canonical = $this->canonicalizer->canonicalize($trimmed);

            $detections[] = new Detection(
                startOffset: $start,
                endOffset: $end,
                entityType: 'EPOSTA',
                originalValue: $trimmed,
                normalizedValue: $canonical,
                confidence: $isValid ? 1.0 : 0.6,
                ruleId: 'EPOSTA_RFC',
                validationStatus: $isValid ? 'valid' : 'invalid',
                evidences: [DetectionEvidence::FORMAT_MATCH->value],
                priority: 70,
            );
        }

        return $detections;
    }
}
