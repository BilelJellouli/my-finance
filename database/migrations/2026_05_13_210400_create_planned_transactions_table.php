<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('counterparty_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->date('due_date')->nullable();
            $table->string('purpose')->nullable();
            $table->string('status')->default('planned');
            $table->boolean('is_mandatory')->default(true);
            $table->text('note')->nullable();
            $table->uuid('transfer_group_id')->nullable();
            $table->text('deletion_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_entity_id', 'due_date']);
            $table->index(['owner_entity_id', 'status']);
            $table->index(['owner_entity_id', 'is_mandatory']);
            $table->index('transfer_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_transactions');
    }
};
