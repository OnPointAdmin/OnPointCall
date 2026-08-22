<?php

namespace App\Filament\Resources\Cadences\Pages;

use App\Filament\Resources\Cadences\CadenceResource;
use App\Filament\Resources\Cadences\Concerns\ManagesCadenceRecord;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCadence extends EditRecord
{
    use ManagesCadenceRecord;

    protected static string $resource = CadenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action): void {
                    if ($this->record->callingLists()->exists()) {
                        Notification::make()
                            ->title('Cadence in use')
                            ->body('This cadence is assigned to one or more calling lists and cannot be deleted.')
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}
