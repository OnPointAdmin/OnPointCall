<?php

namespace App\Filament\Actions;

use App\Filament\Support\NextDayPartField;
use App\Models\Lead;
use App\Services\Leads\LeadDayPartService;
use App\Support\CadenceDefaults;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ChangeNextDayPartAction
{
    public static function make(): Action
    {
        return Action::make('changeNextDayPart')
            ->label('Change next day part')
            ->icon(Heroicon::OutlinedClock)
            ->fillForm(fn (Lead $record): array => [
                'next_day_part' => CadenceDefaults::formValue($record->next_day_part),
            ])
            ->form([
                NextDayPartField::make(),
            ])
            ->action(function (Lead $record, array $data): void {
                $changed = app(LeadDayPartService::class)->setNextDayPart(
                    $record,
                    $data['next_day_part'] ?? null,
                    Auth::user(),
                );

                Notification::make()
                    ->title($changed ? 'Next day part updated' : 'Next day part unchanged')
                    ->success()
                    ->send();
            });
    }

    public static function makeBulk(): BulkAction
    {
        return BulkAction::make('changeNextDayPart')
            ->label('Change next day part')
            ->icon(Heroicon::OutlinedClock)
            ->form([
                NextDayPartField::make(),
            ])
            ->action(function (Collection $records, array $data): void {
                $service = app(LeadDayPartService::class);
                $user = Auth::user();
                $count = 0;

                foreach ($records as $record) {
                    if ($service->setNextDayPart($record, $data['next_day_part'] ?? null, $user)) {
                        $count++;
                    }
                }

                Notification::make()
                    ->title("Updated next day part on {$count} lead(s)")
                    ->success()
                    ->send();
            });
    }
}
