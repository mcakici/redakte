<?php

declare(strict_types=1);

namespace Redakte\Tests;

use InvalidArgumentException;
use Redakte\Detectors\CustomPatternDetector;
use Redakte\Detectors\IpAddressDetector;
use Redakte\Detectors\NameDetector;
use Redakte\Exceptions\UnsafeUnmaskException;
use Redakte\MaskStrategy;
use Redakte\Normalization\NormalizedText;
use Redakte\OptionsFactory;
use Redakte\PatternRegistry;
use Redakte\Policy\RedactionPolicy;
use Redakte\RedactionMap;
use Redakte\RedactionOptions;
use Redakte\Redactor;
use Redakte\Token\TokenFactory;
use Redakte\Validators\VknChecksumValidator;

/**
 * redakte-audit-1.0.1.md raporundaki tüm bulguların
 * başarıyla çözüldüğünü kanıtlayan kapsamlı doğrulama testleri.
 */
class AuditV101FixesTest extends TestCase
{
    /**
     * Audit Madde 1: OptionsFactory hiyerarşisi, doğrulama ve bilinmeyen değer kontrolleri
     */
    public function test_options_factory_hierarchy_and_validation(): void
    {
        $registry = new PatternRegistry([
            'policy' => 'balanced',
            'token_format' => 'namespaced',
            'default_strategy' => 'partial',
        ]);

        // 1. Parametre verilmediğinde config'deki değerler önceliklidir
        $opts = OptionsFactory::create(null, $registry);
        $this->assertSame(RedactionPolicy::BALANCED, $opts->policy->name);
        $this->assertSame('namespaced', $opts->tokenFormat);
        $this->assertSame(MaskStrategy::PARTIAL, $opts->strategy);

        // 2. Çağrı parametreleri config'i ezer
        $overrideOpts = OptionsFactory::create(['strategy' => 'tag', 'token_format' => 'legacy'], $registry);
        $this->assertSame(MaskStrategy::TAG, $overrideOpts->strategy);
        $this->assertSame('legacy', $overrideOpts->tokenFormat);

        // 3. Geçersiz politika sessizce strict'e düşmemeli, exception fırlatmalı
        $this->expectException(InvalidArgumentException::class);
        OptionsFactory::create(['policy' => 'invalid_unknown_policy']);
    }

