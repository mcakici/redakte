<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;
use Redakte\Normalization\Canonicalizers\DigitCanonicalizer;
use Redakte\Validators\LuhnChecksumValidator;

final class CardDetector implements DetectorInterface
{
    private DigitCanonicalizer $canonicalizer;
    private LuhnChecksumValidator $validator;

    public function __construct(
        ?DigitCanonicalizer $canonicalizer = null,
        ?LuhnChecksumValidator $validator = null,
    ) {
        $this->canonicalizer = $canonicalizer ?? new DigitCanonicalizer();
        $this->validator = $validator ?? new LuhnChecksumValidator();
    }

    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->isEntityAllowed('KREDI_KARTI')) {
            return [];
        }

        $detections = [];

        // 13-19 hane aralığında gruplu veya bitişik kart numaraları
        // Örn: 4532 1234 5678 9012, 4532-1234-5678-9012, 16 haneli bitişik
        $pattern = '/(?<!\d)(?:\d{4}[ -]\d{4}[ -]\d{4}[ -]\d{1,7}|\d{13,19})(?!\d)/u';

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

            $digits = $this->canonicalizer->canonicalize($raw);
            $digitCount = strlen($digits);
            if ($digitCount < 13 || $digitCount > 19) {
                continue;
            }

            // Çevreleyen bağlamda kart etiketi var mı?
            $beforeText = substr($text, max(0, $start - 35), min(35, $start));
            $hasLabel = (bool) preg_match('/(?:Kredi\s*Kartı|Kart\s*No(?:su)?|Card\s*Number|PAN)\s*[:\-\/]?\s*$/ui', $beforeText);

            $isValid = $this->validator->isValid($digits);

            $evidences = [DetectionEvidence::FORMAT_MATCH->value];
            if ($hasLabel) {
                $evidences[] = DetectionEvidence::LABEL_MATCH->value;
            }

            if ($isValid) {
                $evidences[] = DetectionEvidence::CHECKSUM_VALID->value;
                $confidence = 1.0;
                $status = 'valid';
            } else {
                $evidences[] = DetectionEvidence::CHECKSUM_INVALID->value;
                $confidence = $hasLabel ? 0.85 : 0.45;
                $status = 'invalid';
            }

            $detections[] = new Detection(
                startOffset: $start,
                endOffset: $end,
                entityType: 'KREDI_KARTI',
                originalValue: $raw,
                normalizedValue: $digits,
                confidence: $confidence,
                ruleId: 'KREDI_KARTI_LUHN',
                validationStatus: $status,
                evidences: $evidences,
                priority: 98,
            );
        }

        return $detections;
    }
}
