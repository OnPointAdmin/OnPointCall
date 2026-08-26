<?php

namespace App\Support;

class CsvHeader
{
    public static function normalize(string $header): string
    {
        return strtolower(trim(str_replace("\xEF\xBB\xBF", '', $header)));
    }
}
