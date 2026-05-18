<?php

namespace App\Actions;

use App\Enums\PlannedTransactionStatus;
use App\Events\TransactionCreated;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTransaction
{
    /**
     * Record a real transaction against a planned one. Enforces:
     *  - amount > 0
     *  - sum of existing transactions + new amount <= planned amount
     *  - planned status is not cancelled
     *
     * When the planned row is part of a transfer pair, a mirror transaction is
     * created on the sibling planned row in the same DB transaction so both
     * sides stay in lockstep. When the cumulative total reaches the planned
     * amount the planned status auto-flips to settled (on both sides).
     *
     * @return array<int, Transaction> Single row for normal txns; linked pair for transfer-group plans.
     */
    public function execute(
        PlannedTransaction $plannedTransaction,
        float $amount,
        string $occurredOn,
        ?string $note = null,
    ): array {
        if ($plannedTransaction->status === PlannedTransactionStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'amount' => __('Cannot record a transaction on a cancelled planned transaction.'),
            ]);
        }

        $rows = DB::transaction(function () use ($plannedTransaction, $amount, $occurredOn, $note) {
            $primary = $this->recordOn($plannedTransaction, $amount, $occurredOn, $note);

            if ($plannedTransaction->transfer_group_id === null) {
                return [$primary];
            }

            $created = [$primary];

            $siblings = PlannedTransaction::query()
                ->where('transfer_group_id', $plannedTransaction->transfer_group_id)
                ->where('id', '!=', $plannedTransaction->id)
                ->lockForUpdate()
                ->get();

            foreach ($siblings as $sibling) {
                $created[] = $this->recordOn($sibling, $amount, $occurredOn, $note);
            }

            return $created;
        });

        TransactionCreated::dispatch($rows);

        return $rows;
    }

    private function recordOn(
        PlannedTransaction $plannedTransaction,
        float $amount,
        string $occurredOn,
        ?string $note,
    ): Transaction {
        $plannedAmount = (float) $plannedTransaction->amount;
        $alreadySettled = (float) $plannedTransaction->transactions()->sum('amount');
        $remaining = round($plannedAmount - $alreadySettled, 2);

        if ($amount > $remaining + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => __('Amount :amount exceeds the remaining :remaining on this planned transaction.', [
                    'amount' => number_format($amount, 2),
                    'remaining' => number_format(max($remaining, 0), 2),
                ]),
            ]);
        }

        $transaction = Transaction::create([
            'planned_transaction_id' => $plannedTransaction->id,
            'amount' => $amount,
            'occurred_on' => $occurredOn,
            'note' => $note,
        ]);

        $newTotal = round($alreadySettled + $amount, 2);

        if ($newTotal >= $plannedAmount && $plannedTransaction->status !== PlannedTransactionStatus::SETTLED) {
            $plannedTransaction->update(['status' => PlannedTransactionStatus::SETTLED]);
        }

        return $transaction;
    }
}
