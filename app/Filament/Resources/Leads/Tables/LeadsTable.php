<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\Disposition;
use App\Enums\DncStatus;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\SoftScoreStatus;
use App\Filament\Actions\ViewDncResultAction;
use App\Filament\Actions\ViewQualificationResultAction;
use App\Jobs\DncScrubJob;
use App\Jobs\QualifyLeadJob;
use App\Jobs\RndLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\CallingList;
use App\Models\Lead;
use App\Models\LeadTypeDefinition;
use App\Services\Leads\DispositionService;
use App\Services\Leads\LeadMergeService;
use App\Services\Leads\LeadRecycleService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('latestDisposition'))
            ->columns([
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('state')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('last_disposition')
                    ->label('Last Disp')
                    ->badge()
                    ->placeholder('—')
                    ->getStateUsing(function (Lead $record): ?string {
                        $value = $record->latestDisposition?->payload['disposition'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return null;
                        }

                        return Disposition::tryFrom($value)?->label() ?? $value;
                    }),
                TextColumn::make('last_attempt_at')
                    ->label('Last Call Date')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('attempt_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('callingList.name')
                    ->label('List')
                    ->searchable(),
                TextColumn::make('file_name')
                    ->label('Source file')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lead_type')
                    ->badge(),
                TextColumn::make('soft_score_code')
                    ->label('Soft Score')
                    ->toggleable(),
                TextColumn::make('soft_score_status')
                    ->label('Soft Score status')
                    ->badge()
                    ->formatStateUsing(fn (?SoftScoreStatus $state): ?string => $state?->label())
                    ->toggleable(),
                TextColumn::make('soft_score_checked_at')
                    ->label('Soft Score last checked')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('qualification_status')
                    ->badge()
                    ->color(fn (?QualificationStatus $state): string => match ($state) {
                        QualificationStatus::Qualified => 'success',
                        QualificationStatus::NotQualified => 'warning',
                        QualificationStatus::Error => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?QualificationStatus $state): ?string => $state?->label())
                    ->tooltip(fn (Lead $record): ?string => $record->qualification_status
                        ? 'View qualification response'
                        : null)
                    ->action(ViewQualificationResultAction::make())
                    ->toggleable(),
                TextColumn::make('dnc_status')
                    ->label('DNC')
                    ->badge()
                    ->color(fn (?DncStatus $state): string => match ($state) {
                        DncStatus::Clear => 'success',
                        DncStatus::Hit, DncStatus::Invalid => 'danger',
                        DncStatus::Error => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?DncStatus $state): ?string => $state?->label())
                    ->tooltip(fn (Lead $record): ?string => $record->dnc_status
                        ? 'View DNC scrub result'
                        : null)
                    ->action(ViewDncResultAction::make())
                    ->toggleable(),
                TextColumn::make('callback_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('imported_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(LeadStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('lead_type')
                    ->options(fn (): array => LeadTypeDefinition::allOptions()),
                SelectFilter::make('calling_list_id')
                    ->label('Calling list')
                    ->options(fn (): array => ['holding' => 'Holding'] + CallingList::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === null || $value === '') {
                            return $query;
                        }

                        if ($value === 'holding') {
                            return $query->whereNull('calling_list_id');
                        }

                        return $query->where('calling_list_id', $value);
                    }),
                SelectFilter::make('last_disposition')
                    ->label('Last Disp')
                    ->options(fn (): array => ['none' => 'None'] + collect(Disposition::cases())
                        ->mapWithKeys(fn (Disposition $disposition): array => [$disposition->value => $disposition->label()])
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === null || $value === '') {
                            return $query;
                        }

                        if ($value === 'none') {
                            return $query->whereDoesntHave('history', function (Builder $history): void {
                                $history->where('event_type', LeadHistoryType::Disposition->value);
                            });
                        }

                        return $query->whereHas('latestDisposition', function (Builder $latest) use ($value): void {
                            $latest->where('payload->disposition', $value);
                        });
                    }),
                SelectFilter::make('soft_score_status')
                    ->options(collect(SoftScoreStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('qualification_status')
                    ->options(collect(QualificationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('dnc_status')
                    ->label('DNC')
                    ->options(collect(DncStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('recycle')
                        ->label('Recycle')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $service = app(LeadRecycleService::class);
                            $count = 0;

                            foreach ($records as $record) {
                                try {
                                    $service->recycle($record, Auth::user());
                                    $count++;
                                } catch (\InvalidArgumentException) {
                                    // skip DNC
                                }
                            }

                            Notification::make()
                                ->title("Recycled {$count} lead(s)")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('markDnc')
                        ->label('Mark DNC')
                        ->color('danger')
                        ->icon('heroicon-o-no-symbol')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $service = app(DispositionService::class);
                            $user = Auth::user();

                            foreach ($records as $record) {
                                if ($record->status !== LeadStatus::Dnc) {
                                    $service->apply($record, $user, Disposition::Dnc);
                                }
                            }

                            Notification::make()
                                ->title('Marked selected leads as DNC')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('moveList')
                        ->label('Move to list')
                        ->icon('heroicon-o-arrows-right-left')
                        ->form([
                            Select::make('calling_list_id')
                                ->label('Target list')
                                ->options(fn (): array => CallingList::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $target = CallingList::query()->findOrFail($data['calling_list_id']);
                            $moved = 0;

                            foreach ($records as $record) {
                                if ($record->lead_type !== $target->lead_type) {
                                    continue;
                                }

                                $record->update(['calling_list_id' => $target->id]);
                                $moved++;
                            }

                            Notification::make()
                                ->title("Moved {$moved} lead(s)")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('mergeDuplicates')
                        ->label('Merge duplicates')
                        ->icon('heroicon-o-link')
                        ->requiresConfirmation()
                        ->modalDescription('Merges selected leads into the first selected lead. History is consolidated.')
                        ->action(function (Collection $records): void {
                            if ($records->count() < 2) {
                                Notification::make()
                                    ->title('Select at least two leads to merge')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $survivor = $records->first();
                            $service = app(LeadMergeService::class);
                            $merged = 0;

                            foreach ($records->skip(1) as $duplicate) {
                                $service->merge($survivor, $duplicate, Auth::user());
                                $merged++;
                            }

                            Notification::make()
                                ->title("Merged {$merged} duplicate lead(s)")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('rerunSoftScore')
                        ->label('Re-run Soft Score')
                        ->icon('heroicon-o-signal')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                SoftScoreLeadJob::dispatch($record->id, $record->import_batch_id, Auth::id());
                            }

                            Notification::make()
                                ->title('Soft Score jobs queued')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('rerunRnd')
                        ->label('Re-run RND')
                        ->icon('heroicon-o-phone-arrow-up-right')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                RndLeadJob::dispatch($record->id, $record->import_batch_id, Auth::id());
                            }

                            Notification::make()
                                ->title('RND jobs queued')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('rerunQualification')
                        ->label('Re-run Qualification')
                        ->icon('heroicon-o-check-badge')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                QualifyLeadJob::dispatch($record->id, $record->import_batch_id, Auth::id());
                            }

                            Notification::make()
                                ->title('Qualification jobs queued')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('rerunDnc')
                        ->label('Re-run DNC')
                        ->icon('heroicon-o-no-symbol')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            DncScrubJob::dispatchForLeadIds(
                                $records->pluck('id')->all(),
                                null,
                                Auth::id(),
                            );

                            Notification::make()
                                ->title('DNC jobs queued')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
