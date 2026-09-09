<?php

namespace App\Filament\Resources\QualifyBatches\Schemas;

use App\Enums\QualifyBatchStatus;
use App\Filament\Support\BatchCheckFormSections;
use App\Filament\Support\DncBatchFormSection;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QualifyBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                BatchCheckFormSections::fullWidth(Section::make('Overview')
                    ->schema([
                        TextInput::make('user.name')
                            ->label('Queued by'),
                        DateTimePicker::make('created_at')
                            ->label('Queued at'),
                        Select::make('status')
                            ->options(QualifyBatchStatus::class),
                        Textarea::make('error_message')
                            ->label('Batch error')
                            ->columnSpanFull()
                            ->visible(fn (?string $state): bool => filled($state)),
                    ])
                    ->columns(3)),
                BatchCheckFormSections::fullWidth(Section::make('Run summary')
                    ->schema([
                        TextInput::make('lead_count')
                            ->label('Leads')
                            ->numeric(),
                    ])
                    ->columns(3)),
                BatchCheckFormSections::checksRun(),
                ...BatchCheckFormSections::counterSections(),
                ...DncBatchFormSection::sections(),
            ]);
    }
}
