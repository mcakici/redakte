<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;
use Redakte\Exceptions\PatternCompilationException;
use Redakte\PatternRegistry;
use Redakte\Validators\IbanMod97Validator;
use Redakte\Validators\LuhnChecksumValidator;
use Redakte\Validators\TcknChecksumValidator;
use Redakte\Validators\VknChecksumValidator;

final class CustomPatternDetector implements DetectorInterface
{
    public function __construct(
        private readonly PatternRegistry $registry,
        private readonly ?TcknChecksumValidator $tcknValidator = null,
        private readonly ?IbanMod97Validator $ibanValidator = null,
        private readonly ?VknChecksumValidator $vknValidator = null,
        private readonly ?LuhnChecksumValidator $luhnValidator = null,
    ) {}

    public function detect(string $text, DetectionContext $context): array
    {
        $detections = [];

        // Yerleşik dedektörlerin kapsamadığı veya kullanıcı tarafından eklenen pattern'ler
        $builtinRuleIds = [
            'TCKN_CHECKSUM', 'VKN_10', 'IBAN_MOD97', 'KREDI_KARTI_LUHN',
            'MERSIS_16', 'TELEFON_TR', 'TELEFON_5XX', 'TELEFON_LANDLINE',
            'TELEFON_COMPACT', 'EPOSTA_RFC', 'PLAKA_CLASSIC',
        ];

        foreach ($this->registry->getPatterns() as $pattern) {
            $ruleId = $pattern['id'] ?? '';
            if (in_array($ruleId, $builtinRuleIds, true)) {
                continue; // Yerleşik özel dedektörler daha gelişmiş mantıkla çalışır
            }

            $entityType = $pattern['entity_type'] ?? '';
            if (!$context->isEntityAllowed($entityType)) {
                continue;
            }

            $regex = $pattern['regex'] ?? '';
            if ($regex === '') {
                continue;
            }

            $matches = [];
            $res = @preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

            $pcreErr = preg_last_error();
            if ($pcreErr !== PREG_NO_ERROR) {
                $errMsg = function_exists('preg_last_error_msg') ? preg_last_error_msg() : 'PCRE hatası: ' . $pcreErr;
                throw new PatternCompilationException(sprintf('Desen çalıştırılırken PCRE hatası oluştu (%s): %s', $ruleId, $errMsg));
            }

            if ($res === false || empty($matches)) {
                continue;
            }

            $validatorName = $pattern['validator'] ?? null;
            $priority = $pattern['priority'] ?? 50;

            foreach ($matches as $match) {
                $full = $match[0];
                $value = $full[0];
                $start = (int) $full[1];
                $end = $start + strlen($value);

                if ($context->overlapsExclude($start, $end)) {
                    continue;
                }

                $evidences = [DetectionEvidence::FORMAT_MATCH->value];

                if ($validatorName === null) {
                    $status = 'not_checked';
                    $confidence = 0.85;
                } else {
                    $isValid = $this->validate($value, $validatorName);
                    if ($isValid) {
                        $evidences[] = DetectionEvidence::CHECKSUM_VALID->value;
                        $status = 'valid';
                        $confidence = 1.0;
                    } else {
                        $evidences[] = DetectionEvidence::CHECKSUM_INVALID->value;
                        $status = 'invalid';
                        $confidence = 0.6;
                    }
                }

                $detections[] = new Detection(
                    startOffset: $start,
                    endOffset: $end,
                    entityType: $entityType,
                    originalValue: $value,
                    normalizedValue: preg_replace('/\s+/', '', $value) ?? $value,
                    confidence: $confidence,
                    ruleId: $ruleId,
                    validationStatus: $status,
                    evidences: $evidences,
                    priority: $priority,
                );
            }
        }

        return $detections;
    }

    private function validate(string $value, ?string $validator): bool
    {
        if ($validator === null) {
            return true;
        }

        return match ($validator) {
            'tckn_checksum' => ($this->tcknValidator ?? new TcknChecksumValidator())->isValid($value),
            'iban_mod97' => ($this->ibanValidator ?? new IbanMod97Validator())->isValid($value),
            'vkn_checksum' => ($this->vknValidator ?? new VknChecksumValidator())->isValid($value),
            'luhn_checksum' => ($this->luhnValidator ?? new LuhnChecksumValidator())->isValid($value),
            default => true,
        };
    }
}
