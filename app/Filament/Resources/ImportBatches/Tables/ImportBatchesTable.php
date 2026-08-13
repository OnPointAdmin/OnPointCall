<?php

namespace App\Filament\Resources\ImportBatches\Tables;

use App\Models\ImportBatch;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ImportBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('health')
                    ->label('Health')
                    ->html()
                    ->getStateUsing(function (ImportBatch $record): HtmlString {
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
                    ->tooltip(fn (ImportBatch $record): string => $record->healthLabel())
                    ->alignCenter(),
                TextColumn::make('source_filename')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('imported_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('total_rows')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('inserted_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('valid_leads')
                    ->label('Valid Leads')
                    ->numeric()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

                        return $query->orderByRaw('(inserted_count - COALESCE(rnd_reassigned, 0)) '.$direction);
                    }),
                TextColumn::make('duplicate_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('conflict_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lead_type')
                    ->badge()
                    ->searchable(),
                IconColumn::make('run_soft_score')
                    ->boolean(),
                IconColumn::make('run_rnd_check')
                    ->boolean(),
                TextColumn::make('soft_score_pending')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('soft_score_qualified')
                    ->label('Soft score done')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('soft_score_error')
                    ->label('Soft score error')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rnd_pending')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rnd_clear')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rnd_reassigned')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rnd_no_data')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rnd_error')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
