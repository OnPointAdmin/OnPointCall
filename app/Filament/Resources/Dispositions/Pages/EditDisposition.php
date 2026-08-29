<?php

namespace App\Filament\Resources\Dispositions\Pages;

use App\Enums\LeadHistoryType;
use App\Filament\Resources\Dispositions\DispositionResource;
use App\Models\LeadHistory;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDisposition extends EditRecord
{
    protected static string $resource = DispositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->is_system)
                ->before(function (): void {
                    if ($this->record->is_system) {
                        Notification::make()
                            ->title('Standard dispositions cannot be deleted.')
                            ->danger()
                            ->send();

                        $this->halt();
                    }

                    $hasHistory = LeadHistory::withoutGlobalScopes()
                        ->where('company_id', $this->record->company_id)
                        ->whereIn('event_type', [
                            LeadHistoryType::Disposition->value,
                            LeadHistoryType::Skip->value,
                        ])
                        ->where('payload->disposition', $this->record->slug)
                        ->exists();

                    if ($hasHistory) {
                        Notification::make()
                            ->title('Deactivate this disposition instead — it appears in lead history.')
                            ->danger()
                            ->send();

                        $this->halt();
                    }
                }),
        ];
    }
}
