<?php

namespace App\Filament\Resources\CallingLists\Pages;

use App\Filament\Resources\CallingLists\Actions\EditCallbackScheduleAction;
use App\Filament\Resources\CallingLists\Actions\ImportAgentCallbacksAction;
use App\Filament\Resources\CallingLists\CallingListResource;
use App\Filament\Resources\CallingLists\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\CallingLists\RelationManagers\ListAssignmentHistoryRelationManager;
use App\Models\BookingCallbackSchedule;
use App\Models\BookingCallbackSyncRun;
use App\Models\CallingList;
use App\Services\CallingLists\CallingListDispositionCountService;
use App\Services\Leads\AgentCallbacksProvisioner;
use App\Services\Leads\DialableInventoryService;
use App\Support\CompanyTimezone;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewCallingList extends ViewRecord
{
    protected static string $resource = CallingListResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->getRecord() instanceof CallingList && AgentCallbacksProvisioner::isList($this->getRecord())) {
            $actions[] = ImportAgentCallbacksAction::make();
            $actions[] = EditCallbackScheduleAction::make();
        }

        $actions[] = EditAction::make();
        $actions[] = DeleteAction::make();

        return $actions;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])->schema([
                    $this->getFormContentComponent()
                        ->columnSpan(['lg' => 2]),
                    Group::make([
                        Section::make('Queue status')
                            ->compact()
                            ->schema([
                                Html::make(function (): HtmlString {
                                    $inventory = app(DialableInventoryService::class)
                                        ->forList($this->getRecord());

                                    return new HtmlString(view('filament.resources.calling-lists.queue-status', [
                                        'rows' => $inventory->queueStatusRows(),
                                        'timezone' => $inventory->timezone,
                                        'showHeading' => false,
                                    ])->render());
                                }),
                            ]),
                        Section::make('Disposition counts')
                            ->compact()
                            ->schema([
                                Html::make(function (): HtmlString {
                                    return new HtmlString(view('filament.resources.calling-lists.disposition-counts', [
                                        'items' => app(CallingListDispositionCountService::class)->forList($this->getRecord()),
                                        'showHeading' => false,
                                    ])->render());
                                }),
                            ]),
                    ])->columnSpan(['lg' => 1]),
                ]),
                $this->getRelationManagersContentComponent(),
                ...array_filter([$this->bookingCallbackSection()]),
            ]);
    }

    private function bookingCallbackSection(): ?Section
    {
        $record = $this->getRecord();

        if (! $record instanceof CallingList || ! AgentCallbacksProvisioner::isList($record)) {
            return null;
        }

        return Section::make('Agent Callbacks import')
            ->compact()
            ->schema([
                Html::make(function () use ($record): HtmlString {
                    $run = BookingCallbackSyncRun::latestForCompany($record->company_id);
                    $schedule = BookingCallbackSchedule::withoutGlobalScopes()
                        ->where('company_id', $record->company_id)
                        ->first();

                    return new HtmlString(view('filament.resources.calling-lists.booking-callback-sync', [
                        'run' => $run,
                        'errors' => $run?->errors()->orderBy('id')->get() ?? collect(),
                        'schedule' => $schedule,
                        'timezone' => CompanyTimezone::for($record->company_id),
                    ])->render());
                }),
            ]);
    }

    /**
     * @return array<class-string<ListAssignmentHistoryRelationManager|LeadsRelationManager>>
     */
    protected function getAllRelationManagers(): array
    {
        return [
            ListAssignmentHistoryRelationManager::class,
            LeadsRelationManager::class,
        ];
    }

    public function getRelationManagersContentComponent(): Component
    {
        $ownerRecord = $this->getRecord();
        $managerLivewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        $section = function (string $heading, string $manager) use ($managerLivewireData): Section {
            return Section::make($heading)
                ->collapsible()
                ->collapsed()
                ->compact()
                ->columnSpanFull()
                ->schema([
                    Livewire::make(
                        $manager,
                        [...$managerLivewireData, ...$manager::getDefaultProperties()],
                    )->key($manager)->lazy(),
                ]);
        };

        return Group::make([
            $section('Assignment history', ListAssignmentHistoryRelationManager::class),
            $section('Leads in this list', LeadsRelationManager::class),
        ]);
    }
}
