<?php

namespace App\Actions;

use App\Enums\RecurringPlanStatus;
use App\Events\RecurringPlanResumed;
use App\Models\RecurringPlan;
use Illuminate\Support\Facades\DB;

class ResumeRecurringPlan
{
    public function __construct(private MaterializeRecurringPlan $materializeRecurringPlan) {}

    public function execute(RecurringPlan $plan): RecurringPlan
    {
        DB::transaction(function () use ($plan) {
            $plan->update([
                'status' => RecurringPlanStatus::ACTIVE,
                'materialized_until' => null,
            ]);
        });

        $this->materializeRecurringPlan->execute($plan->fresh(['phases', 'ownerEntity', 'counterparty']));

        RecurringPlanResumed::dispatch($plan->fresh());

        return $plan;
    }
}
