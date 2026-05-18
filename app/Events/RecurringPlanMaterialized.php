<?php

namespace App\Events;

use App\Models\RecurringPlan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecurringPlanMaterialized
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public RecurringPlan $plan,
        public int $createdCount,
        public string $horizon,
    ) {}
}
