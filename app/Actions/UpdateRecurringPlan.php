<?php

namespace App\Actions;

use App\Events\RecurringPlanUpdated;
use App\Models\Account;
use App\Models\RecurringPlan;
use Illuminate\Support\Facades\DB;

class UpdateRecurringPlan
{
    public function execute(
        RecurringPlan $plan,
        string $label,
        ?Account $account,
        ?string $purpose,
        bool $isMandatory,
        ?string $endsOn,
        ?string $note,
    ): RecurringPlan {
        DB::transaction(function () use ($plan, $label, $account, $purpose, $isMandatory, $endsOn, $note) {
            $plan->update([
                'label' => $label,
                'account_id' => $account?->id,
                'purpose' => $purpose,
                'is_mandatory' => $isMandatory,
                'ends_on' => $endsOn,
                'note' => $note,
            ]);
        });

        RecurringPlanUpdated::dispatch($plan->fresh());

        return $plan;
    }
}
