<?php

namespace App\Http\Controllers;

use App\Actions\CreateTransaction;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TransactionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }

    public function store(
        StoreTransactionRequest $request,
        PlannedTransaction $plannedTransaction,
        CreateTransaction $createTransaction,
    ): RedirectResponse {
        Gate::authorize('create', [Transaction::class, $plannedTransaction]);

        $validated = $request->validated();

        $createTransaction->execute(
            plannedTransaction: $plannedTransaction,
            amount: (float) $validated['amount'],
            occurredOn: $validated['occurred_on'],
            note: $validated['note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction recorded.')]);

        return back();
    }
}
