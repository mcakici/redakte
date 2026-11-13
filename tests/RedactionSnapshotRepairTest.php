<?php

declare(strict_types=1);

namespace Redakte\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redakte\Support\RedactionSnapshotRepair;

class RedactionSnapshotRepairTest extends TestCase
{
    private static function fixture(): array
    {
        $source = 'İlgili maddeler 24 ve 25. Plaka: 24 ve 25. kisi@example.com';
        $first = strpos($source, '24 ve 25');
        $last = strrpos($source, '24 ve 25');
        $snapshot = ['hash' => hash('sha256', $source),
            'text' => 'İlgili maddeler plateToken. Plaka: plateToken. emailToken',
            'map' => ['plateToken' => '24 ve 25', 'emailToken' => 'kisi@example.com'],
            'types' => ['plateToken' => 'PLAKA', 'emailToken' => 'EPOSTA'], 'spans' => [
                ['start' => $first, 'end' => $first + 8, 'token' => 'plateToken'],
                ['start' => $last, 'end' => $last + 8, 'token' => 'plateToken'],
                ['start' => strpos($source, 'kisi@example.com'), 'end' => strlen($source), 'token' => 'emailToken'],
            ]];

        return [$source, $snapshot];
    }

    public function test_repair_is_occurrence_specific_idempotent_and_works_with_saved_map_only(): void
    {
        [$source, $snapshot] = self::fixture();
        $fixed = RedactionSnapshotRepair::apply($snapshot, $source);

        self::assertSame('İlgili maddeler 24 ve 25. Plaka: plateToken. emailToken', $fixed['text']);
        self::assertCount(2, $fixed['spans']);
        self::assertSame($snapshot['map'], $fixed['map']);
        self::assertSame($fixed, RedactionSnapshotRepair::apply($snapshot));
        self::assertSame($fixed, RedactionSnapshotRepair::apply($fixed, $source));
    }

    public function test_instruction_correction_keeps_a_real_person_sharing_the_token(): void
    {
        $source = 'SAĞLAM HALE GETİR. Davacı: HALE GETİR.';
        $length = strlen('HALE GETİR');
        $first = strpos($source, 'HALE GETİR');
        $last = strrpos($source, 'HALE GETİR');
        $snapshot = ['hash' => hash('sha256', $source), 'text' => 'SAĞLAM token. Davacı: token.',
            'map' => ['token' => 'HALE GETİR'], 'types' => ['token' => 'KISI'], 'spans' => [
                ['start' => $first, 'end' => $first + $length, 'token' => 'token'],
                ['start' => $last, 'end' => $last + $length, 'token' => 'token'],
            ]];

        self::assertSame('SAĞLAM HALE GETİR. Davacı: token.', RedactionSnapshotRepair::apply($snapshot, $source)['text']);
    }

    public static function malformedSnapshots(): array
    {
        [$source, $snapshot] = self::fixture();
        $badHash = $snapshot;
        $badHash['hash'] = hash('sha256', 'changed source');
        $missingSpan = $snapshot;
        array_pop($missingSpan['spans']);
        $badBounds = $snapshot;
        $badBounds['spans'][2]['end'] = strlen($source) + 1;
        $overlap = $snapshot;
        $overlap['spans'][1]['start'] = $overlap['spans'][0]['start'];
        $badMap = $snapshot;
        $badMap['map']['emailToken'] = [];

        return [[$source, $badHash], [$source, $missingSpan], [$source, $badBounds], [$source, $overlap], [$source, $badMap]];
    }

    #[DataProvider('malformedSnapshots')]
    public function test_invalid_or_incomplete_snapshots_are_preserved_without_unmasking(string $source, array $snapshot): void
    {
        self::assertSame($snapshot, RedactionSnapshotRepair::apply($snapshot, $source));
    }
}
