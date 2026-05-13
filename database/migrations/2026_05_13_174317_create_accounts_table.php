<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('currency', 3);
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('is_main')->default(false);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX accounts_one_main_per_entity ON accounts (entity_id) WHERE is_main = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
