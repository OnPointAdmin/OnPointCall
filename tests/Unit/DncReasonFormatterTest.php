<?php

namespace Tests\Unit;

use App\Support\DncReasonFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DncReasonFormatterTest extends TestCase
{
    public function test_formats_national_and_state_with_dates(): void
    {
        $summary = DncReasonFormatter::formatPhones([
            'phone' => [
                'reason' => 'National (USA) 2023-10-20;State (FL) 2023-12-06;;',
                'result_code' => 'D',
                'flags' => ['national', 'state'],
            ],
        ]);

        $this->assertSame(
            'National DNC since 10-20-2023 State DNC since 12-06-2023',
            $summary,
        );
    }

    public function test_formats_state_name_only(): void
    {
        $summary = DncReasonFormatter::formatPhones([
            'phone' => [
                'reason' => ';Florida;;',
                'result_code' => 'D',
                'flags' => ['state'],
            ],
        ]);

        $this->assertSame('State DNC (Florida)', $summary);
    }

    public function test_formats_litigator(): void
    {
        $summary = DncReasonFormatter::formatPhones([
            'phone' => [
                'reason' => 'Litigator',
                'result_code' => 'D',
                'flags' => ['litigator'],
            ],
        ]);

        $this->assertSame('Litigator', $summary);
    }

    public function test_formats_internal_dnc_from_result_code(): void
    {
        $summary = DncReasonFormatter::formatPhones([
            'phone' => [
                'reason' => '',
                'result_code' => 'P',
                'flags' => ['idnc'],
            ],
        ]);

        $this->assertSame('Internal DNC', $summary);
    }

    public function test_formats_multiple_phones(): void
    {
        $summary = DncReasonFormatter::formatPhones([
            'phone' => [
                'reason' => 'National (USA) 2003-06-01;;;',
                'result_code' => 'D',
                'flags' => ['national'],
            ],
            'phone_2' => [
                'reason' => 'Litigator',
                'result_code' => 'D',
                'flags' => ['litigator'],
            ],
        ]);

        $this->assertSame(
            'National DNC since 06-01-2003 · Phone 2: Litigator',
            $summary,
        );
    }

    #[DataProvider('phoneReasonProvider')]
    public function test_formats_individual_phone_reasons(array $phone, ?string $expected): void
    {
        $this->assertSame($expected, DncReasonFormatter::formatPhoneReason($phone));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function phoneReasonProvider(): array
    {
        return [
            'invalid number' => [
                ['reason' => '', 'result_code' => 'I', 'flags' => ['invalid']],
                'Invalid number',
            ],
            'national only' => [
                ['reason' => 'National (USA) 2018-08-16;;;', 'result_code' => 'D', 'flags' => ['national']],
                'National DNC since 08-16-2018',
            ],
            'suppress fallback' => [
                ['reason' => '', 'result_code' => 'D', 'flags' => [], 'suppress' => 'national'],
                'National DNC',
            ],
        ];
    }
}
