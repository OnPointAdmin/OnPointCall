<?php

namespace App\Providers;

use App\Http\Controllers\Admin\FilamentLogoutController;
use Filament\Auth\Http\Controllers\LogoutController as FilamentVendorLogoutController;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Logout without wiping the agent guard session.
        $this->app->bind(FilamentVendorLogoutController::class, FilamentLogoutController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! in_array($event->command, ['migrate:fresh', 'migrate:refresh', 'db:wipe'], true)) {
                return;
            }

            if ($this->app->environment('testing') || config('database.allow_destructive')) {
                return;
            }

            throw new RuntimeException(
                "Refusing to run {$event->command} because it would wipe the database. Set ALLOW_DESTRUCTIVE_DB=true if you intend to destroy local data."
            );
        });
    }
}
