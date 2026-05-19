<?php

namespace App\Actions;

use App\Enums\Currency;
use App\Enums\TransactionKind;
use App\Events\TransactionUpdated;
use App\Models\Account;
use App\Models\Counterparty;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTransaction
{
    public function __construct(private CreateTransaction $createTransaction) {}

    /**
     * Update a transaction's mutable fields. The planned-transaction link is immutable —
     * unlink-then-relink should be done via delete + create.
     */
    public function execute(
        User $user,
        Transaction $transaction,
        float $amount,
        string $occurredOn,
        TransactionKind $kind,
        Currency $currency,
        ?Account $fromAccount,
        ?Account $toAccount,
        ?Counterparty $counterparty,
        ?string $note,
    ): Transaction {
        $plannedTransaction = $transaction->plannedTransaction;

        $this->createTransaction->validateMovement(
            user: $user,
            amount: $amount,
            currency: $currency,
            fromAccount: $fromAccount,
            toAccount: $toAccount,
            counterparty: $counterparty,
            plannedTransaction: $plannedTransaction,
        );

        if ($plannedTransaction !== null) {
            $this->createTransaction->validatePlannedCap(
                plannedTransaction: $plannedTransaction,
                amount: $amount,
                excludeTransactionId: $transaction->id,
            );
        }

        $this->createTransaction->ensureFromAccountCovers(
            fromAccount: $fromAccount,
            amount: $amount,
            excludeTransactionId: $transaction->id,
        );

        if (! $this->ownsTransaction($user, $transaction)) {
            throw ValidationException::withMessages([
                'id' => __('You do not own this transaction.'),
            ]);
        }

        return DB::transaction(function () use (
            $transaction,
            $amount,
            $occurredOn,
            $kind,
            $currency,
            $fromAccount,
            $toAccount,
            $counterparty,
            $note,
            $plannedTransaction,
        ) {
            $transaction->update([
                'from_account_id' => $fromAccount?->id,
                'to_account_id' => $toAccount?->id,
                'counterparty_id' => $counterparty?->id,
                'amount' => $amount,
                'currency' => $currency,
                'kind' => $kind,
                'occurred_on' => $occurredOn,
                'note' => $note,
            ]);

            if ($plannedTransaction !== null) {
                $this->createTransaction->syncPlannedStatusAfterChange($plannedTransaction->fresh());
            }

            TransactionUpdated::dispatch($transaction->fresh());

            return $transaction->fresh();
        });
    }

    private function ownsTransaction(User $user, Transaction $transaction): bool
    {
        if ($transaction->plannedTransaction?->ownerEntity?->user_id === $user->id) {
            return true;
        }
        if ($transaction->fromAccount?->entity?->user_id === $user->id) {
            return true;
        }
        if ($transaction->toAccount?->entity?->user_id === $user->id) {
            return true;
        }

        return false;
    }
}
