<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planned_transaction_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('occurred_on');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['planned_transaction_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
