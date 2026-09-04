# Changelog

Bu projedeki tüm önemli değişiklikler bu dosyada belgelenmektedir. Format [Keep a Changelog](https://keepachangelog.com/tr/1.0.0/) standardına uygundur.

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
