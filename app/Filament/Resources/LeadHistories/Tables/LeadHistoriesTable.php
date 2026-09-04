<?php

namespace App\Filament\Resources\LeadHistories\Tables;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Filament\Resources\LeadHistories\Schemas\LeadHistoryInfolist;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\CallingList;
use App\Models\LeadHistory;
use App\Services\Dashboard\ManagerDashboardService;
use App\Support\CompanyTimezone;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LeadHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['lead.callingList', 'actor']))
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('When')
                    ->formatStateUsing(fn (LeadHistory $record): ?string => CompanyTimezone::display(
                        $record->occurred_at,
                        $record->company_id,
                        'M j, Y g:i A T',
                    ))
                    ->sortable(),
                TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (LeadHistoryType $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('lead.phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable()
                    ->url(fn (LeadHistory $record): ?string => $record->lead_id
                        ? LeadResource::getUrl('view', ['record' => $record->lead_id])
                        : null)
                    ->placeholder('—'),
                TextColumn::make('lead.last_name')
                    ->label('Name')
                    ->formatStateUsing(fn (LeadHistory $record): string => trim(
                        ($record->lead?->first_name ?? '').' '.($record->lead?->last_name ?? ''),
                    ) ?: '—')
                    ->searchable(['lead.first_name', 'lead.last_name'])
                    ->sortable(),
                TextColumn::make('lead.callingList.name')
                    ->label('List')
                    ->placeholder('Holding')
                    ->sortable(),
                TextColumn::make('payload.disposition')
                    ->label('Disposition')
                    ->badge()
                    ->formatStateUsing(function (?string $state): ?string {
                        if (! is_string($state) || $state === '') {
                            return null;
                        }

                        return Disposition::tryFrom($state)?->label() ?? $state;
                    })
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('detailLabel')
                    ->label('Details')
                    ->getStateUsing(fn (LeadHistory $record): string => $record->detailLabel())
                    ->wrap()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('event_type', $direction)),
                TextColumn::make('noteLabel')
                    ->label('Note')
                    ->getStateUsing(fn (LeadHistory $record): ?string => $record->noteLabel())
                    ->placeholder('—')
                    ->wrap()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('payload->note', $direction)),
                TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Logged at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('date_range')
                    ->label('Date range')
                    ->schema([
                        Select::make('preset')
                            ->label('Preset')
                            ->options([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'this_week' => 'This Week',
                                '' => 'Custom range',
                            ])
                            ->default('today'),
                        DatePicker::make('start_date')
                            ->label('Start Date'),
                        DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->default(function (): array {
                        $timezone = CompanyTimezone::forAuthenticated();
                        $today = Carbon::now($timezone)->toDateString();

                        return [
                            'preset' => 'today',
                            'start_date' => $today,
                            'end_date' => $today,
                        ];
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $range = self::resolveDateRange($data);

                        if ($range === null) {
                            return $query;
                        }

                        return $query->whereBetween('occurred_at', [$range['start'], $range['end']]);
                    })
                    ->indicateUsing(function (array $data): array {
                        $range = self::resolveDateRange($data);

                        if ($range === null) {
                            return [];
                        }

                        $timezone = CompanyTimezone::forAuthenticated();
                        $label = Carbon::parse($range['start'])->timezone($timezone)->format('M j, Y')
                            .' – '
                            .Carbon::parse($range['end'])->timezone($timezone)->format('M j, Y');

                        return [Indicator::make("Date: {$label}")];
                    }),
                SelectFilter::make('event_type')
                    ->label('Event')
                    ->multiple()
                    ->options(collect(LeadHistoryType::cases())->mapWithKeys(
                        fn (LeadHistoryType $type): array => [$type->value => $type->label()],
                    )),
                SelectFilter::make('activity')
                    ->options([
                        'calls' => 'Calls',
                        'system' => 'System',
                        'lead_changes' => 'Lead changes',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === null || $value === '') {
                            return $query;
                        }

                        $types = match ($value) {
                            'calls' => [
                                LeadHistoryType::Disposition,
                                LeadHistoryType::Skip,
                            ],
                            'system' => [
                                LeadHistoryType::SoftScore,
                                LeadHistoryType::RndCheck,
                                LeadHistoryType::Qualification,
                                LeadHistoryType::DncCheck,
                                LeadHistoryType::DncPush,
                            ],
                            'lead_changes' => [
                                LeadHistoryType::Assign,
                                LeadHistoryType::Release,
                                LeadHistoryType::Recycle,
                                LeadHistoryType::Merge,
                                LeadHistoryType::Claim,
                                LeadHistoryType::ClaimExpire,
                                LeadHistoryType::StatusChange,
                                LeadHistoryType::FieldEdit,
                            ],
                            default => [],
                        };

                        if ($types === []) {
                            return $query;
                        }

                        return $query->whereIn(
                            'event_type',
                            array_map(fn (LeadHistoryType $type): string => $type->value, $types),
                        );
                    }),
                SelectFilter::make('actor_id')
                    ->label('Actor')
                    ->relationship('actor', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('disposition')
                    ->options(collect(Disposition::cases())->mapWithKeys(
                        fn (Disposition $disposition): array => [$disposition->value => $disposition->label()],
                    ))
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $query->where('payload->disposition', $value);
                    }),
                SelectFilter::make('calling_list_id')
                    ->label('Calling list')
                    ->options(fn (): array => ['holding' => 'Holding'] + CallingList::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $query->whereHas('lead', function (Builder $lead) use ($value): void {
                            if ($value === 'holding') {
                                $lead->whereNull('calling_list_id');
                            } else {
                                $lead->where('calling_list_id', $value);
                            }
                        });
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth(Width::FourExtraLarge)
                    ->schema(fn (Schema $schema): Schema => LeadHistoryInfolist::configure($schema)->columns(2)),
            ])
            ->toolbarActions([]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{start: Carbon, end: Carbon}|null
     */
    private static function resolveDateRange(array $data): ?array
    {
        $companyId = (int) Auth::user()?->company_id;
        $dashboard = app(ManagerDashboardService::class);
        $timezone = $dashboard->companyTimezone($companyId);

        $preset = $data['preset'] ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        if (filled($preset) && in_array($preset, ['today', 'yesterday', 'this_week'], true)) {
            $dates = $dashboard->presetDates($preset, $timezone);

            return $dashboard->dateRange($companyId, $dates['start'], $dates['end']);
        }

        if (filled($startDate) && filled($endDate)) {
            return $dashboard->dateRange(
                $companyId,
                Carbon::parse($startDate, $timezone),
                Carbon::parse($endDate, $timezone),
            );
        }

        return null;
    }
}
