<?php

namespace App\Filament\Actions;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ReassignCallbackAction
{
    public static function make(): Action
    {
        return Action::make('reassign')
            ->label('Reassign')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (Lead $record): bool => $record->status === LeadStatus::Callback)
            ->form([
                Select::make('callback_owner_id')
                    ->label('New owner')
                    ->options(fn (): array => User::query()
                        ->where('active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Lead $record, array $data): void {
                $record->update([
                    'callback_owner_id' => $data['callback_owner_id'],
                ]);

                Notification::make()
                    ->title('Callback reassigned')
                    ->success()
                    ->send();
            });
    }
}
