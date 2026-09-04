# Changelog

Bu projedeki tüm önemli değişiklikler bu dosyada belgelenmektedir. Format [Keep a Changelog](https://keepachangelog.com/tr/1.0.0/) standardına uygundur.

## [1.0.2] - 2026-09-05

### Güvenlik ve Mimari Sertleştirmeleri
- **OptionsFactory Mimarisi:** Çağrı seçenekleri > Config (`PatternRegistry` / Laravel) > Sabit Varsayılanlar öncelik sırası getirildi. Bilinmeyen veya hatalı seçeneklerde sessizce strict'e düşmek yerine `InvalidArgumentException` fırlatılması sağlandı.
- **Normalizasyon & Doğrudan $O(1)$ Offset Haritalama:** `NormalizedText` tek geçişli hızlı UTF-8 okuyucuya ve $O(1)$ doğrudan byte haritalamasına kavuşturuldu. Dedektörler normalize edilmiş metin üzerinde çalıştırılarak NBSP, zero-width ve CRLF koordinat kayıpları giderildi.
- **128-Bit Token ve Otomatik Çakışma Yönetimi:** `TokenFactory` oturum kimliği en az 128-bit (`bin2hex(random_bytes(16))`) rastgeleliğe yükseltildi; orijinal metinde çakışan tokenlar legacy modda indeks artırarak, namespaced modda yeni session türeterek çözüldü.
- **RedactionMap Güvenli Serileştirme:** `RedactionMap::jsonSerialize()` ham kişisel verileri gizleyerek yalnızca güvenli metadata dönecek şekilde sertleştirildi; açık erişim için `toSensitiveJson()` ve `toSensitiveArray()` eklendi.
- **Otomatik Çapraz Oturum (Cross-Session) Koruması:** `unmask()` metodunda namespaced tokenlar için harita oturum uyuşmazlığında otomatik `UnsafeUnmaskException` fırlatılması sağlandı.
- **Tek Yönlü Stratejilerde Veri İzolasyonu:** `PARTIAL`, `ASTERISK` ve `LABEL` stratejilerinde haritada gereksiz veri saklanması engellendi; yalnızca `TAG` stratejisinde harita doldurulur.

### Algoritmik ve Dedektör Düzeltmeleri
- **VKN 5xx Doğrulama Düzeltmesi:** `VknChecksumValidator` içerisindeki 5xx engeli kaldırıldı; resmi GİB algoritmasıyla 5 ile başlayan VKN'lerin tam doğrulanması sağlandı.
- **Dedektör Doğrulama Statüleri:** Checksum doğrulaması olmayan dedektörler (`Telefon`, `Adres`, `Hukuk No`, `Plaka`, `Mersis`, `Custom Patterns`) `validationStatus: 'not_checked'` olarak güncellendi.
- **Adres Sınır Kesimi:** `AddressDetector` satırda başlayan diğer veri etiketlerini (TCKN, VKN, Telefon vb.) yutmaması için lookahead sınır kontrolüyle donatıldı.
- **Hukuki Numaralar:** `LegalNumberDetector` etiketleri (`Esas No:`, `Karar No:`) koruyarak yalnızca numara grubunu redakte edecek şekilde düzeltildi.
- **Telefon 13-Hane Desteği:** `PhoneDetector` içinde `+90 0...` (13 haneli) durumundaki erişilemez kod dalı düzeltildi.
- **IPv6 Desteği:** `IpAddressDetector` içerisine IPv6 tespiti ve `filter_var` doğrulama denetimi eklendi.
- **Kısaltmalı İsim Desteği:** `NameDetector` içerisine `A. Yılmaz` gibi tek harfli kısaltmalı adların tespiti eklendi.
- **TCKN 0-Başlangıçlı Adaylar:** 0 ile başlayan adaylar regex kapsamına alınarak strict modda yakalanması sağlandı.

### Test ve CI Altyapısı
- **GitHub Actions CI:** PHP 8.2, 8.3, 8.4 sürümlerinde çalışan, `composer validate --strict` ve PHPUnit koşan `.github/workflows/tests.yml` eklendi.
- **Kapsamlı Audit Testleri:** `tests/AuditV101FixesTest.php` eklenerek toplam test sayısı 98'e (299 assertion) çıkarıldı.

## [1.0.1] - 2026-09-05

### Güvenlik Düzeltmeleri (P0)
- **Hassas Veri İzolasyonu (P0-01):** `RedactionResult::toArray()` ve `jsonSerialize()` çıktılarından `token_map` ve span'lardaki `original_value` değerleri tamamen kaldırıldı. Bilinçli erişim için `$result->toSensitiveArray()` eklendi. `RedactionSpan::toArray()` artık hassas veri döndürmez.
- **Tek Koordinat Alanı (P0-02):** Önceden isimlerin metin içinde değiştirilip ardından regex motoruna verilmesi sebebiyle oluşan ofset kaymaları giderildi. Mimari `Detect -> Resolve -> Apply` olarak yeniden yapılandırıldı; tüm span'lar orijinal metin üzerinde üretilir.
- **Konfigürasyon Deep-Merge (P0-03):** Kısmi konfigürasyon geçildiğinde varsayılan kuralların silinmesi önlendi; `patterns_mode` (`append`, `prepend`, `replace`) desteği eklendi.
- **Özel İsimler Ayar Yolu (P0-04):** `name_redaction.custom_names` altındaki özel isimler doğru yoldan okunacak şekilde düzeltildi.
- **Seçenek Doğrulaması (P0-05):** Geçersiz maskeleme stratejilerinde sessizce `TAG`'e düşmek yerine `InvalidArgumentException` üretilmesi sağlandı; `checkHumanReview` ve `name_redaction.enabled` motor düzeyinde bağlandı.
- **Doğrulama ve Politika Ayrımı (P0-06):** Doğrulanamayan fakat biçime uyan veya etiketli adaylar için `strict`, `balanced` ve `validated_only` politikaları uygulandı. `strict` modda geçersiz adaylar maskelenip `UNVALIDATED_FORMAT_CANDIDATE` uyarısı üretilir.
- **Kriptografik Token & Oturum Güvenliği (P0-07):** Oturum bazlı, çakışmaya dayanıklı `⟦RDT:{session}:{entity}:{idx}⟧` token mimarisi eklendi. Yalnızca `TAG` stratejisinde unmask yapılmasına izin verildi; çapraz oturum koruması getirildi.
- **Blade XSS Koruması (P0-08):** `@redakte` ve `@redaktePartial` direktifleri `e()` (HTML escaping) ile sarmalanarak XSS açığı kapatıldı.
- **Log İşlemcisi Güvenliği (P0-09):** Monolog `message`, `context` ve `extra` alanları; `Stringable`, `Throwable`, döngüsel referans ve maksimum derinlik korumasıyla kapsandı.

### İyileştirmeler ve Yeni Yetenekler (P1)
- **Normalizasyon & Koordinat Haritası (P1-01):** `NormalizedText` ile Unicode boşlukları (NBSP), görünmez karakterler (zero-width), akıllı tırnaklar ve CRLF orijinal byte ofsetleri korunarak normalize edilir.
- **Kanonikleştiriciler (P1-02):** Telefon, IBAN, e-posta, plaka, rakam ve isim için kanonikleştiriciler eklendi.
- **Deterministik Çakışma Çözümleyici (P1-03, P2-01):** $O(m \log m)$ weighted interval scheduling algoritmasıyla çalışan `OverlapResolver` eklendi.
- **VKN 5 Başlangıcı Hatasının Giderilmesi (P1-05):** `[0-46-9]` kısıtlaması kaldırılarak tüm 10 haneli VKN'ler (`\b\d{10}\b`) desteklendi; telefon çakışmaları resolver bağlamıyla çözüldü.
- **Kapsamlı Telefon Ayrıştırıcı (P1-06):** 10 farklı telefon formatı ve dengeli parantez denetimi eklendi.
- **Yeni Dedektörler (P1-13, P1-14, P1-15):** `AddressDetector` (etiket ve bileşen tabanlı), `LegalNumberDetector` (`ESAS_NO`, `KARAR_NO`, `DOSYA_NO`), `IpAddressDetector` (`IP_ADRESI`).
- **Eloquent Allowlist Koruması (P1-21):** Yalnızca `$redactable` listesindeki alanlar için `redacted_*` sihirli erişimine izin verildi.
- **Çok Sayfalı Oturum Desteği (P2-04):** `Redakte::session()` API'si eklendi.

### Paket ve Bağımlılıklar
- `composer.json` içerisine `ext-mbstring` ve `ext-ctype` eklendi.
- Test paketi 85 test ve 254 assertion ile %100 başarılı duruma getirildi.

## [1.0.0] - 2026-03-01
- İlk kararlı prototip sürümü.
