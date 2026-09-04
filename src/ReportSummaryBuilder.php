<?php

declare(strict_types=1);

namespace Redakte;

/**
 * ReplacementsByType dizisini kullanıcı dostu Türkçe redaksiyon özetine dönüştürür.
 * Örnek: "2 TCKN, 1 IBAN, 1 kişi adı redakte edildi."
 */
final class ReportSummaryBuilder
{
    public function __construct(
        private PatternRegistry $registry,
    ) {}

    /**
     * @param array<string, int> $replacementsByType
     */
    public function build(array $replacementsByType): string
    {
        $filtered = array_filter($replacementsByType, fn (int $count) => $count > 0);

        if (empty($filtered)) {
            return 'Belgede TCKN, IBAN, telefon, e-posta, kişi adı vb. taranan kişisel veri türleri bulunamadı.';
        }

        $entityTypes = $this->registry->getEntityTypes();
        $parts = [];

        foreach ($filtered as $type => $count) {
            $info = $entityTypes[$type] ?? ['label' => $type, 'label_plural' => $type];
            $label = $count === 1 ? $info['label'] : $info['label_plural'];
            $parts[] = "{$count} {$label}";
        }

        return implode(', ', $parts) . ' redakte edildi.';
    }
}
