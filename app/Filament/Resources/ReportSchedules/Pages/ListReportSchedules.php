<?php

namespace App\Filament\Resources\ReportSchedules\Pages;

use App\Filament\Resources\ReportSchedules\ReportScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReportSchedules extends ListRecords
{
    protected static string $resource = ReportScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
