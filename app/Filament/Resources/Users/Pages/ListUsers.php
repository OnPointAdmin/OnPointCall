<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\CallingList;
use App\Models\Company;
use App\Services\Users\UserInviteService;
use App\Support\CompanyContext;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label('Invite user')
                ->icon(Heroicon::OutlinedEnvelope)
                ->form([
                    TextInput::make('name')
                        ->required(),
                    TextInput::make('email')
                        ->label('Email address')
                        ->email()
                        ->required(),
                    Select::make('role')
                        ->options(UserRole::class)
                        ->default(UserRole::Agent->value)
                        ->required(),
                    Select::make('calling_list_ids')
                        ->label('Calling lists')
                        ->multiple()
                        ->options(fn (): array => CallingList::query()
                            ->where('active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn (): array => CallingList::query()
                            ->where('name', 'Standard')
                            ->pluck('id')
                            ->all())
                        ->required(),
                ])
                ->action(function (array $data, UserInviteService $invites): void {
                    $companyId = CompanyContext::id() ?? auth()->user()?->company_id;
                    $company = Company::query()->find($companyId);

                    if (! $company) {
                        Notification::make()
                            ->title('No company context')
                            ->danger()
                            ->send();

                        return;
                    }

                    $invites->invite(
                        $company,
                        $data['name'],
                        $data['email'],
                        UserRole::coerce($data['role']),
                        $data['calling_list_ids'] ?? [],
                    );

                    Notification::make()
                        ->title("Invite sent to {$data['email']}")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
