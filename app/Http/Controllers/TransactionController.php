<?php

namespace App\Http\Controllers;

use App\Actions\CreateTransaction;
use App\Actions\DeleteTransaction;
use App\Actions\UpdateTransaction;
use App\Enums\CounterpartyKind;
use App\Enums\Currency;
use App\Enums\TransactionKind;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Transaction::class);

        $user = $request->user();
        $filters = $this->validatedFilters($request);

        $query = Transaction::query()
            ->with([
                'fromAccount:id,name,currency,entity_id',
                'fromAccount.entity:id,name,type,color',
                'toAccount:id,name,currency,entity_id',
                'toAccount.entity:id,name,type,color',
                'counterparty:id,name,kind,entity_id',
                'plannedTransaction:id,purpose,due_date,owner_entity_id,counterparty_id,amount,currency',
                'plannedTransaction.counterparty:id,name,kind,entity_id',
            ])
            ->where(function (Builder $q) use ($user) {
                $q->whereHas('fromAccount.entity', fn (Builder $sub) => $sub->where('user_id', $user->id))
                    ->orWhereHas('toAccount.entity', fn (Builder $sub) => $sub->where('user_id', $user->id))
                    ->orWhereHas('plannedTransaction.ownerEntity', fn (Builder $sub) => $sub->where('user_id', $user->id));
            });

        if ($filters['entity_id'] !== null) {
            $query->where(function (Builder $q) use ($filters) {
                $q->whereHas('fromAccount', fn (Builder $sub) => $sub->where('entity_id', $filters['entity_id']))
                    ->orWhereHas('toAccount', fn (Builder $sub) => $sub->where('entity_id', $filters['entity_id']))
                    ->orWhereHas('plannedTransaction', fn (Builder $sub) => $sub->where('owner_entity_id', $filters['entity_id']));
            });
        }

        if ($filters['kind'] !== null) {
            $query->where('kind', $filters['kind']);
        }

        if ($filters['account_id'] !== null) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('from_account_id', $filters['account_id'])
                    ->orWhere('to_account_id', $filters['account_id']);
            });
        }

        if ($filters['has_planned'] === 'yes') {
            $query->whereNotNull('planned_transaction_id');
        } elseif ($filters['has_planned'] === 'no') {
            $query->whereNull('planned_transaction_id');
        }

        if ($filters['from'] !== null) {
            $query->whereDate('occurred_on', '>=', $filters['from']);
        }

        if ($filters['to'] !== null) {
            $query->whereDate('occurred_on', '<=', $filters['to']);
        }

        $query->orderByDesc('occurred_on')->orderByDesc('id');

        $page = $query->paginate(25)->withQueryString();

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

        $openPlanned = PlannedTransaction::query()
            ->whereHas('ownerEntity', fn (Builder $q) => $q->where('user_id', $user->id))
            ->whereIn('status', ['planned', 'overdue'])
            ->with(['ownerEntity:id,name,color', 'counterparty:id,name,kind'])
            ->orderBy('due_date')
            ->limit(200)
            ->get();

        return Inertia::render('transactions/Index', [
            'transactions' => [
                'data' => $page->getCollection()->map(fn (Transaction $t) => $this->serialize($t))->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
            'filters' => $filters,
            'options' => [
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
                'open_planned' => $openPlanned->map(fn (PlannedTransaction $p) => [
                    'id' => $p->id,
                    'direction' => $p->direction,
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'due_date' => $p->due_date?->toDateString(),
                    'purpose' => $p->purpose,
                    'owner_entity' => [
                        'id' => $p->ownerEntity->id,
                        'name' => $p->ownerEntity->name,
                        'color' => $p->ownerEntity->color,
                    ],
                    'counterparty' => [
                        'id' => $p->counterparty->id,
                        'name' => $p->counterparty->displayName(),
                        'kind' => $p->counterparty->kind,
                    ],
                ])->all(),
            ],
        ]);
    }

    public function store(StoreTransactionRequest $request, CreateTransaction $createTransaction): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $planned = isset($validated['planned_transaction_id'])
            ? PlannedTransaction::findOrFail($validated['planned_transaction_id'])
            : null;

        Gate::authorize('create', [Transaction::class, $planned]);

        $createTransaction->execute(
            user: $user,
            amount: (float) $validated['amount'],
            occurredOn: $validated['occurred_on'],
            kind: TransactionKind::from($validated['kind']),
            currency: Currency::from($validated['currency']),
            fromAccount: isset($validated['from_account_id']) ? Account::findOrFail($validated['from_account_id']) : null,
            toAccount: isset($validated['to_account_id']) ? Account::findOrFail($validated['to_account_id']) : null,
            counterparty: isset($validated['counterparty_id']) ? Counterparty::findOrFail($validated['counterparty_id']) : null,
            plannedTransaction: $planned,
            note: $validated['note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction recorded.')]);

        return back();
    }

    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction,
        UpdateTransaction $updateTransaction,
    ): RedirectResponse {
        Gate::authorize('update', $transaction);

        $user = $request->user();
        $validated = $request->validated();

        $updateTransaction->execute(
            user: $user,
            transaction: $transaction,
            amount: (float) $validated['amount'],
            occurredOn: $validated['occurred_on'],
            kind: TransactionKind::from($validated['kind']),
            currency: Currency::from($validated['currency']),
            fromAccount: isset($validated['from_account_id']) ? Account::findOrFail($validated['from_account_id']) : null,
            toAccount: isset($validated['to_account_id']) ? Account::findOrFail($validated['to_account_id']) : null,
            counterparty: isset($validated['counterparty_id']) ? Counterparty::findOrFail($validated['counterparty_id']) : null,
            note: $validated['note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction updated.')]);

        return back();
    }

    public function destroy(Transaction $transaction, DeleteTransaction $deleteTransaction): RedirectResponse
    {
        Gate::authorize('delete', $transaction);

        $deleteTransaction->execute($transaction);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Transaction $t): array
    {
        return [
            'id' => $t->id,
            'amount' => $t->amount,
            'currency' => $t->currency,
            'kind' => $t->kind,
            'occurred_on' => $t->occurred_on->toDateString(),
            'note' => $t->note,
            'from_account' => $t->fromAccount ? [
                'id' => $t->fromAccount->id,
                'name' => $t->fromAccount->name,
                'currency' => $t->fromAccount->currency,
                'entity' => $t->fromAccount->entity ? [
                    'id' => $t->fromAccount->entity->id,
                    'name' => $t->fromAccount->entity->name,
                    'color' => $t->fromAccount->entity->color,
                ] : null,
            ] : null,
            'to_account' => $t->toAccount ? [
                'id' => $t->toAccount->id,
                'name' => $t->toAccount->name,
                'currency' => $t->toAccount->currency,
                'entity' => $t->toAccount->entity ? [
                    'id' => $t->toAccount->entity->id,
                    'name' => $t->toAccount->entity->name,
                    'color' => $t->toAccount->entity->color,
                ] : null,
            ] : null,
            'counterparty' => $t->counterparty ? [
                'id' => $t->counterparty->id,
                'name' => $t->counterparty->displayName(),
                'kind' => $t->counterparty->kind,
            ] : null,
            'planned_transaction' => $t->plannedTransaction ? [
                'id' => $t->plannedTransaction->id,
                'purpose' => $t->plannedTransaction->purpose,
                'due_date' => $t->plannedTransaction->due_date?->toDateString(),
                'amount' => $t->plannedTransaction->amount,
                'counterparty' => $t->plannedTransaction->counterparty ? [
                    'id' => $t->plannedTransaction->counterparty->id,
                    'name' => $t->plannedTransaction->counterparty->displayName(),
                    'kind' => $t->plannedTransaction->counterparty->kind,
                ] : null,
            ] : null,
        ];
    }

    /**
     * @return array{entity_id: ?int, kind: ?string, account_id: ?int, has_planned: ?string, from: ?string, to: ?string}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'kind' => ['nullable', 'string', 'in:cash,bank_transfer,card'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'has_planned' => ['nullable', 'string', 'in:yes,no'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return [
            'entity_id' => isset($validated['entity_id']) ? (int) $validated['entity_id'] : null,
            'kind' => $validated['kind'] ?? null,
            'account_id' => isset($validated['account_id']) ? (int) $validated['account_id'] : null,
            'has_planned' => $validated['has_planned'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }
}
