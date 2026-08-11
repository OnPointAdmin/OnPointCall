<?php

namespace Tests\Unit;

use App\Support\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNormalizerTest extends TestCase
{
    #[DataProvider('phoneProvider')]
    public function test_normalizes_phone_numbers(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNormalizer::normalize($input));
    }

    public static function phoneProvider(): array
    {
        return [
            ['(404) 555-1212', '4045551212'],
            ['1-404-555-1212', '4045551212'],
            ['+14045551212', '4045551212'],
            ['404555121', null],
            ['', null],
            [null, null],
        ];
    }
}
