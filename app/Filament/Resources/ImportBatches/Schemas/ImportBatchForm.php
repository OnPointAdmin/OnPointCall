<?php

namespace App\Filament\Resources\ImportBatches\Schemas;

use App\Enums\ImportBatchStatus;
use App\Filament\Support\BatchCheckFormSections;
use App\Filament\Support\DncBatchFormSection;
use App\Filament\Support\LeadTypeSelect;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImportBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                BatchCheckFormSections::fullWidth(Section::make('Overview')
                    ->schema([
                        TextInput::make('source_filename')
                            ->label('Source file'),
                        DateTimePicker::make('imported_at')
                            ->label('Imported at'),
                        Select::make('status')
                            ->options(ImportBatchStatus::class),
                        LeadTypeSelect::make(allowCreate: false, activeOnly: false),
                        Textarea::make('error_message')
                            ->label('Batch error')
                            ->columnSpanFull()
                            ->visible(fn (?string $state): bool => filled($state)),
                    ])
                    ->columns(3)),
                BatchCheckFormSections::fullWidth(Section::make('Import results')
                    ->schema([
                        TextInput::make('total_rows')
                            ->label('Total rows')
                            ->numeric(),
                        TextInput::make('inserted_count')
                            ->label('Inserted')
                            ->numeric(),
                        TextInput::make('updated_count')
                            ->label('Updated')
                            ->numeric(),
                        TextInput::make('valid_leads')
                            ->label('Valid leads')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('duplicate_count')
                            ->label('Duplicates')
                            ->numeric(),
                        TextInput::make('conflict_count')
                            ->label('Conflicts')
                            ->numeric(),
                    ])
                    ->columns(3)),
                BatchCheckFormSections::checksRun([
                    Toggle::make('ignore_national_dnc')
                        ->label('TCPA consent (ignore national and state DNC)')
                        ->helperText('When DNC runs, national and state hits are recorded but do not mark the lead DNC. Litigator and internal DNC still block.')
                        ->columnSpanFull(),
                ]),
                ...BatchCheckFormSections::counterSections(),
                ...DncBatchFormSection::sections(),
            ]);
    }
}
