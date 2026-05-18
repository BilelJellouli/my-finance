<?php

namespace App\Events;

use App\Models\PlannedTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlannedTransactionDeleted
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, PlannedTransaction>  $rows  The soft-deleted row(s). One element for normal txns; two linked rows for internal transfers.
     */
    public function __construct(public array $rows, public string $reason) {}
}
