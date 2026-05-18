<?php

namespace App\Actions;

use App\Events\PlannedTransactionDeleted;
use App\Models\PlannedTransaction;
use Illuminate\Support\Facades\DB;

class DeletePlannedTransaction
{
    /**
     * Soft-delete the planned transaction and, when part of a transfer pair,
     * soft-delete the mirror row atomically with the same reason.
     *
     * @return array<int, PlannedTransaction> The soft-deleted row(s).
     */
    public function execute(PlannedTransaction $plannedTransaction, string $reason): array
    {
        $rows = DB::transaction(function () use ($plannedTransaction, $reason) {
            $plannedTransaction->deletion_reason = $reason;
            $plannedTransaction->save();
            $plannedTransaction->delete();

            $deleted = [$plannedTransaction];

            if ($plannedTransaction->transfer_group_id !== null) {
                $mirrors = PlannedTransaction::query()
                    ->where('transfer_group_id', $plannedTransaction->transfer_group_id)
                    ->where('id', '!=', $plannedTransaction->id)
                    ->get();

                foreach ($mirrors as $mirror) {
                    $mirror->deletion_reason = $reason;
                    $mirror->save();
                    $mirror->delete();
                    $deleted[] = $mirror;
                }
            }

            return $deleted;
        });

        PlannedTransactionDeleted::dispatch($rows, $reason);

        return $rows;
    }
}
