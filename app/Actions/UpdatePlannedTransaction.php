<?php

namespace App\Actions;

use App\Enums\Currency;
use App\Enums\PlannedTransactionStatus;
use App\Events\PlannedTransactionUpdated;
use App\Models\PlannedTransaction;
use Illuminate\Support\Facades\DB;

class UpdatePlannedTransaction
{
    /**
     * Update the financial fields of a planned transaction. When the row is part
     * of a transfer pair, the mirror row is updated atomically with the same
     * fields (direction stays mirrored — never flipped here).
     *
     * @return array<int, PlannedTransaction> The updated row(s). Two elements when part of a transfer pair.
     */
    public function execute(
        PlannedTransaction $plannedTransaction,
        float $amount,
        Currency $currency,
        ?string $dueDate,
        ?string $purpose,
        PlannedTransactionStatus $status,
        bool $isMandatory,
        ?string $note,
    ): array {
        $rows = DB::transaction(function () use (
            $plannedTransaction,
            $amount,
            $currency,
            $dueDate,
            $purpose,
            $status,
            $isMandatory,
            $note,
        ) {
            $attributes = [
                'amount' => $amount,
                'currency' => $currency,
                'due_date' => $dueDate,
                'purpose' => $purpose,
                'status' => $status,
                'is_mandatory' => $isMandatory,
                'note' => $note,
            ];

            $plannedTransaction->update($attributes);

            if ($plannedTransaction->transfer_group_id === null) {
                return [$plannedTransaction->fresh()];
            }

            $mirrors = PlannedTransaction::query()
                ->where('transfer_group_id', $plannedTransaction->transfer_group_id)
                ->where('id', '!=', $plannedTransaction->id)
                ->get();

            foreach ($mirrors as $mirror) {
                $mirror->update($attributes);
            }

            return PlannedTransaction::query()
                ->where('transfer_group_id', $plannedTransaction->transfer_group_id)
                ->orderBy('id')
                ->get()
                ->all();
        });

        PlannedTransactionUpdated::dispatch($rows);

        return $rows;
    }
}
