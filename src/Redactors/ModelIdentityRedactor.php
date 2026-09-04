<?php

declare(strict_types=1);

namespace Redakte\Redactors;

/**
 * Yapay zeka çıktılarında alttaki model kimliği ve bilgi kesim (cutoff)
 * sızıntılarını deterministik olarak yakalayıp güvenli bir ifadeyle değiştiren filtre.
 */
final class ModelIdentityRedactor
{
    public const SAFE_IDENTITY_REPLY = 'Ben yapay zeka asistanıyım; teknik altyapıma dair bilgi paylaşamıyorum.';
    private const INLINE_REPLACEMENT = '[ASİSTAN]';
    private const VENDORS = 'Qwen|Tongyi|Alibaba|OpenAI|GPT-?[0-9o.]*|ChatGPT|Llama|Meta\s+AI|Gemini|Google\s+DeepMind|DeepMind|Mistral|DeepSeek|Anthropic|Claude|Cohere|Yi|Ernie|Baidu';

    private const PATTERNS = [
        '/\b(?:I\s*am|I[\x{2019}\']m|Ben)\b[^.\n!?]{0,40}?(?:'.self::VENDORS.'|large\s+language\s+model|büyük\s+dil\s+model\w*|yapay\s+zek\w*\s+model\w*|language\s+model|dil\s+model\w*)\b[^.\n!?]*/iu',
        '/\b(?:independently\s+)?(?:developed|trained|created|built|made|trained\s+up)\s+by\b[^.\n!?]{0,60}?(?:'.self::VENDORS.')\b[^.\n!?]*/iu',
        '/(?:'.self::VENDORS.')\b[^.\n!?]{0,40}?\btaraf\w*\s+(?:geliştiril\w*|eğitil\w*|oluşturul\w*|üretil\w*)\b[^.\n!?]*/iu',
        '/\bTongyi(?:\s+Lab(?:oratory)?|\s+Qianwen)?\b/iu',
        '/\bAlibaba\s+(?:Group|Cloud|DAMO)\b[^.\n!?]*/iu',
        '/\b(?:'.self::VENDORS.')\b[^.\n!?]{0,20}?\b(?:model|serisi|series|family|ailesi)\b/iu',
        '/\b(?:knowledge\s+)?cut[\s\-]?off(?:\s+date)?\b[^.\n!?]*/iu',
        '/\bbilgi\s+kesim\w*\b[^.\n!?]*/iu',
        '/\b(?:training|eğitim)\s+(?:data|veri\w*)\b[^.\n!?]{0,40}?(?:cut|kes|tarih|date|until|kadar)\b[^.\n!?]*/iu',
    ];

    /**
     * @return array{text: string, replaced: int}
     */
    public function redact(string $text): array
    {
        if ($text === '' || !$this->looksSuspicious($text)) {
            return ['text' => $text, 'replaced' => 0];
        }

        $replaced = 0;
        foreach (self::PATTERNS as $pattern) {
            $text = (string) preg_replace_callback(
                $pattern,
                function (array $m) use (&$replaced): string {
                    $replaced++;
                    return self::INLINE_REPLACEMENT;
                },
                $text
            );
        }

        if ($replaced > 0) {
            $text = (string) preg_replace('/(?:' . preg_quote(self::INLINE_REPLACEMENT, '/') . '\s*){2,}/u', self::INLINE_REPLACEMENT . ' ', $text);
            $text = (string) preg_replace('/[ \t]{2,}/u', ' ', $text);
        }

        return ['text' => $text, 'replaced' => $replaced];
    }

    public function looksSuspicious(string $text): bool
    {
        return (bool) preg_match(
            '/(?:' . self::VENDORS . '|language\s+model|dil\s+model|yapay\s+zek|cut[\s\-]?off|bilgi\s+kesim|developed\s+by|trained\s+by|taraf\w*\s+(?:geliş|eğit))/iu',
            $text
        );
    }
}
