<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const EXTRA_FIELD_BACKFILL_KEYS = [
        'age_range',
        'annual_income',
        'marital_status',
        'gender',
        'home_owner',
        'original_lead_submit_date',
    ];

    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('age_range')->nullable()->after('email');
            $table->string('annual_income')->nullable()->after('age_range');
            $table->string('marital_status')->nullable()->after('annual_income');
            $table->string('gender')->nullable()->after('marital_status');
            $table->string('home_owner')->nullable()->after('gender');
            $table->string('original_lead_submit_date')->nullable()->after('home_owner');
            $table->string('booking_id')->nullable()->after('external_lead_id');
            $table->string('phone_2', 10)->nullable()->after('phone');
            $table->string('address_2')->nullable()->after('address');
            $table->string('tour_location')->nullable()->after('event');
            $table->string('tour_date')->nullable()->after('tour_location');
            $table->string('premiums')->nullable()->after('tour_date');
            $table->string('tour_result')->nullable()->after('premiums');
            $table->string('tour_or_no_show')->nullable()->after('tour_result');
        });

        $this->backfillFromExtraFields();
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'age_range',
                'annual_income',
                'marital_status',
                'gender',
                'home_owner',
                'original_lead_submit_date',
                'booking_id',
                'phone_2',
                'address_2',
                'tour_location',
                'tour_date',
                'premiums',
                'tour_result',
                'tour_or_no_show',
            ]);
        });
    }

    private function backfillFromExtraFields(): void
    {
        DB::table('leads')
            ->whereNotNull('extra_fields')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $row): void {
                $extra = json_decode($row->extra_fields, true);

                if (! is_array($extra)) {
                    return;
                }

                $updates = [];
                $remaining = $extra;

                foreach (self::EXTRA_FIELD_BACKFILL_KEYS as $key) {
                    if (! array_key_exists($key, $extra)) {
                        continue;
                    }

                    $value = $extra[$key];
                    $updates[$key] = is_scalar($value) ? (string) $value : json_encode($value);
                    unset($remaining[$key]);
                }

                if ($updates === []) {
                    return;
                }

                $updates['extra_fields'] = $remaining === [] ? null : json_encode($remaining);

                DB::table('leads')->where('id', $row->id)->update($updates);
            });
    }
};
