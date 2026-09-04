<?php

declare(strict_types=1);

namespace Redakte;

use Redakte\Redactors\ModelIdentityRedactor;
use Redakte\Redactors\NameRedactor;
use Redakte\Validators\IbanMod97Validator;
use Redakte\Validators\LuhnChecksumValidator;
use Redakte\Validators\TcknChecksumValidator;
use Redakte\Validators\VknChecksumValidator;

/**
 * Hem saf PHP (Vanilla PHP, Symfony, WordPress vb.) hem de Laravel için
 * tek noktadan erişim sağlayan ana Redakte sınıfı.
 *
 * Kullanım:
 *   use Redakte\Redakte;
 *   $result = Redakte::redact($metin);
 *   $clean  = Redakte::clean($metin);
 *   $part   = Redakte::partial($metin);
 *   [$prompt, $map] = Redakte::maskForLLM($metin);
 *   $clear  = Redakte::unmask($aiResponse, $map);
 */
class Redakte
{
    private static ?Redactor $instance = null;

    /**
     * Varsayılan Redactor instance'ını döndürür.
     * Laravel ortamındaysa container'daki singleton'ı, değilse varsayılan nesneyi kullanır.
     */
    public static function instance(): Redactor
    {
        // Laravel container varsa ve 'redakte' kayıtlıysa oradan al
        if (function_exists('app')) {
            try {
                $app = app();
                if ($app && method_exists($app, 'bound') && $app->bound('redakte')) {
                    return $app->make('redakte');
                }
            } catch (\Throwable) {
                // Laravel dışı ortam
            }
        }

        if (self::$instance === null) {
            self::$instance = self::create();
        }

        return self::$instance;
    }

    /**
     * Özel konfigürasyon ile yeni bir Redactor oluşturur (Saf PHP için fabrika metodu).
     *
     * @param array<string, mixed>|null $config Özel ayarlar (null ise varsayılan config kullanılır)
     */
    public static function create(?array $config = null): Redactor
    {
        $registry = new PatternRegistry($config);
        $reportBuilder = new ReportSummaryBuilder($registry);
        $tcknValidator = new TcknChecksumValidator();
        $ibanValidator = new IbanMod97Validator();
        $vknValidator = new VknChecksumValidator();
        $luhnValidator = new LuhnChecksumValidator();

        $service = new RedactionService(
            registry: $registry,
            reportBuilder: $reportBuilder,
            tcknValidator: $tcknValidator,
            ibanValidator: $ibanValidator,
            vknValidator: $vknValidator,
            luhnValidator: $luhnValidator,
        );

        $nameRedactor = new NameRedactor($registry);
        $validator = new RedactionValidator($registry);
        $modelIdentityRedactor = new ModelIdentityRedactor();

        return new Redactor(
            service: $service,
            nameRedactor: $nameRedactor,
            validator: $validator,
            reportBuilder: $reportBuilder,
            modelIdentityRedactor: $modelIdentityRedactor,
        );
    }

    /**
     * Metni tarayarak hassas kişisel verileri redakte eder.
     *
     * @param string $text Redakte edilecek ham metin
     * @param RedactionOptions|array<string, mixed>|null $options Ayarlar
     */
    public static function redact(string $text, RedactionOptions|array|null $options = null): RedactionResult
    {
        return self::instance()->redact($text, $options);
    }

    /**
     * Sadece redakte edilmiş temiz metni döndürür.
     */
    public static function clean(string $text, RedactionOptions|array|null $options = null): string
    {
        return self::instance()->clean($text, $options);
    }

    /**
     * Kısmi (okunabilir) maskelenmiş metni döndürür (Örn: 123*****890, TR33 **** 12)
     */
    public static function partial(string $text, RedactionOptions|array|null $options = null): string
    {
        return self::instance()->partial($text, $options);
    }

    /**
     * LLM/AI promptları için güvenli çift yönlü (reversible) maskeleme yapar.
     *
     * @return array{0: string, 1: RedactionMap, text: string, map: RedactionMap}
     */
    public static function maskForLLM(string $text, RedactionOptions|array|null $options = null): array
    {
        return self::instance()->maskForLLM($text, $options);
    }

    /**
     * Yeni bir çok parçalı redaksiyon oturumu başlatır (P2-04).
     */
    public static function session(?string $sessionId = null): \Redakte\Token\RedactionSession
    {
        return self::instance()->session($sessionId);
    }

    /**
     * Token içeren bir metni (örneğin LLM yanıtını) token haritası ile çözer.
     */
    public static function unmask(string $text, RedactionMap|array $map): string
    {
        return self::instance()->unmask($text, $map);
    }

    /**
     * Özel bir Redactor instance'ı atamak için (testlerde veya özel mock'larda)
     */
    public static function setInstance(?Redactor $redactor): void
    {
        self::$instance = $redactor;
    }
}
