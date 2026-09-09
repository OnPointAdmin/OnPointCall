<?php

namespace App\Filament\Resources\QualifyBatches\Pages;

use App\Filament\Pages\QualifyLeads;
use App\Filament\Resources\QualifyBatches\QualifyBatchResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListQualifyBatches extends ListRecords
{
    protected static string $resource = QualifyBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('qualify')
                ->label('Qualify Leads')
                ->url(QualifyLeads::getUrl()),
        ];
    }
}
