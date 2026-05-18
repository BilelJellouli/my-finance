<?php

namespace App\Events;

use App\Models\RecurringPlan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecurringPlanEnded
{
    use Dispatchable, SerializesModels;

    public function __construct(public RecurringPlan $plan, public int $cancelledCount) {}
}
