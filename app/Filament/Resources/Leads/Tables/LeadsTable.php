<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadStatus;
use App\Enums\LeadTablePreset;
use App\Filament\Actions\ChangeNextDayPartAction;
use App\Filament\Actions\ReassignCallbackAction;
use App\Filament\Resources\Leads\Schemas\LeadForm;
use App\Jobs\DncScrubJob;
use App\Jobs\QualifyLeadJob;
use App\Jobs\RndLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\CallingList;
use App\Services\Import\HoldingReleaseService;
use App\Services\Leads\DispositionService;
use App\Services\Leads\LeadMergeService;
use App\Services\Leads\LeadRecycleService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class LeadsTable
{
    public static function configure(Table $table, LeadTablePreset $preset = LeadTablePreset::AllLeads): Table
    {
        $filters = LeadsTableFilters::make($preset, $table);
        $defaultSort = $preset->defaultSort();

        $table = $table
            ->modifyQueryUsing(function (Builder $query) use ($preset): Builder {
                $query->with(['latestDisposition', 'callbackOwner', 'callingList']);

                if ($preset->appliesAssignableScope() && ! $preset->usesPoolSourceScope()) {
                    app(HoldingReleaseService::class)->applyAssignableScopesToQuery($query);
                }

                if ($preset === LeadTablePreset::Callbacks) {
                    $query->where('status', LeadStatus::Callback);
                }

                return $query;
            })
            ->columns(LeadsTableColumns::make($preset))
            ->defaultSort($defaultSort['column'], $defaultSort['direction'])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->recordActions(self::recordActions($preset))
            ->toolbarActions([
                BulkActionGroup::make(self::bulkActions()),
            ])
            ->filters($filters, layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1);

        return $table;
    }

    /**
     * @return list<ViewAction|Action>
     */
    private static function recordActions(LeadTablePreset $preset): array
    {
        $view = $preset->usesSlideOverView()
            ? ViewAction::make()
                ->slideOver()
                ->modalWidth(Width::Full)
                ->schema(fn (Schema $schema): Schema => LeadForm::configure($schema, withHistory: true)->columns(2))
            : ViewAction::make();

        return [
            $view,
            ChangeNextDayPartAction::make(),
            ReassignCallbackAction::make(),
        ];
    }

    /**
     * @return list<BulkAction>
     */
    private static function bulkActions(): array
    {
        return [
            ChangeNextDayPartAction::makeBulk(),
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
                            $service->apply($record, $user, 'dnc');
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
        ];
    }
}
