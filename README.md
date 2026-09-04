# Redakte - PHP & Laravel Kişisel Veri Redaksiyon ve Anonimleştirme Paketi

[![Latest Version](https://img.shields.io/packagist/v/mcakici/redakte.svg?style=flat-square)](https://packagist.org/packages/mcakici/redakte)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg?style=flat-square)](#-testleri-çalıştırma)

**Redakte**, metinlerde, hukuki belgelerde, veri tabanı modellerinde ve kullanıcı girdilerinde bulunan hassas kişisel verileri (KVKK / GDPR kapsamında) akıllı ve yüksek doğruluklu algoritmalarla tespit edip güvenli yer tutucularla (`[TCKN_1]`, `[IBAN_1]`, `[KISI_1]`) veya kısmi maskeleme (`123*****890`) ile redakte eden bağımsız bir PHP & Laravel kütüphanesidir.

> [!NOTE]
> **Önemli Bilgilendirme:** Bu dokümantasyonda ve test senaryolarında yer alan tüm kişi isimleri (*Ahmet Yılmaz, Ali Veli vb.*), TCKN, IBAN, telefon, kredi kartı ve e-posta adresleri, kütüphanenin algoritmik doğrulamalarını (Luhn, Mod97, TCKN checksum vb.) göstermek amacıyla üretilmiş **tamamen sentetik / sahte (dummy) test verileridir**; gerçek kişi veya kurumlarla herhangi bir ilgisi bulunmamaktadır.

---

## 🚀 Öne Çıkan Yetenekler

- **T.C. Kimlik Numarası (TCKN):** 11 haneli kural ve resmi mod kontrolü algoritması (10. ve 11. hane matematiksel doğrulaması). Geçersiz TCKN'ler yanlışlıkla redakte edilmez.
- **Kredi Kartı & Banka Kartı:** Visa, MasterCard, Troy vb. kart numaraları ve Luhn (Mod 10) algoritması doğrulaması.
- **Vergi Kimlik Numarası (VKN):** 10 haneli VKN doğrulama algoritması. 5 ile başlayan GSM numaralarıyla çakışmaz.
- **MERSİS Numarası:** 16 haneli merkezi sicil kayıt numaraları.
- **IBAN:** TR formatında boşluklu veya bitişik IBAN'lar ve ISO 7064 Mod97 algoritma doğrulaması.
- **Telefon Numaraları:** 
  - Cep telefonları (`+90 5xx`, `05xx`, `5xx`, boşluklu, parantezli ve bitişik tüm biçimler)
  - Sabit / Şehirlerarası hatlar (`0212`, `0312`, `+90 2xx/3xx/4xx`, `0090...`)
- **İsim ve Soyisim Tespiti:**
  - Hukuki/bağlamsal roller (*Davacı, Davalı, Sanık, Müşteki, Şüpheli, Mağdur, Tanık, Müvekkil, Kiracı, Borçlu, Alacaklı vb.*)
  - Unvan ve hitaplar (*Av., Avukat, Hakim, Savcı, Dr., Prof. Dr., Sayın vb.*)
  - Etiket ve form alanları (*Adı Soyadı:, İsim:, İmza: vb.*)
  - İsim eklerinin ve yönelme durumlarının korunması (*"kiracı Mehmet Kaya'ya" -> "kiracı [KISI_1]'ya"*)
- **E-Posta & Araç Plakası:** RFC uyumlu e-postalar ve Türkiye araç plakaları.
- **🤖 LLM / Yapay Zeka Çift Yönlü Maskeleme (Reversible / De-anonymize):** Prompt'u OpenAI/Claude'a göndermeden önce maskeleme, gelen yanıttaki etiketleri otomatik orijinal değerlere geri takas etme.
- **🎭 4 Farklı Maskeleme Stratejisi:**
  - `TAG`: `[TCKN_1]`, `[KISI_1]` (Varsayılan ve LLM için ideal)
  - `PARTIAL`: `123*****890`, `TR33 **** 26`, `a***@domain.com`, `A**** Y*****`
  - `ASTERISK`: `***********` (Karakter sayısınca tam gizleme)
  - `LABEL`: `[TCKN]`, `[IBAN]`, `[KİŞİ ADI]` (Numarasız etiketleme)
- **⚡ Laravel Ekosistem Özellikleri:**
  - **Eloquent Model Trait (`HasRedaction`):** `$model->redacted_content`, `$model->toRedactedArray()`
  - **Monolog Log İşlemcisi (`RedakteLogProcessor`):** Laravel loglarına kazara TCKN/IBAN/kart sızmasını önleme
  - **Blade Direktifleri:** `@redakte($text)`, `@redaktePartial($text)`
- **Akıllı İstisnalar (Negative Patterns):** Kanun ve madde referansları (*m. 123, madde 456, 123/2 md.*) korunur, yanlış pozitifler önlenir.
- **Tutarlı Yer Tutucu Eşleşmesi (Entity Consistency):** Aynı metin içinde aynı kişi veya numara birden fazla kez geçtiğinde her seferinde aynı indeks kullanılır.

---

## 📦 Kurulum

### Composer ile Yükleme

```bash
composer require mcakici/redakte
```

### Konfigürasyonu Yayınlama (Laravel için İsteğe Bağlı)

```bash
php artisan vendor:publish --tag=redakte-config
```

---

## 💡 Kullanım Örnekleri

### 1. LLM & Yapay Zeka Çift Yönlü Maskeleme (Reversible Redaction)

Prompt'u OpenAI, Anthropic veya yerel bir LLM'e göndermeden önce kişisel verileri maskeleyip, AI yanıtındaki etiketleri tekrar orijinal verileriyle takas edebilirsiniz:

```php
use Redakte\Redakte;

$prompt = "Davacı Ahmet Yılmaz (TCKN: 43650391326), 0532 123 45 67 telefon numarasından arandı.";

// 1. LLM için maskele ve token haritasını al:
[$maskedPrompt, $map] = Redakte::maskForLLM($prompt);
// $maskedPrompt -> "Davacı [KISI_1] (TCKN: [TCKN_1]), [TELEFON_1] telefon numarasından arandı."

// 2. Modeli çağırın (örnek AI yanıtı):
$aiResponse = "Özet: [KISI_1], [TCKN_1] kimlik numaralı olup [TELEFON_1] ile aranmıştır.";

// 3. Yanıtı orijinal haline geri çözün (De-anonymize):
$originalResponse = Redakte::unmask($aiResponse, $map);
// Çıktı: "Özet: Ahmet Yılmaz, 43650391326 kimlik numaralı olup 0532 123 45 67 ile aranmıştır."
```

---

### 2. Kısmi (Okunabilir) Maskeleme (`partial`)

Metnin akışını ve okunabilirliğini bozmadan güvenlik sağlamak için:

```php
use Redakte\Redakte;

$metin = "Sayın Ahmet Yılmaz, TCKN: 43650391326, IBAN: TR33 0006 1005 1978 6457 8413 26, E-posta: ahmet@example.com";

echo Redakte::partial($metin);
// Çıktı: "Sayın A**** Y*****, TCKN: 436*****326, IBAN: TR33 **** **** **** **** **** 26, E-posta: a***t@example.com"
```

---

### 3. Saf PHP ile Standart Redaksiyon

```php
use Redakte\Redakte;

$metin = "Davacı Ali Veli, TCKN: 43650391326, Kart: 4111 1111 1111 1111";

$result = Redakte::redact($metin);

echo $result->redactedText;
// Davacı [KISI_1], TCKN: [TCKN_1], Kart: [KREDI_KARTI_1]

echo $result->reportSummary;
// 1 TCKN, 1 kredi kartı, 1 kişi adı redakte edildi.

// Sadece temiz metin gerekiyorsa:
$clean = Redakte::clean($metin);
```

---

### 4. Laravel Eloquent Modellerinde Kullanım (`HasRedaction`)

Modellerinizdeki hassas alanları anında redakte edilmiş olarak sunabilirsiniz:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Redakte\Laravel\Traits\HasRedaction;

class Decision extends Model
{
    use HasRedaction;

    protected array $redactable = ['reasoning', 'internal_notes'];
}

// Kullanım:
$decision = Decision::find(1);

// Otomatik accessor:
echo $decision->redacted_reasoning;

// Belirli seçeneklerle alma:
echo $decision->getRedactedAttribute('reasoning', ['strategy' => 'partial']);

// API yanıtı için topluca redakte edilmiş dizi:
return response()->json($decision->toRedactedArray());
```

---

### 5. Laravel Log Redaksiyonu (Monolog Processor)

Log dosyalarınıza kazara TCKN, IBAN, kredi kartı veya telefon bilgisi düşmesini engellemek için `config/logging.php` içerisine ekleyebilirsiniz:

```php
// config/logging.php
'channels' => [
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'processors' => [\Redakte\Laravel\Logging\RedakteLogProcessor::class],
    ],
],
```

---

### 6. Laravel Blade Direktifleri

Blade şablonlarında hızlıca redaksiyon yapmak için:

```blade
{{-- Tam redaksiyon ([TCKN_1] vb.) --}}
@redakte($user->bio)

{{-- Kısmi maskeleme (123*****890 vb.) --}}
@redaktePartial($user->comment)
```

---

### 7. Maskeleme Stratejileri (`MaskStrategy`)

```php
use Redakte\Redakte;
use Redakte\RedactionOptions;

// 1. Tag (Varsayılan): [TCKN_1], [IBAN_1]
Redakte::clean($text, RedactionOptions::create());

// 2. Partial: 123*****890, TR33 **** 26
Redakte::clean($text, RedactionOptions::partial());

// 3. Asterisk: ***********
Redakte::clean($text, RedactionOptions::asterisk());

// 4. Label: [TCKN], [IBAN]
Redakte::clean($text, RedactionOptions::label());
```

---

## 🔍 Sonuç Nesnesi (`RedactionResult`)

| Özellik / Metot | Tip | Açıklama |
|---|---|---|
| `$result->redactedText` | `string` | Maskelenmiş nihai metin |
| `$result->reportSummary` | `string` | Türkçe kullanıcı dostu özet metin |
| `$result->replacementsByType` | `array<string, int>` | Entity türü başına değiştirilme adedi |
| `$result->totalReplacements()` | `int` | Toplam redakte edilen veri sayısı |
| `$result->unmask($targetText)` | `string` | Metindeki token'ları orijinal değerlerle geri takas eder |
| `$result->getTokenMap()` | `array<string, string>` | `['[TCKN_1]' => '123...']` token eşleşme haritası |
| `$result->spans` | `list<RedactionSpan>` | Tespit edilen parçaların ofset, konum, güven ve orijinal değerleri |
| `$result->requiresHumanReview`| `bool` | Şüpheli bağlam uyarısı |
| `$result->warnings` | `list<string>` | Güvenlik ve denetim uyarıları |
| `$result->toArray()` | `array` | API ve loglama için tam veri dizisi |

---

## 🧪 Testleri Çalıştırma

Tüm birim ve entegrasyon testlerini PHPUnit ile çalıştırabilirsiniz:

```bash
composer test
# veya
vendor/bin/phpunit
```

---

## 📄 Lisans

Bu paket MIT lisansı altında açık kaynak olarak lisanslanmıştır.
