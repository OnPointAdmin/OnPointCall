<?php

namespace App\Filament\Resources\CallingLists\Pages;

use App\Filament\Resources\CallingLists\CallingListResource;
use App\Filament\Resources\CallingLists\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\CallingLists\RelationManagers\ListAssignmentHistoryRelationManager;
use App\Services\CallingLists\CallingListDispositionCountService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Html::make(function (): HtmlString {
                    return new HtmlString(view('filament.resources.calling-lists.disposition-counts', [
                        'items' => app(CallingListDispositionCountService::class)->forList($this->getRecord()),
                    ])->render());
                })->columnSpanFull(),
                $this->getFormContentComponent(),
                $this->getRelationManagersContentComponent(),
            ]);
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
