<?php

namespace App\Http\Controllers;

use App\Actions\CreateAccount;
use App\Enums\CounterpartyKind;
use App\Enums\Currency;
use App\Enums\EntityColor;
use App\Enums\EntityType;
use App\Enums\TransactionKind;
use App\Http\Requests\StoreEntityRequest;
use App\Http\Requests\UpdateEntityRequest;
use App\Models\Counterparty;
use App\Models\Entity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EntityController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $entities = $user->entities()
            ->with(['accounts' => fn ($query) => $query
                ->withSum('transactionsTo as incoming_sum', 'amount')
                ->withSum('transactionsFrom as outgoing_sum', 'amount')
                ->orderByDesc('is_main')
                ->orderBy('name')])
            ->orderByRaw("CASE WHEN type = 'personal' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'color']);

        $entitiesPayload = $entities->map(fn (Entity $entity) => [
            'id' => $entity->id,
            'name' => $entity->name,
            'type' => $entity->type,
            'color' => $entity->color,
            'accounts' => $entity->accounts->map(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'currency' => $account->currency,
                'amount' => $account->amount,
                'current_balance' => number_format($account->currentBalance(), 2, '.', ''),
                'is_main' => $account->is_main,
            ])->all(),
        ])->all();

        $externalCounterparties = $user->counterparties()
            ->where('kind', CounterpartyKind::EXTERNAL)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('entities/Index', [
            'entities' => $entitiesPayload,
            'transaction_options' => [
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

    public function create(): Response
    {
        Gate::authorize('create', Entity::class);

        return Inertia::render('entities/Create', [
            'colors' => $this->colorOptions(),
            'currencies' => $this->currencyOptions(),
        ]);
    }

    public function store(StoreEntityRequest $request, CreateAccount $createAccount): RedirectResponse
    {
        Gate::authorize('create', Entity::class);

        DB::transaction(function () use ($request, $createAccount) {
            $entity = $request->user()->entities()->create([
                'name' => $request->string('name'),
                'type' => EntityType::LLC,
                'color' => EntityColor::from($request->string('color')),
            ]);

            /** @var array<int, array{name: string, currency: string, amount?: float|int|string, is_main: bool|string|int}> $accounts */
            $accounts = $request->validated('accounts');

            foreach ($accounts as $account) {
                $createAccount->execute(
                    $entity,
                    (string) $account['name'],
                    Currency::from((string) $account['currency']),
                    amount: (float) ($account['amount'] ?? 0),
                    isMain: filter_var($account['is_main'], FILTER_VALIDATE_BOOLEAN),
                );
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entity created.')]);

        return to_route('entities.index');
    }

    public function edit(Entity $entity): Response
    {
        Gate::authorize('update', $entity);

        $entity->load(['accounts' => fn ($query) => $query
            ->withSum('transactionsTo as incoming_sum', 'amount')
            ->withSum('transactionsFrom as outgoing_sum', 'amount')
            ->orderByDesc('is_main')
            ->orderBy('name')]);

        return Inertia::render('entities/Edit', [
            'entity' => [
                ...$entity->only(['id', 'name', 'type', 'color']),
                'accounts' => $entity->accounts->map(fn ($account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'currency' => $account->currency,
                    'amount' => $account->amount,
                    'current_balance' => number_format($account->currentBalance(), 2, '.', ''),
                    'is_main' => $account->is_main,
                ])->all(),
            ],
            'colors' => $this->colorOptions(),
            'currencies' => $this->currencyOptions(),
        ]);
    }

    public function update(UpdateEntityRequest $request, Entity $entity): RedirectResponse
    {
        Gate::authorize('update', $entity);

        $entity->update([
            'name' => $request->string('name'),
            'color' => EntityColor::from($request->string('color')),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entity updated.')]);

        return to_route('entities.index');
    }

    public function destroy(Entity $entity): RedirectResponse
    {
        Gate::authorize('delete', $entity);

        $entity->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entity deleted.')]);

        return to_route('entities.index');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function colorOptions(): array
    {
        return array_map(
            fn (EntityColor $color) => ['value' => $color->value, 'label' => $color->label()],
            EntityColor::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string, symbol: string}>
     */
    private function currencyOptions(): array
    {
        return array_map(
            fn (Currency $currency) => [
                'value' => $currency->value,
                'label' => $currency->label(),
                'symbol' => $currency->symbol(),
            ],
            Currency::cases(),
        );
    }
}
