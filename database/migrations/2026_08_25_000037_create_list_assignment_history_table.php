<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calling_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('action');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['calling_list_id', 'occurred_at']);
        });

        $now = now();

        foreach (DB::table('list_assignments')->orderBy('id')->get() as $row) {
            $userName = DB::table('users')->where('id', $row->user_id)->value('name') ?? 'Unknown';

            DB::table('list_assignment_history')->insert([
                'company_id' => $row->company_id,
                'calling_list_id' => $row->calling_list_id,
                'user_id' => $row->user_id,
                'user_name' => $userName,
                'action' => 'assigned',
                'actor_id' => null,
                'occurred_at' => $row->created_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('list_assignment_history');
    }
};
