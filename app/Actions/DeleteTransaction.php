<?php

namespace App\Actions;

use App\Events\TransactionDeleted;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DeleteTransaction
{
    public function __construct(private CreateTransaction $createTransaction) {}

    public function execute(Transaction $transaction): void
    {
        $snapshot = $transaction->replicate();
        $snapshot->id = $transaction->id;
        $plannedTransaction = $transaction->plannedTransaction;

        DB::transaction(function () use ($transaction, $plannedTransaction) {
            $transaction->delete();

            if ($plannedTransaction !== null) {
                $this->createTransaction->syncPlannedStatusAfterChange($plannedTransaction->fresh());
            }
        });

        TransactionDeleted::dispatch($snapshot);
    }
}
