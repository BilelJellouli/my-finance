<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use Carbon\CarbonImmutable;
use Database\Factories\RecurringPlanPhaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'recurring_plan_id',
    'amount',
    'frequency',
    'interval_step',
    'anchor_day',
    'starts_on',
    'ends_on',
    'occurrence_count',
    'reason',
])]
class RecurringPlanPhase extends Model
{
    /** @use HasFactory<RecurringPlanPhaseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'frequency' => RecurringFrequency::class,
            'amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'interval_step' => 'integer',
            'anchor_day' => 'integer',
            'occurrence_count' => 'integer',
        ];
    }

    public function recurringPlan(): BelongsTo
    {
        return $this->belongsTo(RecurringPlan::class);
    }

    public function isOpen(): bool
    {
        return $this->ends_on === null;
    }

    public function advance(CarbonImmutable $date): CarbonImmutable
    {
        $step = max(1, (int) $this->interval_step);

        return match ($this->frequency) {
            RecurringFrequency::WEEKLY => $date->addWeeks($step),
            RecurringFrequency::BIWEEKLY => $date->addWeeks(2 * $step),
            RecurringFrequency::MONTHLY => $date->addMonthsNoOverflow($step),
            RecurringFrequency::QUARTERLY => $date->addMonthsNoOverflow(3 * $step),
            RecurringFrequency::YEARLY => $date->addYearsNoOverflow($step),
        };
    }
}
