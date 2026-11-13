<?php

declare(strict_types=1);

namespace Redakte\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Redakte\PatternRegistry;
use Redakte\RedactionResult;
use Redakte\RedactionService;
use Redakte\RedactionValidator;
use Redakte\Redactor;
use Redakte\ReportSummaryBuilder;
use Redakte\Token\RedactionSession;

class PlateRedactionTest extends TestCase
{
    public static function ordinaryNumbers(): array
    {
        return array_map(static fn ($text) => [$text], [
            '24 ve 25 maddeleri; 60 ile 72 arasında; 12 ila 34.',
            '12 TL 50 kuruş; 20 kg 50 gr; 30 cm 100 mm.',
            '19 May 2026; 12.34 AB 123.56; ş34 AB 123ğ.',
            "24\nve\n25; 60\r\nile\r\n72.",
            "24\u{00a0}ve\u{00a0}25; 2\u{200b}4 ve 25.",
            '24 VE 25 maddeleri; 60 ILE 72 arasında; 84 ve 89; 2026/12345; 15.11.2026.',
        ]);
    }

    #[DataProvider('ordinaryNumbers')]
    public function test_ordinary_numbers_are_neither_masked_nor_reported_as_residual_plates(string $text): void
    {
        $result = $this->redactor->redact($text, ['strict' => true]);

        self::assertSame($text, $result->redactedText);
        self::assertNotContains('UNREDACTED_HIGH_RISK_CANDIDATE', array_column($result->warnings, 'code'));
    }

    public static function realPlates(): array
    {
        return [
            ['34 ABC 123'], ['06 A 1234'], ['81-ZZ-9999'], ['34abc123'],
            ['24VE25'], ['24-VE-25'], ['Plaka: 24 VE 25'], ['24 ve 25 plakalı araç'],
            ['24 VE 25'], ['34 TL 123'], ['60 ILE 72'], ['Araç: 60 ILE 72'],
            ["Plakası: 3\u{200b}4 ABC 123"], ["34\u{00a0}ABC\u{00a0}123"], ["34 ABC\r\n123"],
        ];
    }

    #[DataProvider('realPlates')]
    public function test_real_plates_keep_masking_original_offsets_and_round_trip(string $text): void
    {
        $session = RedactionSession::create();
        $result = $this->redactor->redact($text, ['strict' => true, 'session' => $session, 'token_format' => 'namespaced']);

        self::assertSame(1, $result->replacementsByType['PLAKA'] ?? 0);
        self::assertSame($text, $session->getMap()->unmask($result->redactedText));
        foreach ($result->spans as $span) {
            self::assertSame($span->originalValue, substr($text, $span->startOffset, $span->length()));
        }
    }

    public function test_residual_validator_still_warns_about_real_unmasked_plates(): void
    {
        $text = '24 ve 25; Plaka: 34 ABC 123.';
        $result = (new RedactionValidator($this->service->getRegistry()))->validate($text, new RedactionResult(
            spans: [], redactedText: $text, replacementsByType: [], reportSummary: '',
        ));
        $warnings = array_values(array_filter($result->warnings, static fn ($warning) => $warning->code === 'UNREDACTED_HIGH_RISK_CANDIDATE'));

        self::assertCount(1, $warnings);
        self::assertSame('PLAKA', $warnings[0]->entityType);
    }

    public function test_plate_exclusions_use_original_offsets_after_unicode_normalization(): void
    {
        $registry = new PatternRegistry(['exclude_patterns' => ['~34\x{00a0}ABC 123~u']]);
        $redactor = new Redactor(new RedactionService($registry, new ReportSummaryBuilder($registry)));
        $source = "ş\u{200b} 34\u{00a0}ABC 123; 06 A 1234";
        $result = $redactor->redact($source);

        self::assertStringContainsString("34\u{00a0}ABC 123", $result->redactedText);
        self::assertStringNotContainsString('06 A 1234', $result->redactedText);
        self::assertSame(1, $result->replacementsByType['PLAKA']);
    }
}
