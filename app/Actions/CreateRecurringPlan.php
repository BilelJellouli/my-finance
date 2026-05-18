<?php

namespace App\Actions;

use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringPlanStatus;
use App\Events\RecurringPlanCreated;
use App\Models\Account;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPhase;
use Illuminate\Support\Facades\DB;

class CreateRecurringPlan
{
    public function __construct(private MaterializeRecurringPlan $materializeRecurringPlan) {}

    public function execute(
        Entity $owner,
        Counterparty $counterparty,
        ?Account $account,
        PlannedTransactionDirection $direction,
        Currency $currency,
        string $label,
        ?string $purpose,
        bool $isMandatory,
        string $startsOn,
        ?string $endsOn,
        ?string $note,
        float $amount,
        RecurringFrequency $frequency,
        int $intervalStep,
        ?int $anchorDay,
    ): RecurringPlan {
        [$plan, $phase] = DB::transaction(function () use (
            $owner,
            $counterparty,
            $account,
            $direction,
            $currency,
            $label,
            $purpose,
            $isMandatory,
            $startsOn,
            $endsOn,
            $note,
            $amount,
            $frequency,
            $intervalStep,
            $anchorDay,
        ) {
            $plan = RecurringPlan::create([
                'owner_entity_id' => $owner->id,
                'counterparty_id' => $counterparty->id,
                'account_id' => $account?->id,
                'direction' => $direction,
                'currency' => $currency,
                'label' => $label,
                'purpose' => $purpose,
                'is_mandatory' => $isMandatory,
                'status' => RecurringPlanStatus::ACTIVE,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'note' => $note,
            ]);

            $phase = RecurringPlanPhase::create([
                'recurring_plan_id' => $plan->id,
                'amount' => $amount,
                'frequency' => $frequency,
                'interval_step' => $intervalStep,
                'anchor_day' => $anchorDay,
                'starts_on' => $startsOn,
                'ends_on' => null,
                'occurrence_count' => null,
                'reason' => null,
            ]);

            return [$plan, $phase];
        });

        $this->materializeRecurringPlan->execute($plan->fresh(['phases']));

        RecurringPlanCreated::dispatch($plan, $phase);

        return $plan;
    }
}
