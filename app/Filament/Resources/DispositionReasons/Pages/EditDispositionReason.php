<?php

namespace App\Filament\Resources\DispositionReasons\Pages;

use App\Filament\Resources\DispositionReasons\DispositionReasonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDispositionReason extends EditRecord
{
    protected static string $resource = DispositionReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
