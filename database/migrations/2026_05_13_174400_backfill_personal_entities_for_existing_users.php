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
            $entityId = DB::table('entities')->insertGetId([
                'user_id' => $userId,
                'name' => 'Personal',
                'type' => 'personal',
                'color' => 'green',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('accounts')->insert([
                'entity_id' => $entityId,
                'name' => 'Main',
                'currency' => 'TND',
                'amount' => 0,
                'is_main' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $entityIdsWithoutAccounts = DB::table('entities')
            ->leftJoin('accounts', 'accounts.entity_id', '=', 'entities.id')
            ->whereNull('accounts.id')
            ->pluck('entities.id');

        foreach ($entityIdsWithoutAccounts as $entityId) {
            DB::table('accounts')->insert([
                'entity_id' => $entityId,
                'name' => 'Main',
                'currency' => 'TND',
                'amount' => 0,
                'is_main' => true,
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
