<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\SettingsHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_update_records_history(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        $setting = AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 3,
            'claim_ttl_minutes' => 20,
        ]);

        $setting->update(['max_attempts' => 5]);

        $this->assertDatabaseHas('settings_history', [
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        $history = SettingsHistory::withoutGlobalScopes()
            ->whereNotNull('before_value')
            ->get()
            ->first(fn ($row) => isset($row->before_value['max_attempts']));

        $this->assertNotNull($history);
        $this->assertSame(3, $history->before_value['max_attempts']);
        $this->assertSame(5, $history->after_value['max_attempts']);
    }
}
