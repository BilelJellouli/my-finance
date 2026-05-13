<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $userIds = DB::table('users')
            ->leftJoin('entities', function ($join) {
                $join->on('entities.user_id', '=', 'users.id')
                    ->where('entities.type', 'personal');
            })
            ->whereNull('entities.id')
            ->pluck('users.id');

        foreach ($userIds as $userId) {
            DB::table('entities')->insert([
                'user_id' => $userId,
                'name' => 'Personal',
                'type' => 'personal',
                'color' => 'green',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible.
    }
};
