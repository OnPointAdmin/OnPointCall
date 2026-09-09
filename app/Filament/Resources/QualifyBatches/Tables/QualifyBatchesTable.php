<?php

namespace App\Filament\Resources\QualifyBatches\Tables;

use App\Enums\QualifyBatchStatus;
use App\Models\QualifyBatch;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class QualifyBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('health')
                    ->label('Health')
                    ->html()
                    ->getStateUsing(function (QualifyBatch $record): HtmlString {
                        $color = match ($record->healthStatus()) {
                            'ok' => '#22c55e',
                            'pending' => '#f59e0b',
                            'error' => '#ef4444',
                            default => '#9ca3af',
                        };

                        return new HtmlString(
                            '<span style="display:inline-block;width:0.875rem;height:0.875rem;border-radius:9999px;background:'.$color.';box-shadow:0 0 0 2px rgba(255,255,255,0.9);"></span>'
                        );
                    })
                    ->tooltip(fn (QualifyBatch $record): string => $record->healthLabel())
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Who')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('lead_count')
                    ->label('Leads')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('run_soft_score')
                    ->label('Soft Score')
                    ->boolean(),
                IconColumn::make('run_rnd_check')
                    ->label('RND')
                    ->boolean(),
                IconColumn::make('run_qualification')
                    ->label('Qualification')
                    ->boolean(),
                IconColumn::make('run_dnc_check')
                    ->label('DNC')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(QualifyBatchStatus::cases())->mapWithKeys(
                        fn (QualifyBatchStatus $status): array => [$status->value => $status->label()]
                    )),
                SelectFilter::make('health')
                    ->label('Health')
                    ->options([
                        'ok' => 'Healthy',
                        'pending' => 'In progress',
                        'error' => 'Errors',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return $query;
                        }

                        return $query->health($value);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
