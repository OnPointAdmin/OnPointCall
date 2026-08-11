<?php

namespace App\Filament\Resources\ImportBatches\Schemas;

use App\Enums\ImportBatchStatus;
use App\Enums\LeadType;
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
                Select::make('lead_type')
                    ->options(LeadType::class),
                Toggle::make('run_soft_score'),
                TextInput::make('total_rows')->numeric(),
                TextInput::make('inserted_count')->numeric(),
                TextInput::make('duplicate_count')->numeric(),
                TextInput::make('conflict_count')->numeric(),
                TextInput::make('soft_score_pending')->numeric(),
                TextInput::make('soft_score_qualified')->numeric(),
                TextInput::make('soft_score_not_qualified')->numeric(),
                TextInput::make('soft_score_error')->numeric(),
                Textarea::make('error_message')
                    ->columnSpanFull()
                    ->visible(fn (?string $state): bool => filled($state)),
            ]);
    }
}
