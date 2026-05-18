<?php

namespace App\Actions;

use App\Enums\PlannedTransactionStatus;
use App\Events\RecurringPlanDeleted;
use App\Models\PlannedTransaction;
use App\Models\RecurringPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DeleteRecurringPlan
{
    public function execute(RecurringPlan $plan, ?string $reason = null): void
    {
        DB::transaction(function () use ($plan) {
            $this->cancelFuturePlannedRows($plan);

            $plan->delete();
        });

        RecurringPlanDeleted::dispatch($plan, $reason);
    }

    private function cancelFuturePlannedRows(RecurringPlan $plan): void
    {
        $today = CarbonImmutable::today()->toDateString();

        $futureRows = PlannedTransaction::query()
            ->where('recurring_plan_id', $plan->id)
            ->where('status', PlannedTransactionStatus::PLANNED->value)
            ->whereDate('due_date', '>=', $today)
            ->get();

        $transferGroupIds = $futureRows->pluck('transfer_group_id')->filter()->unique()->all();

        foreach ($futureRows as $row) {
            $row->forceDelete();
        }

        if (! empty($transferGroupIds)) {
            PlannedTransaction::query()
                ->whereIn('transfer_group_id', $transferGroupIds)
                ->get()
                ->each(fn (PlannedTransaction $r) => $r->forceDelete());
        }
    }
}
