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

            if (array_key_exists('credit_card_type', $columnMap)) {
                return;
            }

            $columnMap['credit_card_type'] = 'Type_of_Credit_Card__c';
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

            if (($columnMap['credit_card_type'] ?? null) !== 'Type_of_Credit_Card__c') {
                return;
            }

            unset($columnMap['credit_card_type']);
            $mapping->update(['column_map' => $columnMap]);
        });
    }
};
