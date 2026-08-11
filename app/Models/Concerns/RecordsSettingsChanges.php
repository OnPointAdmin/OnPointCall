<?php

namespace App\Models\Concerns;

use App\Services\Settings\SettingsHistoryService;
use Illuminate\Database\Eloquent\Model;

trait RecordsSettingsChanges
{
    public static function bootRecordsSettingsChanges(): void
    {
        static::created(function (Model $model): void {
            if (! auth()->check()) {
                return;
            }

            app(SettingsHistoryService::class)->record(
                $model,
                [],
                $model->getAttributes(),
            );
        });

        static::updated(function (Model $model): void {
            if (! auth()->check()) {
                return;
            }

            app(SettingsHistoryService::class)->record(
                $model,
                $model->getOriginal(),
                $model->getAttributes(),
            );
        });
    }
}
