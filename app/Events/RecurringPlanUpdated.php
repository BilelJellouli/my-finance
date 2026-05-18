<?php

namespace App\Events;

use App\Models\RecurringPlan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecurringPlanUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public RecurringPlan $plan) {}
}
