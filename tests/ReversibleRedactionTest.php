<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\RedactionMap;
use Redakte\Redakte;

class ReversibleRedactionTest extends TestCase
{
    public function test_mask_for_llm_and_unmask(): void
    {
        $prompt = 'Davacı Ahmet Yılmaz (TCKN: 43650391326), 0532 123 45 67 numaralı telefondan arandı.';

        [$maskedPrompt, $map] = Redakte::maskForLLM($prompt);

        $this->assertInstanceOf(RedactionMap::class, $map);
        $this->assertStringContainsString('Davacı [KISI_1]', $maskedPrompt);
        $this->assertStringContainsString('TCKN: [TCKN_1]', $maskedPrompt);
        $this->assertStringContainsString('[TELEFON_1]', $maskedPrompt);
        $this->assertStringNotContainsString('Ahmet Yılmaz', $maskedPrompt);
        $this->assertStringNotContainsString('43650391326', $maskedPrompt);

        // LLM'den dönen simüle edilmiş yanıt
        $aiResponse = 'Özet: [KISI_1], [TCKN_1] kimlik numaralı olup [TELEFON_1] ile irtibat kurulmuştur.';

        // Yanıtı geri çöz (de-anonymize / unmask)
        $unmasked = Redakte::unmask($aiResponse, $map);

        $this->assertStringContainsString('Ahmet Yılmaz', $unmasked);
        $this->assertStringContainsString('43650391326', $unmasked);
        $this->assertStringContainsString('0532 123 45 67', $unmasked);
        $this->assertStringNotContainsString('[KISI_1]', $unmasked);
        $this->assertStringNotContainsString('[TCKN_1]', $unmasked);
    }

    public function test_redaction_result_unmask_direct_method(): void
    {
        $text = 'Müşteri Fatma Demir, TCKN: 43650391326';
        $result = Redakte::redact($text);

        $this->assertNotNull($result->map);
        $this->assertTrue($result->hasReplacements());

        $aiText = 'Kayıt onaylandı: [TCKN_1] numaralı kişi.';
        $restored = $result->unmask($aiText);

        $this->assertSame('Kayıt onaylandı: 43650391326 numaralı kişi.', $restored);
        $this->assertArrayHasKey('[TCKN_1]', $result->getTokenMap());
    }

    public function test_redaction_map_json_serialization(): void
    {
        $map = new RedactionMap();
        $map->add('[TCKN_1]', '43650391326', 'TCKN');
        $map->add('[KISI_1]', 'Ali Kaya', 'KISI');

        $json = $map->toJson();
        $reconstructed = RedactionMap::fromJson($json);

        $this->assertSame(2, $reconstructed->count());
        $this->assertSame('43650391326', $reconstructed->get('[TCKN_1]'));
        $this->assertSame('Ali Kaya', $reconstructed->get('[KISI_1]'));
        $this->assertSame('TCKN', $reconstructed->getType('[TCKN_1]'));

        $unmasked = $reconstructed->unmask('[KISI_1] ve [TCKN_1]');
        $this->assertSame('Ali Kaya ve 43650391326', $unmasked);
    }
}
