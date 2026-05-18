<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, Transaction>  $rows  The created row(s). One element for normal txns; two linked rows for internal transfers.
     */
    public function __construct(public array $rows) {}
}
