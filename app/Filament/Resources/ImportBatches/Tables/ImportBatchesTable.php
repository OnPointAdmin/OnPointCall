<?php

namespace App\Filament\Resources\ImportBatches\Tables;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\LeadTypeDefinition;
use App\Support\CompanyTimezone;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
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

                        return $query->orderByRaw('(inserted_count - COALESCE(rnd_reassigned, 0) - COALESCE(dnc_hit, 0) - COALESCE(dnc_invalid, 0)) '.$direction);
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
                IconColumn::make('run_qualification')
                    ->boolean(),
                IconColumn::make('run_dnc_check')
                    ->boolean(),
                IconColumn::make('ignore_national_dnc')
                    ->label('TCPA consent')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                TextColumn::make('qualification_pending')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('qualification_qualified')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('qualification_not_qualified')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('qualification_error')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('dnc_pending')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('dnc_clear')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('dnc_hit')
                    ->label('DNC hits')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('dnc_invalid')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('dnc_error')
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
                SelectFilter::make('status')
                    ->options(collect(ImportBatchStatus::cases())->mapWithKeys(
                        fn (ImportBatchStatus $status): array => [$status->value => $status->label()]
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
                SelectFilter::make('lead_type')
                    ->options(fn (): array => LeadTypeDefinition::allOptions()),
                Filter::make('imported_at')
                    ->label('Imported')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Start Date'),
                        DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $timezone = CompanyTimezone::forAuthenticated();

                        if (filled($data['start_date'] ?? null)) {
                            $query->where(
                                'imported_at',
                                '>=',
                                Carbon::parse($data['start_date'], $timezone)->startOfDay()->utc(),
                            );
                        }

                        if (filled($data['end_date'] ?? null)) {
                            $query->where(
                                'imported_at',
                                '<=',
                                Carbon::parse($data['end_date'], $timezone)->endOfDay()->utc(),
                            );
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $timezone = CompanyTimezone::forAuthenticated();
                        $startDate = $data['start_date'] ?? null;
                        $endDate = $data['end_date'] ?? null;

                        if (! filled($startDate) && ! filled($endDate)) {
                            return [];
                        }

                        $startLabel = filled($startDate)
                            ? Carbon::parse($startDate, $timezone)->format('M j, Y')
                            : '…';
                        $endLabel = filled($endDate)
                            ? Carbon::parse($endDate, $timezone)->format('M j, Y')
                            : '…';

                        return [Indicator::make("Imported: {$startLabel} – {$endLabel}")];
                    }),
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
