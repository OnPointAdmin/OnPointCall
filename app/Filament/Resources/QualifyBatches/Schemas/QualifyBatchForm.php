<?php

namespace App\Filament\Resources\QualifyBatches\Schemas;

use App\Enums\QualifyBatchStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class QualifyBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user.name')
                    ->label('Queued by'),
                Select::make('status')
                    ->options(QualifyBatchStatus::class),
                TextInput::make('lead_count')
                    ->numeric(),
                Toggle::make('run_soft_score')
                    ->label('Soft Score'),
                Toggle::make('run_rnd_check')
                    ->label('RND'),
                Toggle::make('run_qualification')
                    ->label('Qualification'),
                Toggle::make('run_dnc_check')
                    ->label('DNC'),
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
