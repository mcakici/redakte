<?php

declare(strict_types=1);

namespace Redakte\Support;

/** Narrow, deterministic exclusions for legal analysis; never a general PII bypass. */
final class LegalFactContext
{
    public static function isFalsePositive(string $source, int $start, int $end, string $type): bool
    {
        $value = mb_strtolower(strtr(trim(substr($source, $start, $end - $start)), ['İ' => 'i', 'I' => 'ı']), 'UTF-8');
        if (!in_array($type, ['KISI', 'DOGUM_TARIHI', 'DIGER_KISISEL', 'KONUM', 'ADRES', 'PLAKA', 'BANKA', 'CEZA_BILGISI'], true)) {
            return false;
        }
        if (preg_match('/\A\d+(?:[.,]\d+)?\s+(?:yıl|sene|ay|hafta|gün|saat|dakika|saniye)(?:lık|lik|luk|lük)?(?:\s*(?:,|ve|ile)?\s*\d+(?:[.,]\d+)?\s+(?:yıl|sene|ay|hafta|gün|saat|dakika|saniye)(?:lık|lik|luk|lük)?)*\z/ui', $value) === 1) {
            return true;
        }

        // These exclusions apply to broad/contextual labels, never identifiers
        // such as a customer/account/identity number that happens to look like a date.
        if (preg_match('/\A(?:\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}|(?:19|20)\d{2})\z/u', $value) === 1) {
            $before = mb_strcut(substr($source, 0, $start), -100, null, 'UTF-8');
            $after = mb_strcut(substr($source, $end), 0, 40, 'UTF-8');
            $birth = preg_match('/(?:doğum(?:\s+tarihi|\s+günü)?|d\.?\s*t\.?|doğdu(?:ğu)?)\s*[:=\-]?\s*\z/ui', $before) === 1
                || preg_match('/\A\s*(?:tarihinde\s+)?doğ(?:umlu|du|muş)/ui', $after) === 1;

            return !$birth;
        }

        return preg_match('/\A(?:(?:türk\s+(?:borçlar|medeni|ceza|ticaret)|hukuk\s+muhakemeleri|ceza\s+muhakemesi|iş|icra\s+ve\s+iflas)\s+kanunu|anayasa|(?:(?:belirli|belirsiz)\s+süreli\s+)?(?:iş|kira|konut\s+kira|satış|taşınmaz\s+satış|hizmet|eser|vek[aâ]let|gizlilik|işbirliği)\s+sözleşmesi)\z/u', $value) === 1;
    }
}
