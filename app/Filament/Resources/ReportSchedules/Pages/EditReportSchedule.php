<?php

namespace App\Filament\Resources\ReportSchedules\Pages;

use App\Filament\Resources\ReportSchedules\Actions\SendReportScheduleAction;
use App\Filament\Resources\ReportSchedules\ReportScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReportSchedule extends EditRecord
{
    protected static string $resource = ReportScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendReportScheduleAction::make(),
            DeleteAction::make(),
        ];
    }
}
