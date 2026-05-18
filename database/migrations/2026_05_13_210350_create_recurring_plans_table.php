<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('counterparty_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction');
            $table->string('currency', 3);
            $table->string('label');
            $table->string('purpose')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->string('status')->default('active');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('materialized_until')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_entity_id', 'status']);
            $table->index(['status', 'materialized_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_plans');
    }
};
