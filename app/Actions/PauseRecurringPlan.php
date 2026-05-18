<?php

namespace App\Actions;

use App\Enums\PlannedTransactionStatus;
use App\Enums\RecurringPlanStatus;
use App\Events\RecurringPlanPaused;
use App\Models\PlannedTransaction;
use App\Models\RecurringPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PauseRecurringPlan
{
    public function execute(RecurringPlan $plan): RecurringPlan
    {
        $cancelledCount = DB::transaction(function () use ($plan) {
            $plan->update(['status' => RecurringPlanStatus::PAUSED]);

            return $this->cancelFuturePlannedRows($plan);
        });

        RecurringPlanPaused::dispatch($plan->fresh(), $cancelledCount);

        return $plan;
    }

    private function cancelFuturePlannedRows(RecurringPlan $plan): int
    {
        $today = CarbonImmutable::today()->toDateString();

        $futureRows = PlannedTransaction::query()
            ->where('recurring_plan_id', $plan->id)
            ->where('status', PlannedTransactionStatus::PLANNED->value)
            ->whereDate('due_date', '>=', $today)
            ->get();

        $transferGroupIds = $futureRows->pluck('transfer_group_id')->filter()->unique()->all();
        $count = $futureRows->count();

        foreach ($futureRows as $row) {
            $row->forceDelete();
        }

        if (! empty($transferGroupIds)) {
            PlannedTransaction::query()
                ->whereIn('transfer_group_id', $transferGroupIds)
                ->get()
                ->each(fn (PlannedTransaction $r) => $r->forceDelete());
        }

        return $count;
    }
}
