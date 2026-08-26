<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class EditProfile extends BaseEditProfile
{
    protected static bool $shouldRegisterNavigation = false;

    public bool $wasForcedPasswordChange = false;

    public function mount(): void
    {
        $this->wasForcedPasswordChange = $this->userMustChangePassword();

        parent::mount();
    }

    public function getHeading(): string|Htmlable|null
    {
        if ($this->isForcedPasswordChange()) {
            return 'Choose a new password';
        }

        return parent::getHeading();
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->isForcedPasswordChange()) {
            return 'For security, set your own password before you continue.';
        }

        return parent::getSubheading();
    }

    public static function getLabel(): string
    {
        return 'Change password';
    }

    public static function isSimple(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        $password = $this->getPasswordFormComponent()->required();
        $confirmation = $this->getPasswordConfirmationFormComponent()
            ->required()
            ->visible(true);

        if ($this->isForcedPasswordChange()) {
            return $schema->components([
                $password,
                $confirmation,
            ]);
        }

        return $schema->components([
            $this->getCurrentPasswordFormComponent()
                ->required()
                ->visible(true),
            $password,
            $confirmation,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['email_verified_at'], $data['password'], $data['remember_token']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $payload = [];

        if (filled($data['password'] ?? null)) {
            $payload['password'] = $data['password'];
            $payload['must_change_password'] = false;

            $user = $this->getUser();
            if ($this->isForcedPasswordChange() && $user instanceof User && $user->email_verified_at === null) {
                $payload['email_verified_at'] = now();
            }
        }

        return $payload;
    }

    protected function getRedirectUrl(): ?string
    {
        return Filament::getUrl();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        if ($this->isForcedPasswordChange()) {
            return [
                $this->getSaveFormAction()->label('Save password and continue'),
                Action::make('logout')
                    ->label('Sign out')
                    ->color('gray')
                    ->url(Filament::getLogoutUrl())
                    ->postToUrl(),
            ];
        }

        return parent::getFormActions();
    }

    private function isForcedPasswordChange(): bool
    {
        return $this->wasForcedPasswordChange || $this->userMustChangePassword();
    }

    private function userMustChangePassword(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->mustChangePassword();
    }
}
