<?php

declare(strict_types=1);

namespace Redakte\Tests;

class NameRedactionTest extends TestCase
{
    public function test_davaci_davali_redaction(): void
    {
        $text = 'Davacı Ayşe Demir ve Davalı Mehmet Kaya duruşmaya katıldı.';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('Davacı [KISI_1]', $result->redactedText);
        $this->assertStringContainsString('Davalı [KISI_2]', $result->redactedText);
        $this->assertStringNotContainsString('Ayşe Demir', $result->redactedText);
        $this->assertStringNotContainsString('Mehmet Kaya', $result->redactedText);
    }

    public function test_labeled_name_redaction(): void
    {
        $text = 'Adı Soyadı: Ahmet Yılmaz, İsim: Fatma Öz';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('Adı Soyadı: [KISI_1]', $result->redactedText);
        $this->assertStringContainsString('İsim: [KISI_2]', $result->redactedText);
        $this->assertStringNotContainsString('Ahmet Yılmaz', $result->redactedText);
        $this->assertStringNotContainsString('Fatma Öz', $result->redactedText);
    }

    public function test_role_with_suffix_preserved(): void
    {
        $text = "Müvekkilim Ayşe Demir, kiracı Mehmet Kaya'ya evi kiralamıştır.";
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('Müvekkilim [KISI_1]', $result->redactedText);
        $this->assertStringContainsString("kiracı [KISI_2]'ya", $result->redactedText);
        $this->assertStringNotContainsString('Ayşe Demir', $result->redactedText);
        $this->assertStringNotContainsString('Mehmet Kaya', $result->redactedText);
    }

    public function test_avukat_and_salutation(): void
    {
        $text = 'Sayın Mehmet Yıldız ve vekili Av. Canan Dağ dosyayı sundu.';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('Sayın [KISI_1]', $result->redactedText);
        $this->assertStringContainsString('Av. [KISI_2]', $result->redactedText);
        $this->assertStringNotContainsString('Mehmet Yıldız', $result->redactedText);
        $this->assertStringNotContainsString('Canan Dağ', $result->redactedText);
    }

    public function test_consistent_name_placeholder(): void
    {
        $text = 'Davacı Ayşe Demir duruşmaya geldi. Ayşe Demir iddialarını tekrarladı.';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('[KISI_1]', $result->redactedText);
        $this->assertStringNotContainsString('[KISI_2]', $result->redactedText);
    }
}
