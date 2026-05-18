<?php

namespace App\Actions;

use App\Enums\PlannedTransactionStatus;
use App\Enums\RecurringPlanStatus;
use App\Events\RecurringPlanEnded;
use App\Models\PlannedTransaction;
use App\Models\RecurringPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EndRecurringPlan
{
    public function execute(RecurringPlan $plan, string $endsOn): RecurringPlan
    {
        $endDate = CarbonImmutable::parse($endsOn)->toDateString();

        $cancelledCount = DB::transaction(function () use ($plan, $endDate) {
            $plan->update([
                'status' => RecurringPlanStatus::ENDED,
                'ends_on' => $endDate,
            ]);

            $currentPhase = $plan->phases()->whereNull('ends_on')->orderByDesc('starts_on')->first();
            if ($currentPhase) {
                $currentPhase->update(['ends_on' => $endDate]);
            }

            return $this->cancelFuturePlannedRows($plan, $endDate);
        });

        RecurringPlanEnded::dispatch($plan->fresh(), $cancelledCount);

        return $plan;
    }

    private function cancelFuturePlannedRows(RecurringPlan $plan, string $endDate): int
    {
        $futureRows = PlannedTransaction::query()
            ->where('recurring_plan_id', $plan->id)
            ->where('status', PlannedTransactionStatus::PLANNED->value)
            ->whereDate('due_date', '>', $endDate)
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
