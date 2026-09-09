<?php

namespace Tests\Unit;

use App\Enums\SoftScoreStatus;
use App\Support\SoftScoreCounters;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SoftScoreCountersTest extends TestCase
{
    #[DataProvider('notQualifiedCodes')]
    public function test_nq_and_blank_codes_are_not_qualified(?string $code): void
    {
        $this->assertTrue(SoftScoreCounters::isNotQualifiedCode($code));
        $this->assertSame(
            'soft_score_not_qualified',
            SoftScoreCounters::completedColumn(SoftScoreStatus::Complete, $code),
        );
    }

    /**
     * @return list<array{0: ?string}>
     */
    public static function notQualifiedCodes(): array
    {
        return [
            ['NQ'],
            ['nq'],
            [' Nq '],
            [''],
            [null],
            ['  '],
        ];
    }

    #[DataProvider('qualifiedCodes')]
    public function test_score_bands_are_qualified(string $code): void
    {
        $this->assertFalse(SoftScoreCounters::isNotQualifiedCode($code));
        $this->assertSame(
            'soft_score_qualified',
            SoftScoreCounters::completedColumn(SoftScoreStatus::Complete, $code),
        );
        $this->assertSame(
            'soft_score_qualified',
            SoftScoreCounters::completedColumn(SoftScoreStatus::Recent, $code),
        );
    }

    /**
     * @return list<array{0: string}>
     */
    public static function qualifiedCodes(): array
    {
        return [
            ['Q'],
            ['PC1'],
            ['A'],
            ['A1'],
            ['B2'],
        ];
    }

    public function test_error_and_pending_ignore_the_code(): void
    {
        $this->assertSame(
            'soft_score_error',
            SoftScoreCounters::completedColumn(SoftScoreStatus::Error, 'NQ'),
        );
        $this->assertNull(SoftScoreCounters::completedColumn(SoftScoreStatus::Pending, 'Q'));
    }
}
