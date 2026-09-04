<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;
use Redakte\Normalization\Canonicalizers\IbanCanonicalizer;
use Redakte\Validators\IbanMod97Validator;

final class IbanDetector implements DetectorInterface
{
    private IbanCanonicalizer $canonicalizer;
    private IbanMod97Validator $validator;

    public function __construct(
        ?IbanCanonicalizer $canonicalizer = null,
        ?IbanMod97Validator $validator = null,
    ) {
        $this->canonicalizer = $canonicalizer ?? new IbanCanonicalizer();
        $this->validator = $validator ?? new IbanMod97Validator();
    }

    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->isEntityAllowed('IBAN')) {
            return [];
        }

        $detections = [];

        // TR + 24 karakter (toplam 26), aralarda boşluk, NBSP, tire olabilir
        $pattern = '/(?<![a-zA-Z0-9])TR[\s\-\x{00A0}]*(?:\d[\s\-\x{00A0}]*){24}(?![a-zA-Z0-9])/ui';

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

            $canonical = $this->canonicalizer->canonicalize($raw);
            if (strlen($canonical) !== 26) {
                continue;
            }

            // Çevreleyen bağlamda IBAN etiketi var mı?
            $beforeText = substr($text, max(0, $start - 25), min(25, $start));
            $hasLabel = (bool) preg_match('/(?:IBAN|Hesap\s*(?:No)?)\s*[:\-\/]?\s*$/ui', $beforeText);

            $isValid = $this->validator->isValid($canonical);

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
                $confidence = $hasLabel ? 0.9 : 0.6;
                $status = 'invalid';
            }

            $detections[] = new Detection(
                startOffset: $start,
                endOffset: $end,
                entityType: 'IBAN',
                originalValue: $raw,
                normalizedValue: $canonical,
                confidence: $confidence,
                ruleId: 'IBAN_MOD97',
                validationStatus: $status,
                evidences: $evidences,
                priority: 100,
            );
        }

        return $detections;
    }
}
