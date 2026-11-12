<?php

declare(strict_types=1);

namespace Redakte\Llm;

use Redakte\Exceptions\InvalidConfigurationException;

/** İstekler arasında durum taşımayan Ollama yapılandırması. */
final readonly class LlmOptions
{
    public bool $enabled;
    public ?string $endpoint;
    public ?string $model;
    public string $systemPrompt;
    public array $options;
    public array $headers;
    public float $timeoutSeconds;
    public int $maxResponseBytes;
    public ?int $chunkSize;
    public ?int $chunkOverlap;
    public int $maxChunks;
    public ?array $entityTypes;
    public string|int|null $keepAlive;
    public bool|string|null $think;
    public ?string $showEndpoint;
    public float $contextSafetyMargin;
    public float $estimatedBytesPerToken;
    public int $outputTokenReserve;
    public float $totalTimeoutSeconds;

    public const DEFAULT_SYSTEM_PROMPT = 'Türkçe metinlerdeki hassas kişisel verileri tespit et. '
        . 'Kişi adlarını, açık adresleri ve izin verilen diğer kişisel veri türlerini bağlamıyla değerlendir. '
        . 'Doğum tarihi, kimlik/pasaport, hesap/müşteri numarası, kullanıcı adı ve parolaları da denetle. '
        . 'Kişiye atfedilen sağlık, biyometrik/genetik, köken, inanç, siyasi görüş, üyelik, cinsel hayat '
        . 've ceza/güvenlik tedbiri bilgilerini izin verilen uygun türle işaretle. '
        . 'Genel bir konudan söz edilmesini kişisel veri sanma; kişiye ait bilgiyi taşıyan en dar tam ifadeyi seç. '
        . 'Kurum adlarını, genel kavramları ve kanun/madde atıflarını kişi adı olarak işaretleme. '
        . 'Yalnızca kaynak metinde açıkça bulunan ifadeleri seç; tahminde bulunma.';

    public static function resolve(array $base = [], self|array|bool|null $override = null): self
    {
        if ($override instanceof self) {
            return $override;
        }
        if (is_bool($override)) {
            $override = ['enabled' => $override];
        }
        return new self(self::mergeConfig($base, $override ?? []));
    }

    /** Çağrı ve genel yapılandırma aynı birleştirme kurallarını kullanır. */
    public static function mergeConfig(array $base, array $override): array
    {
        $thinkingKeys = ['think', 'thinking', 'enable_thinking', 'reasoning_effort'];
        if (array_intersect(array_keys($override), $thinkingKeys) !== []) {
            foreach ($thinkingKeys as $key) {
                unset($base[$key]);
            }
        }
        $config = array_replace($base, $override);
        foreach (['options', 'headers'] as $key) {
            if (isset($base[$key], $override[$key]) && is_array($base[$key]) && is_array($override[$key])) {
                $config[$key] = array_replace($base[$key], $override[$key]);
            }
        }
        return $config;
    }

    public function __construct(array $config = [])
    {
        $allowed = ['enabled', 'endpoint', 'model', 'system_prompt', 'options', 'headers',
            'timeout_seconds', 'max_response_bytes', 'chunk_size', 'chunk_overlap',
            'max_chunks', 'entity_types', 'keep_alive', 'think', 'thinking', 'enable_thinking',
            'reasoning_effort', 'show_endpoint', 'context_safety_margin', 'estimated_bytes_per_token',
            'output_token_reserve', 'total_timeout_seconds'];
        foreach (array_keys($config) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidConfigurationException('Bilinmeyen LLM ayarı. Model parametrelerini llm.options içinde verin.');
            }
        }
        $enabled = $config['enabled'] ?? null;
        if ($enabled !== null && !is_bool($enabled)) {
            throw new InvalidConfigurationException('llm.enabled boolean veya null olmalıdır.');
        }
        $this->endpoint = $this->optionalString($config['endpoint'] ?? null, 'endpoint');
        $this->model = $this->optionalString($config['model'] ?? null, 'model');
        $hasEndpoint = $this->endpoint !== null;
        $hasModel = $this->model !== null;
        if ($enabled !== false && (($hasEndpoint !== $hasModel) || ($enabled === true && !$hasEndpoint))) {
            throw new InvalidConfigurationException('LLM kullanımı için endpoint ve model birlikte verilmelidir.');
        }
        $this->enabled = $enabled ?? ($hasEndpoint && $hasModel);
        $this->showEndpoint = $this->optionalString($config['show_endpoint'] ?? null, 'show_endpoint');
        foreach ([$this->endpoint, $this->showEndpoint] as $endpoint) {
            if ($endpoint === null) {
                continue;
            }
            $url = parse_url($endpoint);
            if ($url === false || !in_array($url['scheme'] ?? '', ['http', 'https'], true)
                || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])
                || preg_match('/[\x00-\x20\x7f]/', $endpoint)) {
                throw new InvalidConfigurationException('llm.endpoint geçerli, kimlik bilgisi içermeyen bir HTTP(S) URL olmalıdır.');
            }
        }
        $prompt = $config['system_prompt'] ?? self::DEFAULT_SYSTEM_PROMPT;
        if (!is_string($prompt) || !mb_check_encoding($prompt, 'UTF-8')) {
            throw new InvalidConfigurationException('llm.system_prompt geçerli UTF-8 metin olmalıdır.');
        }
        $this->systemPrompt = $prompt;
        $options = $config['options'] ?? [];
        if (!is_array($options) || ($options !== [] && array_is_list($options))) {
            throw new InvalidConfigurationException('llm.options anahtar/değer dizisi olmalıdır.');
        }
        foreach ($options as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $key)) {
                throw new InvalidConfigurationException('Geçersiz LLM model parametresi adı.');
            }
            if (in_array($key, ['think', 'thinking', 'enable_thinking', 'reasoning_effort'], true)) {
                throw new InvalidConfigurationException('Düşünme ayarları llm.options içine değil, doğrudan llm altına verilmelidir.');
            }
            $this->validateJsonValue($value);
        }
        $this->options = array_replace(['temperature' => 0, 'num_ctx' => 8192, 'num_predict' => 2048], $options);
        foreach (['num_ctx', 'num_predict'] as $key) {
            if (!is_int($this->options[$key]) || ($key === 'num_ctx' ? $this->options[$key] < 1 : $this->options[$key] < -1 || $this->options[$key] === 0)) {
                throw new InvalidConfigurationException('num_ctx pozitif; num_predict pozitif veya -1 bir tamsayı olmalıdır.');
            }
        }
        $headers = $config['headers'] ?? [];
        if (!is_array($headers)) {
            throw new InvalidConfigurationException('llm.headers bir dizi olmalıdır.');
        }
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !preg_match('/^[a-zA-Z0-9-]+$/D', $name) || !is_string($value)
                || preg_match('/[\r\n\x00]/', $value)
                || in_array(strtolower($name), ['host', 'content-length', 'transfer-encoding', 'connection', 'content-type', 'accept'], true)) {
                throw new InvalidConfigurationException('Geçersiz veya ayrılmış LLM HTTP başlığı.');
            }
        }
        $this->headers = $headers;
        $timeout = $config['timeout_seconds'] ?? 120;
        if ((!is_int($timeout) && !is_float($timeout)) || !is_finite((float) $timeout) || $timeout <= 0) {
            throw new InvalidConfigurationException('llm.timeout_seconds pozitif sayı olmalıdır.');
        }
        $this->timeoutSeconds = (float) $timeout;
        $this->maxResponseBytes = $this->positiveInt($config, 'max_response_bytes', 1048576);
        $this->chunkSize = isset($config['chunk_size']) ? $this->positiveInt($config, 'chunk_size', 4096) : null;
        $overlap = $config['chunk_overlap'] ?? null;
        if (($overlap !== null && (!is_int($overlap) || $overlap < 0))
            || ($this->chunkSize !== null && ($this->chunkSize < 64 || ($overlap !== null && $overlap >= $this->chunkSize)))) {
            throw new InvalidConfigurationException('chunk_size en az 64; chunk_overlap sıfır ile chunk_size arasında olmalıdır.');
        }
        $this->chunkOverlap = $overlap;
        $this->maxChunks = $this->positiveInt($config, 'max_chunks', 512);
        $this->contextSafetyMargin = $this->number($config, 'context_safety_margin', 0.05, 0, 0.5);
        $this->estimatedBytesPerToken = $this->number($config, 'estimated_bytes_per_token', 2, 1, 8);
        $this->outputTokenReserve = $this->positiveInt($config, 'output_token_reserve', 2048);
        $this->totalTimeoutSeconds = $this->number($config, 'total_timeout_seconds', 600, 0.001, PHP_FLOAT_MAX);
        $types = $config['entity_types'] ?? null;
        if ($types !== null && (!is_array($types) || !array_is_list($types))) {
            throw new InvalidConfigurationException('llm.entity_types bir liste veya null olmalıdır.');
        }
        foreach ($types ?? [] as $type) {
            if (!is_string($type) || !preg_match('/^[A-Z][A-Z0-9_]*$/D', $type)) {
                throw new InvalidConfigurationException('Geçersiz LLM entity türü.');
            }
        }
        $this->entityTypes = $types;
        $keepAlive = $config['keep_alive'] ?? '5m';
        if (!is_string($keepAlive) && !is_int($keepAlive)) {
            throw new InvalidConfigurationException('llm.keep_alive metin veya tamsayı olmalıdır.');
        }
        $this->keepAlive = $keepAlive;
        $this->think = $this->resolveThinking($config);
    }

    private function optionalString(mixed $value, string $name): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || trim($value) === '' || !mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidConfigurationException('llm.' . $name . ' geçerli metin olmalıdır.');
        }
        return trim($value);
    }

    private function positiveInt(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? $default;
        if (!is_int($value) || $value < 1 || $value >= PHP_INT_MAX) {
            throw new InvalidConfigurationException('llm.' . $key . ' pozitif tamsayı olmalıdır.');
        }
        return $value;
    }

    private function validateJsonValue(mixed $value, int $depth = 0): void
    {
        if ($depth > 32 || is_object($value) || is_resource($value)
            || (is_float($value) && !is_finite($value))
            || (is_string($value) && !mb_check_encoding($value, 'UTF-8'))) {
            throw new InvalidConfigurationException('LLM model parametreleri geçerli JSON değerleri olmalıdır.');
        }
        if (is_array($value)) {
            foreach ($value as $child) {
                $this->validateJsonValue($child, $depth + 1);
            }
        }
    }

    private function number(array $config, string $key, float $default, float $min, float $max): float
    {
        $value = $config[$key] ?? $default;
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < $min || $value > $max) {
            throw new InvalidConfigurationException('Geçersiz llm.' . $key . ' sayısal ayarı.');
        }
        return (float) $value;
    }

    private function resolveThinking(array $config): bool|string|null
    {
        $flag = null;
        $level = null;
        foreach (['think', 'thinking', 'enable_thinking', 'reasoning_effort'] as $key) {
            $value = $config[$key] ?? null;
            if ($value === null) {
                continue;
            }
            if (($key === 'enable_thinking' && !is_bool($value))
                || ($key === 'reasoning_effort' && !is_string($value))
                || (!is_bool($value) && !is_string($value))
                || (is_string($value) && (trim($value) === '' || !mb_check_encoding($value, 'UTF-8')))) {
                throw new InvalidConfigurationException('Geçersiz LLM düşünme ayarı.');
            }
            if ($key === 'reasoning_effort' && $value === 'none') {
                $value = false;
            }
            if (is_bool($value)) {
                if ($flag !== null && $flag !== $value) {
                    throw new InvalidConfigurationException('LLM düşünme ayarları birbiriyle çelişiyor.');
                }
                $flag = $value;
            } else {
                if ($level !== null && $level !== $value) {
                    throw new InvalidConfigurationException('LLM düşünme seviyeleri birbiriyle çelişiyor.');
                }
                $level = $value;
            }
        }
        if ($flag === false && $level !== null) {
            throw new InvalidConfigurationException('Düşünme kapalıyken reasoning_effort/seviye belirtilemez.');
        }
        return $level ?? $flag;
    }
}
