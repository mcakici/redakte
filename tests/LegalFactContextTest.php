<?php

declare(strict_types=1);

namespace Redakte\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redakte\Support\LegalFactContext;
use Redakte\Support\RedactionSnapshotRepair;

class LegalFactContextTest extends TestCase
{
    #[DataProvider('genericFacts')]
    public function test_general_legal_facts_are_preserved(string $value, string $type): void
    {
        self::assertTrue(LegalFactContext::isFalsePositive($value, 0, strlen($value), $type));
    }

    public static function genericFacts(): array
    {
        return [['3 yıl', 'KISI'], ['21 gün', 'DIGER_KISISEL'], ['3 YIL 21 GÜN', 'CEZA_BILGISI'],
            ['3 yıl, 21 gün', 'ADRES'], ['12.05.2026', 'DOGUM_TARIHI'], ['2026', 'KISI'],
            ['Türk Borçlar Kanunu', 'KISI'], ['İş Kanunu', 'KISI'], ['Kira Sözleşmesi', 'KISI'],
            ['BELİRSİZ SÜRELİ İŞ SÖZLEŞMESİ', 'DIGER_KISISEL'], ['2026-11-15', 'DOGUM_TARIHI'],
            ['15 Kasım 2026', 'DOGUM_TARIHI']];
    }

    #[DataProvider('birthContexts')]
    public function test_real_birth_dates_still_mask(string $source): void
    {
        $start = strpos($source, '12.05.1980');
        self::assertFalse(LegalFactContext::isFalsePositive($source, $start, $start + 10, 'DOGUM_TARIHI'));
    }

    public static function birthContexts(): array
    {
        return [['Doğum tarihi: 12.05.1980'], ['D.T.: 12.05.1980'], ['12.05.1980 doğumlu Ayşe Demir'],
            ['DOĞUM TARİHİ: 12.05.1980'], ['DOĞUM YERİ VE TARİHİ: 12.05.1980'], ['D.TARİHİ: 12.05.1980']];
    }

    public function test_real_identifiers_and_personalized_contract_parties_are_not_excluded(): void
    {
        foreach ([['2026', 'HESAP_NO'], ['21gün', 'KULLANICI_ADI'], ['Ayşe Demir', 'KISI'],
            ['İstanbul', 'KONUM'], ['Ziraat Bankası', 'BANKA'], ['Ayşe Demir Kira Sözleşmesi', 'KISI']] as [$value, $type]) {
            self::assertFalse(LegalFactContext::isFalsePositive($value, 0, strlen($value), $type));
        }
    }

    public function test_legacy_fact_tokens_are_repaired_without_revealing_other_identifiers(): void
    {
        $source = 'Ceza: 3 yıl. Davacı: Ayşe Demir. Tebliğ: 12.05.2026. Doğum tarihi: 12.05.1980.';
        $snapshot = ['hash' => hash('sha256', $source), 'text' => 'Ceza: duration. Davacı: person. Tebliğ: event. Doğum tarihi: birth.',
            'map' => ['duration' => '3 yıl', 'person' => 'Ayşe Demir', 'event' => '12.05.2026', 'birth' => '12.05.1980'],
            'types' => ['duration' => 'KISI', 'person' => 'KISI', 'event' => 'DOGUM_TARIHI', 'birth' => 'DOGUM_TARIHI'], 'spans' => []];
        foreach ($snapshot['map'] as $token => $value) {
            $start = strpos($source, $value);
            $snapshot['spans'][] = ['start' => $start, 'end' => $start + strlen($value), 'token' => $token];
        }
        self::assertSame($snapshot, RedactionSnapshotRepair::apply($snapshot, $source));
        $fixed = RedactionSnapshotRepair::apply($snapshot, $source, preserveLegalFacts: true);
        self::assertSame('Ceza: 3 yıl. Davacı: person. Tebliğ: 12.05.2026. Doğum tarihi: birth.', $fixed['text']);
        self::assertSame(['person' => 'Ayşe Demir', 'birth' => '12.05.1980'], $fixed['map']);
    }
}
