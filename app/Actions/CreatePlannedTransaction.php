<?php

namespace App\Actions;

use App\Enums\CounterpartyKind;
use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Events\PlannedTransactionCreated;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePlannedTransaction
{
    /**
     * @return array<int, PlannedTransaction> Single row for normal txns; linked pair for internal-counterparty transfers.
     */
    public function execute(
        Entity $owner,
        Counterparty $counterparty,
        PlannedTransactionDirection $direction,
        float $amount,
        Currency $currency,
        ?string $dueDate = null,
        ?string $purpose = null,
        PlannedTransactionStatus $status = PlannedTransactionStatus::PLANNED,
        bool $isMandatory = true,
        ?string $note = null,
    ): array {
        $rows = DB::transaction(function () use (
            $owner,
            $counterparty,
            $direction,
            $amount,
            $currency,
            $dueDate,
            $purpose,
            $status,
            $isMandatory,
            $note,
        ) {
            $isInternalTransfer = $counterparty->kind === CounterpartyKind::INTERNAL
                && $counterparty->entity_id !== null
                && $counterparty->entity_id !== $owner->id;

            $transferGroupId = $isInternalTransfer ? (string) Str::uuid() : null;

            $primary = PlannedTransaction::create([
                'owner_entity_id' => $owner->id,
                'counterparty_id' => $counterparty->id,
                'direction' => $direction,
                'amount' => $amount,
                'currency' => $currency,
                'due_date' => $dueDate,
                'purpose' => $purpose,
                'status' => $status,
                'is_mandatory' => $isMandatory,
                'note' => $note,
                'transfer_group_id' => $transferGroupId,
            ]);

            if (! $isInternalTransfer) {
                return [$primary];
            }

            $otherEntity = $counterparty->entity;
            $ownerMirror = $this->resolveInternalMirror($owner);

            $mirror = PlannedTransaction::create([
                'owner_entity_id' => $otherEntity->id,
                'counterparty_id' => $ownerMirror->id,
                'direction' => $this->flip($direction),
                'amount' => $amount,
                'currency' => $currency,
                'due_date' => $dueDate,
                'purpose' => $purpose,
                'status' => $status,
                'is_mandatory' => $isMandatory,
                'note' => $note,
                'transfer_group_id' => $transferGroupId,
            ]);

            return [$primary, $mirror];
        });

        PlannedTransactionCreated::dispatch($rows);

        return $rows;
    }

    private function resolveInternalMirror(Entity $entity): Counterparty
    {
        return Counterparty::firstOrCreate(
            ['entity_id' => $entity->id],
            [
                'user_id' => $entity->user_id,
                'name' => $entity->name,
                'kind' => CounterpartyKind::INTERNAL,
            ],
        );
    }

    private function flip(PlannedTransactionDirection $direction): PlannedTransactionDirection
    {
        return $direction === PlannedTransactionDirection::OUTGOING
            ? PlannedTransactionDirection::INCOMING
            : PlannedTransactionDirection::OUTGOING;
    }
}
