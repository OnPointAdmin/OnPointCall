<?php

namespace App\Filament\Resources\CallingLists\Pages;

use App\Filament\Resources\CallingLists\CallingListResource;
use App\Filament\Resources\CallingLists\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\CallingLists\RelationManagers\ListAssignmentHistoryRelationManager;
use App\Services\CallingLists\CallingListDispositionCountService;
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
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
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
                    Section::make('Disposition counts')
                        ->compact()
                        ->columnSpan(['lg' => 1])
                        ->schema([
                            Html::make(function (): HtmlString {
                                return new HtmlString(view('filament.resources.calling-lists.disposition-counts', [
                                    'items' => app(CallingListDispositionCountService::class)->forList($this->getRecord()),
                                    'showHeading' => false,
                                ])->render());
                            }),
                        ]),
                ]),
                $this->getRelationManagersContentComponent(),
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
