<?php

namespace App\Http\Controllers;

use App\Actions\CreatePlannedTransaction;
use App\Actions\UpdatePlannedTransaction;
use App\Enums\CounterpartyKind;
use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Http\Requests\StorePlannedTransactionRequest;
use App\Http\Requests\UpdatePlannedTransactionRequest;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlannedTransactionController extends Controller implements HasMiddleware
{
    private const SORTABLE_COLUMNS = ['due_date', 'amount', 'purpose', 'status', 'direction', 'is_mandatory'];

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PlannedTransaction::class);

        $user = $request->user();

        $filters = $this->validatedFilters($request);

        $query = PlannedTransaction::query()
            ->with(['ownerEntity:id,name,type,color', 'counterparty:id,name,kind,entity_id'])
            ->whereHas('ownerEntity', fn (Builder $q) => $q->where('user_id', $user->id));

        if ($filters['direction']) {
            $query->where('direction', $filters['direction']);
        }

        if ($filters['entity_id']) {
            $query->where('owner_entity_id', $filters['entity_id']);
        }

        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        if ($filters['purpose']) {
            $query->where('purpose', $filters['purpose']);
        }

        if ($filters['mandatory'] !== null) {
            $query->where('is_mandatory', $filters['mandatory']);
        }

        if ($filters['due_from']) {
            $query->whereDate('due_date', '>=', $filters['due_from']);
        }

        if ($filters['due_to']) {
            $query->whereDate('due_date', '<=', $filters['due_to']);
        }

        $query->orderBy($filters['sort'], $filters['dir'])->orderBy('id');

        $transactions = $query->paginate(25)->withQueryString();

        $entities = $user->entities()
            ->orderByRaw("CASE WHEN type = 'personal' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'color']);

        $purposes = PlannedTransaction::query()
            ->whereHas('ownerEntity', fn (Builder $q) => $q->where('user_id', $user->id))
            ->whereNotNull('purpose')
            ->distinct()
            ->orderBy('purpose')
            ->pluck('purpose')
            ->all();

        $externalCounterparties = $user->counterparties()
            ->where('kind', CounterpartyKind::EXTERNAL)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('planned-transactions/Index', [
            'transactions' => [
                'data' => $transactions->getCollection()->map(fn (PlannedTransaction $txn) => [
                    'id' => $txn->id,
                    'direction' => $txn->direction,
                    'amount' => $txn->amount,
                    'currency' => $txn->currency,
                    'due_date' => $txn->due_date?->toDateString(),
                    'purpose' => $txn->purpose,
                    'status' => $txn->status,
                    'is_mandatory' => $txn->is_mandatory,
                    'note' => $txn->note,
                    'transfer_group_id' => $txn->transfer_group_id,
                    'owner_entity' => [
                        'id' => $txn->ownerEntity->id,
                        'name' => $txn->ownerEntity->name,
                        'type' => $txn->ownerEntity->type,
                        'color' => $txn->ownerEntity->color,
                    ],
                    'counterparty' => [
                        'id' => $txn->counterparty->id,
                        'name' => $txn->counterparty->displayName(),
                        'kind' => $txn->counterparty->kind,
                    ],
                ])->all(),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ],
            'filters' => $filters,
            'options' => [
                'entities' => $entities->map(fn (Entity $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'type' => $e->type,
                    'color' => $e->color,
                ])->all(),
                'directions' => array_map(
                    fn (PlannedTransactionDirection $d) => ['value' => $d->value, 'label' => $d->label()],
                    PlannedTransactionDirection::cases(),
                ),
                'statuses' => array_map(
                    fn (PlannedTransactionStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    PlannedTransactionStatus::cases(),
                ),
                'currencies' => array_map(
                    fn (Currency $c) => ['value' => $c->value, 'label' => $c->label(), 'symbol' => $c->symbol()],
                    Currency::cases(),
                ),
                'purposes' => $purposes,
                'external_counterparties' => $externalCounterparties->map(fn (Counterparty $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ])->all(),
                'sortable' => self::SORTABLE_COLUMNS,
            ],
        ]);
    }

    public function store(StorePlannedTransactionRequest $request, CreatePlannedTransaction $createPlannedTransaction): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $owner = Entity::findOrFail($validated['owner_entity_id']);
        Gate::authorize('update', $owner);
        Gate::authorize('create', PlannedTransaction::class);

        $counterparty = $this->resolveCounterparty($request, $owner);

        $createPlannedTransaction->execute(
            owner: $owner,
            counterparty: $counterparty,
            direction: PlannedTransactionDirection::from($validated['direction']),
            amount: (float) $validated['amount'],
            currency: Currency::from($validated['currency']),
            dueDate: $validated['due_date'] ?? null,
            purpose: $validated['purpose'] ?? null,
            status: PlannedTransactionStatus::from($validated['status'] ?? PlannedTransactionStatus::PLANNED->value),
            isMandatory: (bool) $validated['is_mandatory'],
            note: $validated['note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Planned transaction added.')]);

        return to_route('planned-transactions.index');
    }

    public function update(
        UpdatePlannedTransactionRequest $request,
        PlannedTransaction $plannedTransaction,
        UpdatePlannedTransaction $updatePlannedTransaction,
    ): RedirectResponse {
        Gate::authorize('update', $plannedTransaction);

        $validated = $request->validated();

        $updatePlannedTransaction->execute(
            plannedTransaction: $plannedTransaction,
            amount: (float) $validated['amount'],
            currency: Currency::from($validated['currency']),
            dueDate: $validated['due_date'] ?? null,
            purpose: $validated['purpose'] ?? null,
            status: PlannedTransactionStatus::from($validated['status']),
            isMandatory: (bool) $validated['is_mandatory'],
            note: $validated['note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Planned transaction updated.')]);

        return back();
    }

    private function resolveCounterparty(StorePlannedTransactionRequest $request, Entity $owner): Counterparty
    {
        $user = $request->user();
        $mode = $request->string('counterparty_mode');

        if ($mode->is('internal')) {
            /** @var Entity $internalEntity */
            $internalEntity = Entity::where('user_id', $user->id)
                ->findOrFail((int) $request->input('internal_entity_id'));

            return Counterparty::firstOrCreate(
                ['entity_id' => $internalEntity->id],
                [
                    'user_id' => $internalEntity->user_id,
                    'name' => $internalEntity->name,
                    'kind' => CounterpartyKind::INTERNAL,
                ],
            );
        }

        if ($mode->is('external_existing')) {
            return Counterparty::where('user_id', $user->id)
                ->where('kind', CounterpartyKind::EXTERNAL)
                ->findOrFail((int) $request->input('counterparty_id'));
        }

        return Counterparty::create([
            'user_id' => $user->id,
            'name' => trim((string) $request->input('external_name')),
            'kind' => CounterpartyKind::EXTERNAL,
        ]);
    }

    /**
     * @return array{
     *     direction: ?string,
     *     entity_id: ?int,
     *     status: ?string,
     *     purpose: ?string,
     *     mandatory: ?bool,
     *     due_from: ?string,
     *     due_to: ?string,
     *     sort: string,
     *     dir: string
     * }
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'direction' => ['nullable', 'string', 'in:incoming,outgoing'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'status' => ['nullable', 'string', 'in:planned,settled,overdue,cancelled'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'mandatory' => ['nullable', 'in:yes,no'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', self::SORTABLE_COLUMNS)],
            'dir' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        return [
            'direction' => $validated['direction'] ?? null,
            'entity_id' => isset($validated['entity_id']) ? (int) $validated['entity_id'] : null,
            'status' => $validated['status'] ?? null,
            'purpose' => $validated['purpose'] ?? null,
            'mandatory' => match ($validated['mandatory'] ?? null) {
                'yes' => true,
                'no' => false,
                default => null,
            },
            'due_from' => $validated['due_from'] ?? null,
            'due_to' => $validated['due_to'] ?? null,
            'sort' => $validated['sort'] ?? 'due_date',
            'dir' => $validated['dir'] ?? 'asc',
        ];
    }
}
