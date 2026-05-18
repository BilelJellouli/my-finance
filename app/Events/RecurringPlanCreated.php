<?php

namespace App\Events;

use App\Models\RecurringPlan;
use App\Models\RecurringPlanPhase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecurringPlanCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public RecurringPlan $plan, public RecurringPlanPhase $initialPhase) {}
}
