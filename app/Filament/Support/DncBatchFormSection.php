<?php

namespace App\Filament\Support;

use App\Models\ImportBatch;
use App\Models\QualifyBatch;
use App\Services\Dnc\DncBatchBreakdownService;
use App\Support\DncBatchBreakdown;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class DncBatchFormSection
{
    /**
     * @return list<Section>
     */
    public static function sections(): array
    {
        return [
            BatchCheckFormSections::fullWidth(Section::make('DNC')
                ->schema([
                    TextInput::make('dnc_pending')
                        ->label('Pending')
                        ->numeric(),
                    TextInput::make('dnc_clear')
                        ->label('Clear')
                        ->numeric(),
                    TextInput::make('dnc_invalid')
                        ->label('Invalid number')
                        ->numeric(),
                    TextInput::make('dnc_error')
                        ->label('Errors')
                        ->numeric(),
                    self::breakdownField('dnc_hit_litigator', 'Litigator'),
                    self::breakdownField('dnc_hit_internal', 'Internal DNC'),
                    self::breakdownField('dnc_hit_national', 'National DNC'),
                    self::breakdownField('dnc_hit_state', 'State DNC'),
                    self::breakdownField('dnc_hit_generic', 'DNC'),
                    self::breakdownField('dnc_ignored_national', 'National DNC (ignored)', 'nationalIgnored')
                        ->visible(fn (ImportBatch|QualifyBatch|null $record, Get $get): bool => self::showsIgnoredSection($record, $get)),
                    self::breakdownField('dnc_ignored_state', 'State DNC (ignored)', 'stateIgnored')
                        ->visible(fn (ImportBatch|QualifyBatch|null $record, Get $get): bool => self::showsIgnoredSection($record, $get)),
                ])
                ->columns(4)
                ->visible(fn (Get $get): bool => (bool) $get('run_dnc_check'))),
        ];
    }

    private static function breakdownField(string $name, string $label, ?string $field = null): TextInput
    {
        $field ??= match ($name) {
            'dnc_hit_litigator' => 'litigator',
            'dnc_hit_internal' => 'internal',
            'dnc_hit_national' => 'national',
            'dnc_hit_state' => 'state',
            'dnc_hit_generic' => 'dnc',
            default => str_replace('dnc_ignored_', '', $name).'Ignored',
        };

        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->disabled()
            ->dehydrated(false)
            ->default(fn (ImportBatch|QualifyBatch|null $record): ?int => $record === null
                ? 0
                : self::breakdownCount($record, $field));
    }

    private static function breakdownCount(ImportBatch|QualifyBatch $record, string $field): int
    {
        $breakdown = self::breakdown($record);

        return match ($field) {
            'litigator' => $breakdown->litigator,
            'internal' => $breakdown->internal,
            'national' => $breakdown->national,
            'state' => $breakdown->state,
            'dnc' => $breakdown->dnc,
            'nationalIgnored' => $breakdown->nationalIgnored,
            'stateIgnored' => $breakdown->stateIgnored,
            default => 0,
        };
    }

    private static function breakdown(ImportBatch|QualifyBatch $record): DncBatchBreakdown
    {
        $service = app(DncBatchBreakdownService::class);

        return $record instanceof ImportBatch
            ? $service->forImportBatch($record)
            : $service->forQualifyBatch($record);
    }

    private static function showsIgnoredSection(ImportBatch|QualifyBatch|null $record, Get $get): bool
    {
        if ($record instanceof ImportBatch && $record->ignore_national_dnc) {
            return true;
        }

        if ($record !== null && self::breakdown($record)->ignoredHits() > 0) {
            return true;
        }

        return (bool) $get('ignore_national_dnc');
    }
}
