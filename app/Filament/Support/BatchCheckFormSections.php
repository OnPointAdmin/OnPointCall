<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class BatchCheckFormSections
{
    /**
     * @param  list<Toggle>  $extraFields
     */
    public static function checksRun(array $extraFields = []): Section
    {
        return self::fullWidth(Section::make('Checks run')
            ->schema([
                Toggle::make('run_soft_score')
                    ->label('Soft Score'),
                Toggle::make('run_rnd_check')
                    ->label('RND'),
                Toggle::make('run_qualification')
                    ->label('Qualification'),
                Toggle::make('run_dnc_check')
                    ->label('DNC'),
                ...$extraFields,
            ])
            ->columns(4));
    }

    /**
     * @return list<Section>
     */
    public static function counterSections(): array
    {
        return [
            self::softScore(),
            self::rnd(),
            self::qualification(),
        ];
    }

    public static function softScore(): Section
    {
        return self::fullWidth(Section::make('Soft Score')
            ->schema([
                TextInput::make('soft_score_pending')
                    ->label('Pending')
                    ->numeric(),
                TextInput::make('soft_score_qualified')
                    ->label('Qualified')
                    ->numeric(),
                TextInput::make('soft_score_not_qualified')
                    ->label('Not qualified')
                    ->numeric(),
                TextInput::make('soft_score_error')
                    ->label('Errors')
                    ->numeric(),
            ])
            ->columns(4)
            ->visible(fn (Get $get): bool => (bool) $get('run_soft_score')));
    }

    public static function rnd(): Section
    {
        return self::fullWidth(Section::make('RND')
            ->schema([
                TextInput::make('rnd_pending')
                    ->label('Pending')
                    ->numeric(),
                TextInput::make('rnd_clear')
                    ->label('Clear')
                    ->numeric(),
                TextInput::make('rnd_reassigned')
                    ->label('Reassigned')
                    ->numeric(),
                TextInput::make('rnd_no_data')
                    ->label('No data')
                    ->numeric(),
                TextInput::make('rnd_error')
                    ->label('Errors')
                    ->numeric(),
            ])
            ->columns(5)
            ->visible(fn (Get $get): bool => (bool) $get('run_rnd_check')));
    }

    public static function qualification(): Section
    {
        return self::fullWidth(Section::make('Qualification')
            ->schema([
                TextInput::make('qualification_pending')
                    ->label('Pending')
                    ->numeric(),
                TextInput::make('qualification_qualified')
                    ->label('Qualified')
                    ->numeric(),
                TextInput::make('qualification_not_qualified')
                    ->label('Not qualified')
                    ->numeric(),
                TextInput::make('qualification_error')
                    ->label('Errors')
                    ->numeric(),
            ])
            ->columns(4)
            ->visible(fn (Get $get): bool => (bool) $get('run_qualification')));
    }

    public static function fullWidth(Section $section): Section
    {
        return $section->columnSpanFull();
    }
}
