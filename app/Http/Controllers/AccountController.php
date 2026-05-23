<?php

namespace App\Http\Controllers;

use App\Actions\CreateAccount;
use App\Actions\DeleteAccount;
use App\Actions\UpdateAccount;
use App\Enums\CounterpartyKind;
use App\Enums\Currency;
use App\Enums\TransactionKind;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }

    public function show(Request $request, Account $account): Response
    {
        Gate::authorize('view', $account);

        $user = $request->user();

        $account->load(['entity:id,name,color,user_id']);

        $transactions = Transaction::query()
            ->where(function ($q) use ($account) {
                $q->where('from_account_id', $account->id)
                    ->orWhere('to_account_id', $account->id);
            })
            ->with([
                'fromAccount:id,name,currency,entity_id',
                'fromAccount.entity:id,name,color',
                'toAccount:id,name,currency,entity_id',
                'toAccount.entity:id,name,color',
                'counterparty:id,name,kind,entity_id',
                'plannedTransaction:id,purpose,due_date,counterparty_id,amount',
                'plannedTransaction.counterparty:id,name,kind,entity_id',
            ])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $running = (float) $account->amount;
        $entries = [];
        foreach ($transactions as $t) {
            $isIncoming = $t->to_account_id === $account->id;
            $signed = $isIncoming ? (float) $t->amount : -(float) $t->amount;
            $running += $signed;

            $otherAccount = $isIncoming ? $t->fromAccount : $t->toAccount;
            $externalParty = $otherAccount === null
                ? ($t->counterparty?->displayName() ?? $t->plannedTransaction?->counterparty?->displayName())
                : null;

            $entries[] = [
                'id' => $t->id,
                'occurred_on' => $t->occurred_on->toDateString(),
                'direction' => $isIncoming ? 'incoming' : 'outgoing',
                'amount' => number_format((float) $t->amount, 2, '.', ''),
                'signed_amount' => number_format($signed, 2, '.', ''),
                'running_balance' => number_format($running, 2, '.', ''),
                'currency' => $t->currency,
                'kind' => $t->kind,
                'note' => $t->note,
                'other_account' => $otherAccount ? [
                    'id' => $otherAccount->id,
                    'name' => $otherAccount->name,
                    'currency' => $otherAccount->currency,
                    'entity' => $otherAccount->entity ? [
                        'id' => $otherAccount->entity->id,
                        'name' => $otherAccount->entity->name,
                        'color' => $otherAccount->entity->color,
                    ] : null,
                ] : null,
                'external_party' => $externalParty,
                'planned_transaction' => $t->plannedTransaction ? [
                    'id' => $t->plannedTransaction->id,
                    'purpose' => $t->plannedTransaction->purpose,
                    'due_date' => $t->plannedTransaction->due_date?->toDateString(),
                ] : null,
                'from_account_id' => $t->from_account_id,
                'to_account_id' => $t->to_account_id,
                'counterparty_id' => $t->counterparty_id,
            ];
        }

        $currentBalance = $running;
        $entries = array_reverse($entries);

        $entities = $user->entities()
            ->orderByRaw("CASE WHEN type = 'personal' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->with(['accounts' => fn ($q) => $q
                ->withSum('transactionsTo as incoming_sum', 'amount')
                ->withSum('transactionsFrom as outgoing_sum', 'amount')
                ->orderBy('name')])
            ->get();

        $externalCounterparties = $user->counterparties()
            ->where('kind', CounterpartyKind::EXTERNAL)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('accounts/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'currency' => $account->currency,
                'opening_balance' => number_format((float) $account->amount, 2, '.', ''),
                'current_balance' => number_format($currentBalance, 2, '.', ''),
                'is_main' => $account->is_main,
                'entity' => [
                    'id' => $account->entity->id,
                    'name' => $account->entity->name,
                    'color' => $account->entity->color,
                ],
            ],
            'ledger' => $entries,
            'transaction_options' => [
                'entities' => $entities->map(fn (Entity $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'type' => $e->type,
                    'color' => $e->color,
                    'accounts' => $e->accounts->map(fn (Account $a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'currency' => $a->currency,
                        'current_balance' => number_format($a->currentBalance(), 2, '.', ''),
                    ])->all(),
                ])->all(),
                'kinds' => array_map(
                    fn (TransactionKind $k) => ['value' => $k->value, 'label' => $k->label()],
                    TransactionKind::cases(),
                ),
                'currencies' => array_map(
                    fn (Currency $c) => ['value' => $c->value, 'label' => $c->label(), 'symbol' => $c->symbol()],
                    Currency::cases(),
                ),
                'external_counterparties' => $externalCounterparties->map(fn (Counterparty $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ])->all(),
            ],
        ]);
    }

    public function store(StoreAccountRequest $request, Entity $entity, CreateAccount $createAccount): RedirectResponse
    {
        Gate::authorize('update', $entity);
        Gate::authorize('create', Account::class);

        $createAccount->execute(
            $entity,
            (string) $request->string('name'),
            Currency::from((string) $request->string('currency')),
            amount: (float) $request->input('amount', 0),
            isMain: filter_var($request->input('is_main'), FILTER_VALIDATE_BOOLEAN),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account added.')]);

        return back();
    }

    public function update(UpdateAccountRequest $request, Account $account, UpdateAccount $updateAccount): RedirectResponse
    {
        Gate::authorize('update', $account);

        $updateAccount->execute(
            $account,
            Currency::from($request->string('currency')),
            (float) $request->input('amount'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return back();
    }

    public function destroy(Account $account, DeleteAccount $deleteAccount): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $deleteAccount->execute($account);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account deleted.')]);

        return back();
    }
}
