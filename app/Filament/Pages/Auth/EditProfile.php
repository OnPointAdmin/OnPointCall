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
        if ($this->userMustChangePassword()) {
            return 'Choose a new password';
        }

        return parent::getHeading();
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->userMustChangePassword()) {
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
        $user = Filament::auth()->user();

        return $user instanceof User && $user->mustChangePassword();
    }

    public function form(Schema $schema): Schema
    {
        $password = $this->getPasswordFormComponent()->required();
        $confirmation = $this->getPasswordConfirmationFormComponent()
            ->required()
            ->visible(true);

        if ($this->userMustChangePassword()) {
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
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['password'] ?? null)) {
            $data['must_change_password'] = false;
        }

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->wasForcedPasswordChange ? Filament::getUrl() : null;
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        if ($this->userMustChangePassword()) {
            return [
                $this->getSaveFormAction(),
                Action::make('logout')
                    ->label('Sign out')
                    ->color('gray')
                    ->url(Filament::getLogoutUrl())
                    ->postToUrl(),
            ];
        }

        return parent::getFormActions();
    }

    private function userMustChangePassword(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->mustChangePassword();
    }
}
