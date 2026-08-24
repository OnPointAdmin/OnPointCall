<?php

namespace App\Filament\Resources\AppSettings\Schemas;

use App\Support\CompanyTimezone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AppSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('booking_url_template')
                    ->columnSpanFull(),
                TextInput::make('booking_param_map'),
                TextInput::make('max_attempts')
                    ->required()
                    ->numeric()
                    ->default(6),
                TextInput::make('claim_ttl_minutes')
                    ->required()
                    ->numeric()
                    ->default(20),
                Toggle::make('dashboard_email_enabled')
                    ->required(),
                TimePicker::make('dashboard_email_send_time')
                    ->required(),
                Select::make('dashboard_email_timezone')
                    ->label('Agent timezone')
                    ->options(CompanyTimezone::options())
                    ->required()
                    ->default(CompanyTimezone::DEFAULT)
                    ->helperText('Used for dashboard dates, digest emails, and callback due times.'),
                TextInput::make('soft_score_originator'),
                TextInput::make('soft_score_base_url')
                    ->url(),
            ]);
    }
}
