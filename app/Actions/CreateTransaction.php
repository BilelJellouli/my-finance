<?php

namespace App\Actions;

use App\Enums\CounterpartyKind;
use App\Enums\Currency;
use App\Enums\PlannedTransactionStatus;
use App\Enums\TransactionKind;
use App\Events\TransactionCreated;
use App\Models\Account;
use App\Models\Counterparty;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTransaction
{
    /**
     * Record a transaction. Supports three shapes:
     *  - Standalone outgoing (fromAccount set, toAccount null), optional external counterparty.
     *  - Standalone incoming (toAccount set, fromAccount null), optional external counterparty.
     *  - Transfer (both accounts set) — same currency, distinct accounts.
     *
     * Optionally linked to a planned transaction. If the planned row is part of a transfer-group
     * pair, settling it fully also flips the sibling planned row to SETTLED — but no extra
     * Transaction row is created (one Transaction = one money movement).
     */
    public function execute(
        User $user,
        float $amount,
        string $occurredOn,
        TransactionKind $kind,
        Currency $currency,
        ?Account $fromAccount = null,
        ?Account $toAccount = null,
        ?Counterparty $counterparty = null,
        ?PlannedTransaction $plannedTransaction = null,
        ?string $note = null,
    ): Transaction {
        $this->validateMovement(
            user: $user,
            amount: $amount,
            currency: $currency,
            fromAccount: $fromAccount,
            toAccount: $toAccount,
            counterparty: $counterparty,
            plannedTransaction: $plannedTransaction,
        );

        if ($plannedTransaction !== null) {
            $this->validatePlannedCap($plannedTransaction, $amount, excludeTransactionId: null);
        }

        $this->ensureFromAccountCovers($fromAccount, $amount, excludeTransactionId: null);

        $transaction = DB::transaction(function () use (
            $amount,
            $occurredOn,
            $kind,
            $currency,
            $fromAccount,
            $toAccount,
            $counterparty,
            $plannedTransaction,
            $note,
        ) {
            $row = Transaction::create([
                'planned_transaction_id' => $plannedTransaction?->id,
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
                $this->syncPlannedStatusAfterChange($plannedTransaction);
            }

            return $row;
        });

        TransactionCreated::dispatch($transaction);

        return $transaction;
    }

    /**
     * Throws ValidationException if the movement is malformed or not owned by $user.
     */
    public function validateMovement(
        User $user,
        float $amount,
        Currency $currency,
        ?Account $fromAccount,
        ?Account $toAccount,
        ?Counterparty $counterparty,
        ?PlannedTransaction $plannedTransaction,
    ): void {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Amount must be greater than zero.'),
            ]);
        }

        if ($fromAccount === null && $toAccount === null && $plannedTransaction === null) {
            throw ValidationException::withMessages([
                'from_account_id' => __('A transaction must touch at least one account.'),
            ]);
        }

        if ($fromAccount !== null && $toAccount !== null) {
            if ($fromAccount->id === $toAccount->id) {
                throw ValidationException::withMessages([
                    'to_account_id' => __('From and to accounts must be different.'),
                ]);
            }
            if ($fromAccount->currency !== $toAccount->currency) {
                throw ValidationException::withMessages([
                    'currency' => __('Cross-currency transfers are not supported yet.'),
                ]);
            }
        }

        foreach ([$fromAccount, $toAccount] as $account) {
            if ($account !== null && $account->currency !== $currency) {
                throw ValidationException::withMessages([
                    'currency' => __('Transaction currency must match the account currency.'),
                ]);
            }
            if ($account !== null && $account->entity->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'from_account_id' => __('You do not own this account.'),
                ]);
            }
        }

        if ($counterparty !== null) {
            if ($counterparty->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'counterparty_id' => __('You do not own this counterparty.'),
                ]);
            }
            if ($counterparty->kind !== CounterpartyKind::EXTERNAL) {
                throw ValidationException::withMessages([
                    'counterparty_id' => __('Only external counterparties can be attached to a transaction directly.'),
                ]);
            }
        }

        if ($plannedTransaction !== null) {
            if ($plannedTransaction->ownerEntity->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'planned_transaction_id' => __('You do not own this planned transaction.'),
                ]);
            }
            if ($plannedTransaction->currency !== $currency) {
                throw ValidationException::withMessages([
                    'currency' => __('Transaction currency must match the planned transaction currency.'),
                ]);
            }
            if ($plannedTransaction->status === PlannedTransactionStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'amount' => __('Cannot record a transaction on a cancelled planned transaction.'),
                ]);
            }
        }
    }

    /**
     * Reject the movement if the source account does not have enough balance to cover it.
     * Past-dated transactions are also checked against today's balance — adjust the opening
     * balance if you need to backfill history that would otherwise overdraw.
     */
    public function ensureFromAccountCovers(
        ?Account $fromAccount,
        float $amount,
        ?int $excludeTransactionId,
    ): void {
        if ($fromAccount === null) {
            return;
        }

        $balance = $fromAccount->currentBalance($excludeTransactionId);

        if ($balance + 0.0001 < $amount) {
            throw ValidationException::withMessages([
                'amount' => __('Account :name has only :balance available.', [
                    'name' => $fromAccount->name,
                    'balance' => number_format(max($balance, 0), 2),
                ]),
            ]);
        }
    }

    /**
     * Caps cumulative settlement against the planned amount.
     */
    public function validatePlannedCap(
        PlannedTransaction $plannedTransaction,
        float $amount,
        ?int $excludeTransactionId,
    ): void {
        $plannedAmount = (float) $plannedTransaction->amount;
        $existing = $plannedTransaction->transactions()
            ->when($excludeTransactionId !== null, fn ($q) => $q->where('id', '!=', $excludeTransactionId))
            ->sum('amount');
        $remaining = round($plannedAmount - (float) $existing, 2);

        if ($amount > $remaining + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => __('Amount :amount exceeds the remaining :remaining on this planned transaction.', [
                    'amount' => number_format($amount, 2),
                    'remaining' => number_format(max($remaining, 0), 2),
                ]),
            ]);
        }
    }

    /**
     * Re-evaluates the planned row's status against the current cumulative total of its
     * transactions and syncs both sides if it's part of a transfer pair.
     */
    public function syncPlannedStatusAfterChange(PlannedTransaction $plannedTransaction): void
    {
        $plannedAmount = (float) $plannedTransaction->amount;
        $newTotal = (float) $plannedTransaction->transactions()->sum('amount');
        $shouldBeSettled = round($newTotal, 2) >= round($plannedAmount, 2);

        $targetStatus = $shouldBeSettled
            ? PlannedTransactionStatus::SETTLED
            : $this->pendingStatusFor($plannedTransaction);

        if ($plannedTransaction->status !== $targetStatus) {
            $plannedTransaction->update(['status' => $targetStatus]);
        }

        if ($plannedTransaction->transfer_group_id === null) {
            return;
        }

        $siblings = PlannedTransaction::query()
            ->where('transfer_group_id', $plannedTransaction->transfer_group_id)
            ->where('id', '!=', $plannedTransaction->id)
            ->lockForUpdate()
            ->get();

        foreach ($siblings as $sibling) {
            if ($sibling->status === PlannedTransactionStatus::CANCELLED) {
                continue;
            }
            if ($sibling->status !== $targetStatus) {
                $sibling->update(['status' => $targetStatus]);
            }
        }
    }

    private function pendingStatusFor(PlannedTransaction $plannedTransaction): PlannedTransactionStatus
    {
        if ($plannedTransaction->status === PlannedTransactionStatus::CANCELLED) {
            return PlannedTransactionStatus::CANCELLED;
        }

        $dueDate = $plannedTransaction->due_date;
        if ($dueDate !== null && $dueDate->isPast()) {
            return PlannedTransactionStatus::OVERDUE;
        }

        return PlannedTransactionStatus::PLANNED;
    }
}
