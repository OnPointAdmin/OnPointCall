<?php

namespace App\Filament\Resources\ImportBatches\Schemas;

use App\Enums\ImportBatchStatus;
use App\Filament\Support\LeadTypeSelect;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ImportBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source_filename'),
                Select::make('status')
                    ->options(ImportBatchStatus::class),
                DateTimePicker::make('imported_at'),
                LeadTypeSelect::make(allowCreate: false, activeOnly: false),
                Toggle::make('run_soft_score'),
                Toggle::make('run_rnd_check'),
                Toggle::make('run_qualification'),
                Toggle::make('run_dnc_check'),
                TextInput::make('total_rows')->numeric(),
                TextInput::make('inserted_count')->numeric(),
                TextInput::make('updated_count')->numeric(),
                TextInput::make('valid_leads')
                    ->label('Valid Leads')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('duplicate_count')->numeric(),
                TextInput::make('conflict_count')->numeric(),
                TextInput::make('soft_score_pending')->numeric(),
                TextInput::make('soft_score_qualified')
                    ->label('Soft score done')
                    ->numeric(),
                TextInput::make('soft_score_error')->numeric(),
                TextInput::make('rnd_pending')->numeric(),
                TextInput::make('rnd_clear')->numeric(),
                TextInput::make('rnd_reassigned')->numeric(),
                TextInput::make('rnd_no_data')->numeric(),
                TextInput::make('rnd_error')->numeric(),
                TextInput::make('qualification_pending')->numeric(),
                TextInput::make('qualification_qualified')->numeric(),
                TextInput::make('qualification_not_qualified')->numeric(),
                TextInput::make('qualification_error')->numeric(),
                TextInput::make('dnc_pending')->numeric(),
                TextInput::make('dnc_clear')->numeric(),
                TextInput::make('dnc_hit')->label('DNC hits')->numeric(),
                TextInput::make('dnc_invalid')->numeric(),
                TextInput::make('dnc_error')->numeric(),
                Textarea::make('error_message')
                    ->columnSpanFull()
                    ->visible(fn (?string $state): bool => filled($state)),
            ]);
    }
}
