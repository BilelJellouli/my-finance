<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->foreignId('entity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'kind']);
        });

        DB::statement('CREATE UNIQUE INDEX counterparties_one_internal_per_entity ON counterparties (entity_id) WHERE entity_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparties');
    }
};
