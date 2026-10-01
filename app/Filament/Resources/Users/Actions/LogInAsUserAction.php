<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use App\Services\Auth\Impersonation;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class LogInAsUserAction
{
    public static function isVisible(User $actor, User $target): bool
    {
        return $actor->isAdmin()
            && $actor->active
            && $target->active
            && $actor->id !== $target->id;
    }

    public static function configure(Action $action): Action
    {
        return $action
            ->label('Log in as')
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->requiresConfirmation()
            ->modalHeading('Log in as this user?')
            ->modalDescription('You will see the agent workspace as this user. Actions you take are saved as them.');
    }

    public static function run(User $actor, User $target, Impersonation $impersonation): void
    {
        $impersonation->start($actor, $target);
    }
}
