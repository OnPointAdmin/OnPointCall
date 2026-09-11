<?php

namespace App\Filament\Support;

use App\Support\CadenceDefaults;
use Filament\Forms\Components\Select;

class NextDayPartField
{
    public static function make(): Select
    {
        return Select::make('next_day_part')
            ->label('Next day part')
            ->options(CadenceDefaults::selectOptionsWithAny())
            ->required()
            ->helperText('Any serves the lead in the current open window. A specific part waits for that window. Attempt count and last call time stay the same.');
    }
}
