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
            $table->foreignId('planned_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('counterparty_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('kind');
            $table->date('occurred_on');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['planned_transaction_id', 'occurred_on']);
            $table->index(['from_account_id', 'occurred_on']);
            $table->index(['to_account_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
