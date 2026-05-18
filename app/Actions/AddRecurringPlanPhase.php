<?php

namespace App\Actions;

use App\Enums\PlannedTransactionStatus;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringPlanStatus;
use App\Events\RecurringPlanPhaseAdded;
use App\Models\PlannedTransaction;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPhase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AddRecurringPlanPhase
{
    public function __construct(private MaterializeRecurringPlan $materializeRecurringPlan) {}

    public function execute(
        RecurringPlan $plan,
        float $amount,
        RecurringFrequency $frequency,
        int $intervalStep,
        ?int $anchorDay,
        string $effectiveFrom,
        ?string $reason,
    ): RecurringPlanPhase {
        if ($plan->status === RecurringPlanStatus::ENDED) {
            throw new InvalidArgumentException('Cannot add a phase to an ended plan.');
        }

        $effectiveDate = CarbonImmutable::parse($effectiveFrom);
        $cutDate = $effectiveDate->subDay();

        [$previousPhase, $newPhase] = DB::transaction(function () use ($plan, $amount, $frequency, $intervalStep, $anchorDay, $effectiveDate, $cutDate, $reason) {
            $currentPhase = $plan->phases()->whereNull('ends_on')->orderByDesc('starts_on')->first();

            if (! $currentPhase) {
                throw new InvalidArgumentException('Plan has no open phase to replace.');
            }

            $currentStart = CarbonImmutable::parse($currentPhase->starts_on->toDateString());

            if ($effectiveDate->lte($currentStart)) {
                throw new InvalidArgumentException('Effective date must be after the current phase start.');
            }

            $currentPhase->update(['ends_on' => $cutDate->toDateString()]);

            $newPhase = RecurringPlanPhase::create([
                'recurring_plan_id' => $plan->id,
                'amount' => $amount,
                'frequency' => $frequency,
                'interval_step' => $intervalStep,
                'anchor_day' => $anchorDay,
                'starts_on' => $effectiveDate->toDateString(),
                'ends_on' => null,
                'occurrence_count' => null,
                'reason' => $reason,
            ]);

            $this->cancelFuturePlannedRows($plan, $effectiveDate);

            return [$currentPhase, $newPhase];
        });

        $this->materializeRecurringPlan->execute($plan->fresh(['phases', 'ownerEntity', 'counterparty']));

        RecurringPlanPhaseAdded::dispatch($plan, $previousPhase, $newPhase);

        return $newPhase;
    }

    private function cancelFuturePlannedRows(RecurringPlan $plan, CarbonImmutable $effectiveDate): void
    {
        $futureRows = PlannedTransaction::query()
            ->where('recurring_plan_id', $plan->id)
            ->where('status', PlannedTransactionStatus::PLANNED->value)
            ->whereDate('due_date', '>=', $effectiveDate->toDateString())
            ->get();

        $transferGroupIds = $futureRows->pluck('transfer_group_id')->filter()->unique()->all();

        foreach ($futureRows as $row) {
            $row->forceDelete();
        }

        if (! empty($transferGroupIds)) {
            $mirrors = PlannedTransaction::query()
                ->whereIn('transfer_group_id', $transferGroupIds)
                ->get();

            foreach ($mirrors as $mirror) {
                $mirror->forceDelete();
            }
        }
    }
}