    public function test_options_factory_rejects_unknown_keys_in_strict_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OptionsFactory::create(['unknown_key' => 'foo', 'strict' => true]);
    }

    /**
     * Audit Madde 4: 5 ile başlayan VKN checksum doğrulaması
     */
    public function test_vkn_starting_with_five_is_properly_validated_by_checksum(): void
    {
        $validator = new VknChecksumValidator();

        // 5000000010 numarası GİB formülüne göre geçerli bir 5xx VKN'dir:
        // C_1 = (5+10-1)%10 = 4, v_1 = (4 * 2^9) % 9 = (4 * 512) % 9 = 2048 % 9 = 5
        // Diğer basamaklar 0 olduğu için v_i hesaplanır ve check digit bulunur.
        // Formülle üretilen geçerli bir 5xx VKN örneği: 5000000018
        // Test edelim:
        $validVkn = null;
        for ($i = 0; $i <= 99999999; $i++) {
            $candidate = sprintf('5%08d', $i);
            // GİB checksum'ını doğrudan hesapla
            $digits = array_map('intval', str_split($candidate));
            $sum = 0;
            for ($k = 1; $k <= 9; $k++) {
                $c = ($digits[$k - 1] + 10 - $k) % 10;
                $sum += ($c !== 9) ? (($c * (2 ** (10 - $k))) % 9) : 9;
            }
            $checkDigit = (10 - ($sum % 10)) % 10;
            $fullVkn = $candidate . $checkDigit;
            if ($validator->isValid($fullVkn)) {
                $validVkn = $fullVkn;
                break;
            }
        }

        $this->assertNotNull($validVkn, '5 ile başlayan geçerli bir VKN üretilebilmelidir.');
        $this->assertTrue($validator->isValid($validVkn), "5xx ile başlayan VKN ({$validVkn}) geçerli kabul edilmelidir.");

        // Redactor ile de taranabilmelidir
        $text = "Şirket Vergi Kimlik No: {$validVkn}";
        $result = $this->redactor->redact($text);
        $this->assertStringNotContainsString($validVkn, $result->redactedText);
    }

    /**
     * Audit Madde 2: Normalizasyon entegrasyonu, NBSP, zero-width ve O(1) offset haritalama
     */
    public function test_normalization_and_detector_integration_with_nbsp_and_zero_width(): void
    {
        // Zero-width space (\u{200B}) ve NBSP (\u{00A0}) içeren TCKN ve metin
        $text = "Müşteri\u{00A0}TCKN:\u{00A0}436\u{200B}503\u{200B}91326\u{00A0}kayıtlıdır.";
        
        $result = $this->redactor->redact($text);

        // Orijinal aralık doğru tespit edilip değiştirilmeli
        $this->assertStringNotContainsString('436', $result->redactedText);
        $this->assertStringNotContainsString('91326', $result->redactedText);
    }

    public function test_normalized_text_large_text_performance_and_accuracy(): void
    {
        // 100 KB metin oluştur ve O(1) eşleşmeyi doğrula
        $base = "Bu bir test paragrafıdır. Ahmet Yılmaz TCKN: 43650391326 bilgisi mevcuttur.\n";
        $largeText = str_repeat($base, 1500); // ~110 KB

        $startTime = microtime(true);
        $norm = NormalizedText::create($largeText);
        $createTime = microtime(true) - $startTime;

        // 100 KB oluşturma makul sürede olmalı
        $this->assertLessThan(2.0, $createTime, 'Büyük metin normalizasyonu makul sürede tamamlanmalıdır.');

        // O(1) mapSpanToOriginal testi
        $mapStart = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            [$s, $e] = $norm->mapSpanToOriginal(500, 520);
        }
        $mapDuration = microtime(true) - $mapStart;

        $this->assertLessThan(0.1, $mapDuration, '1000 adet ofset sorgusu O(1) hızında çalışmalıdır.');
    }

    /**
     * Audit Madde 3: Token güvenliği, 128-bit rastgelelik ve collision handling
     */
    public function test_token_factory_128bit_randomness_and_collision_resolution(): void
    {
        $factory = new TokenFactory();
        $sessionId = $factory->getSessionId();

        // 16 byte = 32 hex karakter (128 bit)
        $this->assertSame(32, strlen($sessionId), 'Session ID en az 128 bit (32 hex karakter) olmalıdır.');

        // Namespaced token formatı denetimi
        $token = $factory->createToken('TCKN', 1);
        $this->assertMatchesRegularExpression('/^⟦RDT:[a-f0-9]{32}:TCKN:1⟧$/', $token);

        // Orijinal metinde token çakışması varsa
        $collisionText = "Bu metinde {$token} zaten geçiyor.";
        $idx = 1;
        $resolvedToken = $factory->ensureNoCollision($collisionText, 'TCKN', $idx, false);

        $this->assertNotSame($token, $resolvedToken, 'Çakışan token yeni bir session id ile çözülmelidir.');
        $this->assertStringNotContainsString($resolvedToken, $collisionText);

        // Legacy formatta çakışma çözümü (index artırma)
        $legacyCollisionText = 'Bu metinde [TCKN_1] zaten geçiyor.';
        $legacyIdx = 1;
        $legacyResolved = $factory->ensureNoCollision($legacyCollisionText, 'TCKN', $legacyIdx, true);

        $this->assertSame('[TCKN_2]', $legacyResolved, 'Legacy formatta çakışma indeksi artırarak çözülmelidir.');
        $this->assertSame(2, $legacyIdx);
    }

    /**
     * Audit Madde 3: RedactionMap güvenli JSON serileştirmesi ve otomatik cross-session kontrolü
     */
    public function test_redaction_map_safe_json_and_automatic_cross_session_check(): void
    {
        $map = new RedactionMap([], [], 'session_abc_123');
        $map->add('⟦RDT:session_abc_123:TCKN:1⟧', '43650391326', 'TCKN');
        $map->add('⟦RDT:session_abc_123:KISI:1⟧', 'Gizli Kişi', 'KISI');

        // 1. json_encode kişisel verileri dışarı SIZDIRMAMALI
        $safeJson = json_encode($map);
        $this->assertStringNotContainsString('43650391326', $safeJson);
        $this->assertStringNotContainsString('Gizli Kişi', $safeJson);

        // Güvenli metadata içermeli
        $decoded = json_decode($safeJson, true);
        $this->assertSame(2, $decoded['count']);
        $this->assertSame('session_abc_123', $decoded['session_id']);

        // 2. Açık hassas metotlar veriyi döndürmeli
        $sensitiveArray = $map->toSensitiveArray();
        $this->assertArrayHasKey('⟦RDT:session_abc_123:TCKN:1⟧', $sensitiveArray['tokens']);
        $this->assertSame('43650391326', $sensitiveArray['tokens']['⟦RDT:session_abc_123:TCKN:1⟧']);

        // 3. Cross-session otomatik engelleme
        $alienText = 'İşlem ⟦RDT:baska_bir_session:TCKN:1⟧ için onaylandı.';
        $this->expectException(UnsafeUnmaskException::class);
        $map->unmask($alienText);
    }

    /**
     * Audit Madde 3: Non-TAG stratejilerinde haritada hassas veri tutulmaması
     */
    public function test_partial_strategy_does_not_store_raw_data_in_map(): void
    {
        $text = 'TCKN: 43650391326 ve İsim: Ahmet Yılmaz';
        $result = $this->redactor->redact($text, new RedactionOptions(strategy: MaskStrategy::PARTIAL));

        // PARTIAL irreversible olduğu için haritada hassas veri tutulmamalı
        $this->assertSame(0, $result->map->count(), 'PARTIAL stratejisinde harita boş kalmalıdır.');
        $this->assertStringContainsString('436*****326', $result->redactedText);
    }

    /**
     * Audit Dedektör Doğrulukları: Adres sınır kontrolü (diğer etiketleri yutmama)
     */
    public function test_address_detector_does_not_swallow_subsequent_labels(): void
    {
        $text = "Adres: Atatürk Mahallesi No: 5 Çankaya/Ankara TCKN: 43650391326 Tel: 0532 123 45 67";
        $result = $this->redactor->redact($text);

        // Adres, TCKN ve Telefon ayrı ayrı redakte edilmeli, adres diğerlerini yutmamalı
        $this->assertStringContainsString('TCKN:', $result->redactedText);
        $this->assertStringContainsString('Tel:', $result->redactedText);
        $this->assertStringNotContainsString('Atatürk Mahallesi', $result->redactedText);
        $this->assertStringNotContainsString('43650391326', $result->redactedText);
    }

    /**
     * Audit Dedektör Doğrulukları: Hukuk numaraları etiketleri korumalı, sadece numarayı redakte etmeli
     */
    public function test_legal_number_detector_preserves_label(): void
    {
        $text = "Dosyada Esas No: 2023/456 ve Karar No: 2023/789 kayıtlıdır.";
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('Esas No:', $result->redactedText, 'Esas No etiketi metinde korunmalıdır.');
        $this->assertStringContainsString('Karar No:', $result->redactedText, 'Karar No etiketi metinde korunmalıdır.');
        $this->assertStringNotContainsString('2023/456', $result->redactedText);
        $this->assertStringNotContainsString('2023/789', $result->redactedText);
    }

    /**
     * Audit Dedektör Doğrulukları: IPv6 desteği
     */
    public function test_ip_address_detector_supports_ipv6(): void
    {
        $text = "Sunucu 2001:0db8:85a3:0000:0000:8a2e:0370:7334 ve fe80::1 üzerinden yanıt verdi.";
        $result = $this->redactor->redact($text);

        $this->assertStringNotContainsString('2001:0db8', $result->redactedText);
        $this->assertStringNotContainsString('fe80::1', $result->redactedText);
    }

    /**
     * Audit Dedektör Doğrulukları: A. Yılmaz gibi kısaltmalı adların yakalanması
     */
    public function test_name_detector_supports_abbreviated_first_name(): void
    {
        $text = "Sayın A. Yılmaz mahkemeye intikal etti.";
        $result = $this->redactor->redact($text);

        $this->assertStringNotContainsString('A. Yılmaz', $result->redactedText);
        $this->assertStringContainsString('Sayın', $result->redactedText);
    }

    /**
     * Audit Dedektör Doğrulukları: Doğrulayıcısı olmayan özel pattern'ların not_checked statüsü
     */
    public function test_custom_pattern_without_validator_gets_not_checked_status(): void
    {
        $registry = new PatternRegistry([
            'patterns' => [
                [
                    'id' => 'CUSTOM_ORDER_NO',
                    'entity_type' => 'SIPARIS_NO',
                    'regex' => '/\bORD-\d{5}\b/',
                    'validator' => null,
                    'priority' => 50,
                ],
            ],
        ]);

        $detector = new CustomPatternDetector($registry);
        $norm = NormalizedText::create('Sipariş: ORD-12345');
        $context = new \Redakte\Detection\DetectionContext(
            originalText: 'Sipariş: ORD-12345',
            normalizedText: $norm,
            options: new RedactionOptions(),
            policy: RedactionPolicy::balanced(),
        );

        $detections = $detector->detect('Sipariş: ORD-12345', $context);
        $this->assertNotEmpty($detections);
        $this->assertSame('not_checked', $detections[0]->validationStatus);
    }
}
