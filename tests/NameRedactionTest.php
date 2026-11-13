<?php

declare(strict_types=1);

namespace Redakte\Tests;

class NameRedactionTest extends TestCase
{
    public function test_uppercase_instruction_is_preserved_while_hale_remains_a_valid_person_name(): void
    {
        foreach (['DİLEKÇEYİ DAHA HUKUKİ VE SAĞLAM HALE GETİR', 'Metni anlaşılır hâle getirin.'] as $source) {
            $this->assertSame($source, $this->redactor->redact($source)->redactedText);
        }
        $result = $this->redactor->redact('Davacı: Hale Getir. Davalı: Hale Demir.');
        $this->assertStringNotContainsString('Hale Getir', $result->redactedText);
        $this->assertStringNotContainsString('Hale Demir', $result->redactedText);
    }

    public function test_davaci_davali_redaction(): void
    {
        $text = 'Davacı Ayşe Demir ve Davalı Mehmet Kaya duruşmaya katıldı.';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('Davacı [KISI_1]', $result->redactedText);
        $this->assertStringContainsString('Davalı [KISI_2]', $result->redactedText);
        $this->assertStringNotContainsString('Ayşe Demir', $result->redactedText);
        $this->assertStringNotContainsString('Mehmet Kaya', $result->redactedText);
    }

    public function test_context_labels_are_caseless_but_following_words_do_not_join_names(): void
    {
        $result = $this->redactor->redact('davacı Ayşe Demir duruşmaya geldi. davalı Mehmet Kaya arandı.');
        $this->assertSame('davacı [KISI_1] duruşmaya geldi. davalı [KISI_2] arandı.', $result->redactedText);
        $this->assertSame('Ayşe Demir', $result->spans[0]->originalValue);
        $this->assertSame('Mehmet Kaya', $result->spans[1]->originalValue);
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

    public function test_gazetteer_standalone_names(): void
    {
        $text = 'Toplantıya Ali Yılmaz ve Ebru Can Güler katıldı.';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('[KISI_1]', $result->redactedText);
        $this->assertStringContainsString('[KISI_2]', $result->redactedText);
        $this->assertStringNotContainsString('Ali Yılmaz', $result->redactedText);
        $this->assertStringNotContainsString('Ebru Can Güler', $result->redactedText);
    }

    public function test_three_word_name_and_suffix(): void
    {
        $text = "Pınar İpek Parlak'a tebligat yapılmıştır.";
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString("[KISI_1]'a tebligat yapılmıştır.", $result->redactedText);
        $this->assertStringNotContainsString('Pınar İpek Parlak', $result->redactedText);
    }

    public function test_institution_negative_case(): void
    {
        $text = 'Mehmet Akif Ersoy Üniversitesi ve İstanbul Üniversitesi ortak karar aldı.';
        $result = $this->redactor->redact($text);

        // Üniversite isimleri kişi adı sanılıp bozulmamalıdır
        $this->assertStringContainsString('Mehmet Akif Ersoy Üniversitesi', $result->redactedText);
        $this->assertStringContainsString('İstanbul Üniversitesi', $result->redactedText);
    }

    public function test_partial_masking_three_word_name(): void
    {
        $text = "Pınar İpek Parlak ile görüştük.";
        $partial = $this->redactor->partial($text);

        $this->assertStringContainsString('P**** İ*** P***** ile görüştük.', $partial);
        $this->assertStringNotContainsString('Pınar İpek Parlak', $partial);
    }
}
