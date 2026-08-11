<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\Disposition;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\SoftScoreStatus;
use App\Jobs\SoftScoreLeadJob;
use App\Models\CallingList;
use App\Services\Leads\DispositionService;
use App\Services\Leads\LeadMergeService;
use App\Services\Leads\LeadRecycleService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                TextColumn::make('attempt_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('callingList.name')
                    ->label('List')
                    ->searchable(),
                TextColumn::make('lead_type')
                    ->badge(),
                TextColumn::make('soft_score_code')
                    ->toggleable(),
                TextColumn::make('soft_score_status')
                    ->badge()
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
                    ->options(collect(LeadType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                SelectFilter::make('calling_list_id')
                    ->label('Calling list')
                    ->options(fn (): array => CallingList::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('soft_score_status')
                    ->options(collect(SoftScoreStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->recordActions([
                EditAction::make(),
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
                ]),
            ]);
    }
}
