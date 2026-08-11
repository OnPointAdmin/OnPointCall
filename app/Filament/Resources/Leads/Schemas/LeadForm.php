<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\SoftScoreStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('first_name'),
                TextInput::make('last_name'),
                TextInput::make('address'),
                TextInput::make('city'),
                TextInput::make('state'),
                TextInput::make('zip'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                DatePicker::make('date_of_birth'),
                TextInput::make('venue'),
                TextInput::make('event'),
                TextInput::make('external_lead_id'),
                TextInput::make('timezone'),
                Select::make('status')
                    ->options(LeadStatus::class)
                    ->default('holding')
                    ->required(),
                TextInput::make('attempt_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('next_day_part'),
                DateTimePicker::make('last_attempt_at'),
                DateTimePicker::make('callback_at'),
                Select::make('callback_owner_id')
                    ->relationship('callbackOwner', 'name'),
                Select::make('calling_list_id')
                    ->relationship('callingList', 'name'),
                DateTimePicker::make('imported_at'),
                Select::make('import_batch_id')
                    ->relationship('importBatch', 'id'),
                Textarea::make('partner_list')
                    ->columnSpanFull(),
                TextInput::make('queue_rank')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('lead_type')
                    ->options(LeadType::class)
                    ->required(),
                TextInput::make('extra_fields'),
                TextInput::make('soft_score_code'),
                Select::make('soft_score_status')
                    ->options(SoftScoreStatus::class),
                DateTimePicker::make('soft_score_checked_at'),
                Textarea::make('soft_score_last_error')
                    ->columnSpanFull(),
            ]);
    }
}
