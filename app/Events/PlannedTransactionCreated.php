<?php

namespace App\Events;

use App\Models\PlannedTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlannedTransactionCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, PlannedTransaction>  $rows  The created row(s). One element for normal txns; two linked rows for internal transfers.
     */
    public function __construct(public array $rows) {}
}
