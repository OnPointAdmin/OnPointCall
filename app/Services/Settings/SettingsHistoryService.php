<?php

namespace App\Services\Settings;

use App\Models\SettingsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SettingsHistoryService
{
    public function record(Model $model, array $before, array $after, ?string $key = null): void
    {
        if ($before === $after) {
            return;
        }

        $companyId = $model->getAttribute('company_id');

        if (! $companyId) {
            return;
        }

        SettingsHistory::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'user_id' => Auth::id(),
            'setting_key' => $key ?? $model->getTable().':'.$model->getKey(),
            'before_value' => $before,
            'after_value' => $after,
            'changed_at' => now(),
        ]);
    }
}
