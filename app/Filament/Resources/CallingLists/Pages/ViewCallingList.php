<?php

namespace App\Filament\Resources\CallingLists\Pages;

use App\Filament\Resources\CallingLists\CallingListResource;
use App\Filament\Resources\CallingLists\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\CallingLists\RelationManagers\ListAssignmentHistoryRelationManager;
use App\Filament\Resources\CallingLists\Widgets\CallingListDispositionStats;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Widgets\WidgetConfiguration;

class ViewCallingList extends ViewRecord
{
    protected static string $resource = CallingListResource::class;

    public bool $showLeads = false;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleLeads')
                ->label(fn (): string => $this->showLeads ? 'Hide leads' : 'Show leads')
                ->color('gray')
                ->action(function (): void {
                    $this->showLeads = ! $this->showLeads;
                    $this->cachedRelationManagers = null;
                }),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @return array<class-string<CallingListDispositionStats> | WidgetConfiguration>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            CallingListDispositionStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'record' => $this->getRecord(),
        ];
    }

    /**
     * @return array<class-string<ListAssignmentHistoryRelationManager|LeadsRelationManager>>
     */
    protected function getAllRelationManagers(): array
    {
        $managers = [
            ListAssignmentHistoryRelationManager::class,
        ];

        if ($this->showLeads) {
            $managers[] = LeadsRelationManager::class;
        }

        return $managers;
    }

    public function getRelationManagersContentComponent(): Component
    {
        $managers = $this->getCachedRelationManagers();
        $ownerRecord = $this->getRecord();
        $managerLivewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        if ($managers === []) {
            return Group::make()->hidden();
        }

        return Group::make(
            collect(array_values($managers))
                ->map(function (string $manager, int $key) use ($managerLivewireData): Livewire {
                    return Livewire::make(
                        $manager,
                        [...$managerLivewireData, ...$manager::getDefaultProperties()],
                    )->key("{$manager}-{$key}");
                })
                ->all()
        );
    }
}
