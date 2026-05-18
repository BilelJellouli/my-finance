<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_plan_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('frequency');
            $table->unsignedInteger('interval_step')->default(1);
            $table->unsignedSmallInteger('anchor_day')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('occurrence_count')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['recurring_plan_id', 'starts_on']);
            $table->index(['recurring_plan_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_plan_phases');
    }
};
