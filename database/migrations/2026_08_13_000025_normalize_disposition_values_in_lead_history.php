<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize renamed disposition values in lead history payloads.
     * voicemail → left_vm, bad_lead → bad_number (spreadsheet labels).
     */
    public function up(): void
    {
        $map = [
            'voicemail' => 'left_vm',
            'bad_lead' => 'bad_number',
        ];

        foreach ($map as $from => $to) {
            DB::table('lead_history')
                ->where('payload->disposition', $from)
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($from, $to): void {
                    foreach ($rows as $row) {
                        $payload = is_string($row->payload)
                            ? (json_decode($row->payload, true) ?? [])
                            : (array) $row->payload;

                        if (($payload['disposition'] ?? null) !== $from) {
                            continue;
                        }

                        $payload['disposition'] = $to;

                        DB::table('lead_history')->where('id', $row->id)->update([
                            'payload' => json_encode($payload),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        $map = [
            'left_vm' => 'voicemail',
            'bad_number' => 'bad_lead',
        ];

        foreach ($map as $from => $to) {
            DB::table('lead_history')
                ->where('payload->disposition', $from)
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($from, $to): void {
                    foreach ($rows as $row) {
                        $payload = is_string($row->payload)
                            ? (json_decode($row->payload, true) ?? [])
                            : (array) $row->payload;

                        if (($payload['disposition'] ?? null) !== $from) {
                            continue;
                        }

                        $payload['disposition'] = $to;

                        DB::table('lead_history')->where('id', $row->id)->update([
                            'payload' => json_encode($payload),
                        ]);
                    }
                });
        }
    }
};
