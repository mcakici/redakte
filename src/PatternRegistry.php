<?php

declare(strict_types=1);

namespace Redakte;

use Illuminate\Support\Facades\Config;

/**
 * Konfigürasyondan pattern'leri, negatif filtreleri ve ayarları yükleyip yöneten sınıf.
 */
class PatternRegistry
{
    private ?array $config = null;
    private ?array $sortedPatterns = null;

    public function __construct(?array $customConfig = null)
    {
        $this->config = $customConfig;
    }

    /**
     * @return list<array{id: string, entity_type: string, regex: string, validator: ?string, priority: int}>
     */
    public function getPatterns(): array
    {
        if ($this->sortedPatterns === null) {
            $patterns = $this->get('patterns', []);
            usort($patterns, fn (array $a, array $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));
            $this->sortedPatterns = $patterns;
        }

        return $this->sortedPatterns;
    }

    /**
     * @return list<string>
     */
    public function getExcludePatterns(): array
    {
        return $this->get('exclude_patterns', []);
    }

    /**
     * @return array<string, array{label: string, label_plural: string}>
     */
    public function getEntityTypes(): array
    {
        return $this->get('entity_types', []);
    }

    public function getPolicyId(): string
    {
        return $this->get('policy_id', 'legal_tr_v1');
    }

    public function getPolicyVersion(): string
    {
        return $this->get('policy_version', '2026.03');
    }

    /**
     * @return list<string>
     */
    public function getHumanReviewKeywords(): array
    {
        return $this->get('human_review_keywords', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getNameRedactionConfig(): array
    {
        return $this->get('name_redaction', []);
    }

    /**
     * Ayarı oku (Custom config -> Laravel Config facade -> Fallback config dosyası)
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->config !== null) {
            return $this->config[$key] ?? $default;
        }

        try {
            if (class_exists(Config::class) && Config::has("redakte.{$key}")) {
                return Config::get("redakte.{$key}", $default);
            }
        } catch (\Throwable) {
            // Laravel config container hazır değilse fallback
        }

        static $fileConfig = null;
        if ($fileConfig === null) {
            $path = __DIR__ . '/../config/redakte.php';
            $fileConfig = file_exists($path) ? require $path : [];
        }

        return $fileConfig[$key] ?? $default;
    }
}
