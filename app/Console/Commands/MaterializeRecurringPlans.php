<?php

namespace App\Console\Commands;

use App\Actions\MaterializeRecurringPlan;
use App\Enums\RecurringPlanStatus;
use App\Models\RecurringPlan;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recurring:materialize {--horizon-days= : Override the configured horizon}')]
#[Description('Materialize upcoming planned transactions for all active recurring plans up to the configured horizon.')]
class MaterializeRecurringPlans extends Command
{
    public function handle(MaterializeRecurringPlan $materializeRecurringPlan): int
    {
        $horizonDays = (int) ($this->option('horizon-days') ?? config('recurring.horizon_days'));
        $horizon = CarbonImmutable::today()->addDays($horizonDays);

        $totalCreated = 0;
        $plans = RecurringPlan::query()
            ->where('status', RecurringPlanStatus::ACTIVE)
            ->cursor();

        foreach ($plans as $plan) {
            $created = $materializeRecurringPlan->execute($plan, $horizon);
            $totalCreated += $created;
            $this->line(sprintf('  · plan #%d "%s" — %d row(s)', $plan->id, $plan->label, $created));
        }

        $this->info(sprintf('Materialized %d planned transaction(s) up to %s.', $totalCreated, $horizon->toDateString()));

        return self::SUCCESS;
    }
}
