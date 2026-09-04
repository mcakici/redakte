<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\RedactionOptions;
use Redakte\Redakte;

class SafeSerializationTest extends TestCase
{
    public function test_to_array_and_json_do_not_leak_sensitive_data(): void
    {
        $text = 'Davacı Ahmet Yılmaz (TCKN: 43650391326, Tel: 0532 123 45 67, IBAN: TR33 0006 1005 1978 6457 8413 26)';
        $result = Redakte::redact($text);

        $array = $result->toArray();
        $json = json_encode($result);

        // 1. toArray() içinde token_map bulunmamalı
        $this->assertArrayNotHasKey('token_map', $array);

        // 2. Span'lar içinde original_value bulunmamalı
        foreach ($array['spans'] as $span) {
            $this->assertArrayNotHasKey('original_value', $span);
            $this->assertArrayHasKey('start_offset', $span);
            $this->assertArrayHasKey('end_offset', $span);
            $this->assertArrayHasKey('entity_type', $span);
            $this->assertArrayHasKey('replacement', $span);
        }

        // 3. json_encode çıktısında hiçbir orijinal hassas veri bulunmamalı
        $this->assertStringNotContainsString('43650391326', $json);
        $this->assertStringNotContainsString('Ahmet Yılmaz', $json);
        $this->assertStringNotContainsString('0532 123 45 67', $json);
        $this->assertStringNotContainsString('TR330006100519786457841326', $json);
    }

    public function test_sensitive_array_explicit_access(): void
    {
        $text = 'Müvekkil TCKN: 43650391326';
        $result = Redakte::redact($text);

        $sensitive = $result->toSensitiveArray();

        $this->assertArrayHasKey('token_map', $sensitive);
        $this->assertNotEmpty($sensitive['token_map']);
        $this->assertSame('43650391326', array_values($sensitive['token_map'])[0]);

        // Span içinde original_value bulunmalı
        $this->assertArrayHasKey('original_value', $sensitive['spans'][0]);
        $this->assertSame('43650391326', $sensitive['spans'][0]['original_value']);
    }
}
