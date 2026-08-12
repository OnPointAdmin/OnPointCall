<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Users\UserInviteService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resendInvite')
                ->label('Resend invite')
                ->icon(Heroicon::OutlinedEnvelope)
                ->requiresConfirmation()
                ->modalHeading('Resend invite email?')
                ->modalDescription('This resets their password and emails a new temporary password.')
                ->action(function (UserInviteService $invites): void {
                    /** @var User $user */
                    $user = $this->getRecord();
                    $invites->resend($user);

                    Notification::make()
                        ->title("Invite resent to {$user->email}")
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
