<?php

declare(strict_types=1);

namespace Redakte;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Redakte\Redactors\ModelIdentityRedactor;
use Redakte\Redactors\NameRedactor;
use Redakte\Validators\IbanMod97Validator;
use Redakte\Validators\LuhnChecksumValidator;
use Redakte\Validators\TcknChecksumValidator;
use Redakte\Validators\VknChecksumValidator;

class RedakteServiceProvider extends ServiceProvider
{
    /**
     * Servisleri container'a kaydet.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/redakte.php', 'redakte');

        $this->app->singleton(PatternRegistry::class, function () {
            return new PatternRegistry();
        });

        $this->app->singleton(ReportSummaryBuilder::class, function ($app) {
            return new ReportSummaryBuilder($app->make(PatternRegistry::class));
        });

        $this->app->singleton(TcknChecksumValidator::class, fn () => new TcknChecksumValidator());
        $this->app->singleton(IbanMod97Validator::class, fn () => new IbanMod97Validator());
        $this->app->singleton(VknChecksumValidator::class, fn () => new VknChecksumValidator());
        $this->app->singleton(LuhnChecksumValidator::class, fn () => new LuhnChecksumValidator());
        $this->app->singleton(ModelIdentityRedactor::class, fn () => new ModelIdentityRedactor());

        $this->app->singleton(NameRedactor::class, function ($app) {
            return new NameRedactor($app->make(PatternRegistry::class));
        });

        $this->app->singleton(RedactionValidator::class, function ($app) {
            return new RedactionValidator($app->make(PatternRegistry::class));
        });

        $this->app->singleton(RedactionService::class, function ($app) {
            return new RedactionService(
                registry: $app->make(PatternRegistry::class),
                reportBuilder: $app->make(ReportSummaryBuilder::class),
                tcknValidator: $app->make(TcknChecksumValidator::class),
                ibanValidator: $app->make(IbanMod97Validator::class),
                vknValidator: $app->make(VknChecksumValidator::class),
                luhnValidator: $app->make(LuhnChecksumValidator::class),
            );
        });

        $this->app->singleton(Redactor::class, function ($app) {
            return new Redactor(
                service: $app->make(RedactionService::class),
                nameRedactor: $app->make(NameRedactor::class),
                validator: $app->make(RedactionValidator::class),
                reportBuilder: $app->make(ReportSummaryBuilder::class),
                modelIdentityRedactor: $app->make(ModelIdentityRedactor::class),
            );
        });

        $this->app->alias(Redactor::class, 'redakte');
    }

    /**
     * Boot aşamasında konfigürasyon yayınlamayı ve Blade direktiflerini aktif et.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/redakte.php' => $this->app->configPath('redakte.php'),
            ], 'redakte-config');
        }

        // Blade Direktifleri Kaydı (P0-08: XSS Koruması)
        if (class_exists(Blade::class)) {
            Blade::directive('redakte', function (string $expression) {
                return "<?php echo e(\\Redakte\\Redakte::clean({$expression})); ?>";
            });

            Blade::directive('redaktePartial', function (string $expression) {
                return "<?php echo e(\\Redakte\\Redakte::partial({$expression})); ?>";
            });
        }
    }
}
