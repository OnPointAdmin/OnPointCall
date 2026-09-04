<?php

use App\Models\ImportMapping;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ImportMapping::withoutGlobalScopes()->orderBy('id')->each(function (ImportMapping $mapping): void {
            $columnMap = $mapping->column_map ?? [];

            if (! is_array($columnMap)) {
                return;
            }

            if (($columnMap['phone'] ?? null) !== 'Phone_2') {
                return;
            }

            $columnMap['phone'] = 'caller_id';
            $columnMap['phone_2'] = 'Phone_2';
            $mapping->update(['column_map' => $columnMap]);
        });
    }

    public function down(): void
    {
        ImportMapping::withoutGlobalScopes()->orderBy('id')->each(function (ImportMapping $mapping): void {
            $columnMap = $mapping->column_map ?? [];

            if (! is_array($columnMap)) {
                return;
            }

            if (($columnMap['phone'] ?? null) !== 'caller_id'
                || ($columnMap['phone_2'] ?? null) !== 'Phone_2'
                || ($mapping->lead_type ?? null) !== 'tnb') {
                return;
            }

            $columnMap['phone'] = 'Phone_2';
            $columnMap['phone_2'] = 'caller_id';
            $mapping->update(['column_map' => $columnMap]);
        });
    }
};
