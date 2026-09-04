<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\Redakte;

class CoordinateIntegrityTest extends TestCase
{
    public function test_all_spans_match_original_text_exact_coordinates(): void
    {
        $original = "Sayın Ahmet Yılmaz (TCKN: 43650391326), Davacı Mehmet Kaya ile birlikte " .
            "0532 123 45 67 numaralı telefondan arandı. İlgili IBAN: TR33 0006 1005 1978 6457 8413 26 " .
            "ve e-posta ahmet@example.com olarak kaydedildi.";

        $result = Redakte::redact($original);

        $sensitive = $result->toSensitiveArray();
        $this->assertNotEmpty($sensitive['spans']);

        foreach ($sensitive['spans'] as $span) {
            $start = $span['start_offset'];
            $end = $span['end_offset'];
            $len = $end - $start;
            $extracted = substr($original, $start, $len);

            $this->assertSame(
                $span['original_value'],
                $extracted,
                sprintf("Koordinat uyuşmazlığı (%s): beklenen '%s', extracted '%s' [ofset: %d-%d]", $span['entity_type'], $span['original_value'], $extracted, $start, $end)
            );
        }
    }

    public function test_numbers_before_and_after_names_preserve_offsets(): void
    {
        $original = "TCKN: 43650391326 - Davacı Ali Veli - Tel: 0532 123 45 67";
        $result = Redakte::redact($original);

        foreach ($result->toSensitiveArray()['spans'] as $s) {
            $this->assertSame($s['original_value'], substr($original, $s['start_offset'], $s['end_offset'] - $s['start_offset']));
        }
    }
}
