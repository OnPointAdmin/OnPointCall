<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Actions\LogInAsUserAction;
use App\Models\User;
use App\Services\Auth\Impersonation;
use App\Services\Users\UserInviteService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('role')
                    ->badge()
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('google_id')
                    ->searchable(),
                TextColumn::make('microsoft_id')
                    ->searchable(),
                TextColumn::make('salesforce_id')
                    ->label('Salesforce ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                LogInAsUserAction::configure(
                    Action::make('logInAs')
                        ->visible(function (User $record): bool {
                            $actor = auth()->user();

                            return $actor instanceof User
                                && LogInAsUserAction::isVisible($actor, $record);
                        })
                        ->action(function (User $record, Impersonation $impersonation) {
                            /** @var User $actor */
                            $actor = auth()->user();
                            LogInAsUserAction::run($actor, $record, $impersonation);

                            return redirect()->route('agent.workspace');
                        })
                ),
                Action::make('resendInvite')
                    ->label('Resend invite')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->requiresConfirmation()
                    ->modalHeading('Resend invite email?')
                    ->modalDescription('This resets their password and emails a new temporary password.')
                    ->action(function (User $record, UserInviteService $invites): void {
                        $invites->resend($record);

                        Notification::make()
                            ->title("Invite resent to {$record->email}")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
