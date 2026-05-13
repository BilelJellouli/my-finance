<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('color');
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX entities_one_personal_per_user ON entities (user_id) WHERE type = \'personal\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
