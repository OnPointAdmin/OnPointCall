<?php

namespace App\Providers;

use App\Http\Controllers\Admin\FilamentLoginResponse;
use App\Http\Controllers\Admin\FilamentLogoutController;
use App\Services\Leads\DialableInventoryService;
use App\Support\CompanyTimezone;
use Filament\Auth\Http\Controllers\LogoutController as FilamentVendorLogoutController;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as FilamentLoginResponseContract;
use Filament\Support\Facades\FilamentTimezone;
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
        $this->app->bind(FilamentLoginResponseContract::class, FilamentLoginResponse::class);
        $this->app->singleton(DialableInventoryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentTimezone::set(fn (): string => CompanyTimezone::forAuthenticated());

        $this->ignoreDockerBladeUtimeFailures();
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

    /**
     * Docker Desktop bind mounts can reject Blade's compiled-view timestamp bump.
     */
    private function ignoreDockerBladeUtimeFailures(): void
    {
        $previous = set_error_handler(function (
            int $errno,
            string $errstr,
            string $errfile,
            int $errline,
        ) use (&$previous): bool {
            if (str_contains($errstr, 'Utime failed') && str_contains($errfile, 'BladeCompiler.php')) {
                return true;
            }

            if ($previous !== null) {
                return (bool) $previous($errno, $errstr, $errfile, $errline);
            }

            return false;
        });
    }
}
