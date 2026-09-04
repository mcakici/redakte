<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;

final class AddressDetector implements DetectorInterface
{
    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->isEntityAllowed('ADRES')) {
            return [];
        }

        $detections = [];

        // 1. Etiket tabanlı adres tespiti: "Adres: ...", "İkametgâh: ...", "Tebligat Adresi: ..."
        $labelPattern = '/\b(?<prefix>(?:Tebligat\s+)?(?:İkametg[aâ]h|Adres(?:i)?)\s*[:\-\/]\s*)(?<address>[^\r\n]{10,180})(?:\r?\n|$)/ui';
        if (preg_match_all($labelPattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $rawAddress = trim($match['address'][0]);
                $start = (int) $match['address'][1];
                $end = $start + strlen($rawAddress);

                // Satırda başlayan başka bir veri alanını (TCKN, VKN, Telefon vb.) yutmasını engelle
                if (preg_match('/\b(?:TCKN|T\.?C\.?|VKN|Vergi|Tel(?:efon)?|GSM|E-?posta|IBAN|MERS[İI]S|Dosya\s*No|Esas\s*No)\s*[:\-\/]/ui', $rawAddress, $cutMatch, PREG_OFFSET_CAPTURE)) {
                    $cutPos = (int) $cutMatch[0][1];
                    $rawAddress = rtrim(substr($rawAddress, 0, $cutPos), " \t,.-/");
                    $end = $start + strlen($rawAddress);
                }

                if ($rawAddress === '' || strlen($rawAddress) < 5 || $context->overlapsExclude($start, $end)) {
                    continue;
                }

                $detections[] = new Detection(
                    startOffset: $start,
                    endOffset: $end,
                    entityType: 'ADRES',
                    originalValue: $rawAddress,
                    normalizedValue: $rawAddress,
                    confidence: 0.95,
                    ruleId: 'ADDRESS_LABEL',
                    validationStatus: 'not_checked',
                    evidences: [DetectionEvidence::LABEL_MATCH->value],
                    priority: 85,
                );
            }
        }

        // 2. Bileşen tabanlı adres tespiti: Mahalle, Cadde, Sokak, No bileşenleri
        $componentPattern = '/\b(?:[A-Za-zÇĞİÖŞÜçğıöşü0-9\s.-]+(?:Mah(?:allesi|\.)?))\s*(?:[A-Za-zÇĞİÖŞÜçğıöşü0-9\s.-]+(?:Cad(?:desi|\.)?|Sok(?:ağı|\.)?|Blv\.?|Bulvarı))\s*(?:(?:No|Daire|Kat|D|K)\s*[:.\-]?\s*\d+[A-Za-z]?)(?:[A-Za-zÇĞİÖŞÜçğıöşü0-9\s\/\.,\-]+)?/ui';
        if (preg_match_all($componentPattern, $text, $compMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) !== false) {
            foreach ($compMatches as $m) {
                $raw = trim($m[0][0]);
                $start = (int) $m[0][1];
                $end = $start + strlen($raw);

                if (strlen($raw) < 15 || $context->overlapsExclude($start, $end)) {
                    continue;
                }

                $detections[] = new Detection(
                    startOffset: $start,
                    endOffset: $end,
                    entityType: 'ADRES',
                    originalValue: $raw,
                    normalizedValue: $raw,
                    confidence: 0.75,
                    ruleId: 'ADDRESS_COMPONENTS',
                    validationStatus: 'not_checked',
                    evidences: [DetectionEvidence::COMPONENT_MATCH->value],
                    priority: 60,
                );
            }
        }

        return $detections;
    }
}
