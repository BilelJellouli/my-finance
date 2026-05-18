<?php

namespace App\Actions;

use App\Enums\CounterpartyKind;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Enums\RecurringPlanStatus;
use App\Events\RecurringPlanMaterialized;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPhase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaterializeRecurringPlan
{
    public function execute(RecurringPlan $plan, ?CarbonImmutable $horizon = null): int
    {
        $horizon ??= CarbonImmutable::today()->addDays((int) config('recurring.horizon_days'));

        if ($plan->status !== RecurringPlanStatus::ACTIVE) {
            return 0;
        }

        // Never shrink the horizon — if the plan was previously materialized further out,
        // we still need to (re-)cover up to that point.
        if ($plan->materialized_until) {
            $previous = CarbonImmutable::parse($plan->materialized_until->toDateString());

            if ($previous->gt($horizon)) {
                $horizon = $previous;
            }
        }

        $planEnd = $plan->ends_on
            ? CarbonImmutable::parse($plan->ends_on->toDateString())
            : null;

        $effectiveHorizon = $planEnd && $planEnd->lt($horizon) ? $planEnd : $horizon;

        $createdCount = DB::transaction(function () use ($plan, $effectiveHorizon) {
            $count = 0;

            $phases = $plan->phases()->orderBy('starts_on')->get();

            foreach ($phases as $phase) {
                $count += $this->materializePhase($plan, $phase, $effectiveHorizon);
            }

            $plan->update(['materialized_until' => $effectiveHorizon->toDateString()]);

            return $count;
        });

        RecurringPlanMaterialized::dispatch($plan, $createdCount, $effectiveHorizon->toDateString());

        return $createdCount;
    }

    private function materializePhase(RecurringPlan $plan, RecurringPlanPhase $phase, CarbonImmutable $horizon): int
    {
        $phaseStart = CarbonImmutable::parse($phase->starts_on->toDateString());
        $phaseEnd = $phase->ends_on ? CarbonImmutable::parse($phase->ends_on->toDateString()) : null;

        $windowEnd = $phaseEnd && $phaseEnd->lt($horizon) ? $phaseEnd : $horizon;

        if ($phaseStart->gt($windowEnd)) {
            return 0;
        }

        $created = 0;
        $occurrence = 0;
        $current = $phaseStart;

        while ($current->lte($windowEnd)) {
            if ($phase->occurrence_count !== null && $occurrence >= $phase->occurrence_count) {
                break;
            }

            if ($this->createOccurrenceIfMissing($plan, $phase, $current)) {
                $created++;
            }

            $occurrence++;
            $current = $phase->advance($current);
        }

        return $created;
    }

    private function createOccurrenceIfMissing(RecurringPlan $plan, RecurringPlanPhase $phase, CarbonImmutable $dueDate): bool
    {
        $dueDateString = $dueDate->toDateString();

        $exists = PlannedTransaction::query()
            ->where('recurring_plan_id', $plan->id)
            ->whereDate('due_date', $dueDateString)
            ->exists();

        if ($exists) {
            return false;
        }

        $counterparty = $plan->counterparty;
        $owner = $plan->ownerEntity;

        $isInternalTransfer = $counterparty->kind === CounterpartyKind::INTERNAL
            && $counterparty->entity_id !== null
            && $counterparty->entity_id !== $owner->id;

        $transferGroupId = $isInternalTransfer ? (string) Str::uuid() : null;

        PlannedTransaction::create([
            'owner_entity_id' => $owner->id,
            'counterparty_id' => $counterparty->id,
            'account_id' => $plan->account_id,
            'recurring_plan_id' => $plan->id,
            'recurring_plan_phase_id' => $phase->id,
            'direction' => $plan->direction,
            'amount' => $phase->amount,
            'currency' => $plan->currency,
            'due_date' => $dueDateString,
            'purpose' => $plan->purpose,
            'status' => PlannedTransactionStatus::PLANNED,
            'is_mandatory' => $plan->is_mandatory,
            'note' => null,
            'transfer_group_id' => $transferGroupId,
        ]);

        if ($isInternalTransfer) {
            $otherEntity = $counterparty->entity;
            $ownerMirror = $this->resolveInternalMirror($owner);

            PlannedTransaction::create([
                'owner_entity_id' => $otherEntity->id,
                'counterparty_id' => $ownerMirror->id,
                'account_id' => null,
                'recurring_plan_id' => $plan->id,
                'recurring_plan_phase_id' => $phase->id,
                'direction' => $plan->direction->value === 'outgoing' ? PlannedTransactionDirection::INCOMING : PlannedTransactionDirection::OUTGOING,
                'amount' => $phase->amount,
                'currency' => $plan->currency,
                'due_date' => $dueDateString,
                'purpose' => $plan->purpose,
                'status' => PlannedTransactionStatus::PLANNED,
                'is_mandatory' => $plan->is_mandatory,
                'note' => null,
                'transfer_group_id' => $transferGroupId,
            ]);
        }

        return true;
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
}
