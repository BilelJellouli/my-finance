<?php

namespace App\Http\Controllers;

use App\Actions\AddRecurringPlanPhase;
use App\Actions\CreateRecurringPlan;
use App\Actions\DeleteRecurringPlan;
use App\Actions\EndRecurringPlan;
use App\Actions\PauseRecurringPlan;
use App\Actions\ResumeRecurringPlan;
use App\Actions\UpdateRecurringPlan;
use App\Enums\CounterpartyKind;
use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringPlanStatus;
use App\Http\Requests\AddRecurringPlanPhaseRequest;
use App\Http\Requests\DeleteRecurringPlanRequest;
use App\Http\Requests\EndRecurringPlanRequest;
use App\Http\Requests\StoreRecurringPlanRequest;
use App\Http\Requests\UpdateRecurringPlanRequest;
use App\Models\Account;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPhase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RecurringPlanController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', RecurringPlan::class);

        $user = $request->user();

        $plans = RecurringPlan::query()
            ->with([
                'ownerEntity:id,name,type,color',
                'counterparty:id,name,kind,entity_id',
                'account:id,name,currency',
                'phases',
            ])
            ->whereHas('ownerEntity', fn (Builder $q) => $q->where('user_id', $user->id))
            ->orderBy('status')
            ->orderBy('label')
            ->get();

        $entities = $user->entities()
            ->with(['accounts:id,entity_id,name,currency'])
            ->orderByRaw("CASE WHEN type = 'personal' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'color']);

        $externalCounterparties = $user->counterparties()
            ->where('kind', CounterpartyKind::EXTERNAL)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('recurring-plans/Index', [
            'plans' => $plans->map(fn (RecurringPlan $plan) => $this->serializePlan($plan))->all(),
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
                    ])->all(),
                ])->all(),
                'directions' => array_map(
                    fn (PlannedTransactionDirection $d) => ['value' => $d->value, 'label' => $d->label()],
                    PlannedTransactionDirection::cases(),
                ),
                'currencies' => array_map(
                    fn (Currency $c) => ['value' => $c->value, 'label' => $c->label(), 'symbol' => $c->symbol()],
                    Currency::cases(),
                ),
                'frequencies' => array_map(
                    fn (RecurringFrequency $f) => ['value' => $f->value, 'label' => $f->label()],
                    RecurringFrequency::cases(),
                ),
                'statuses' => array_map(
                    fn (RecurringPlanStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    RecurringPlanStatus::cases(),
                ),
                'external_counterparties' => $externalCounterparties->map(fn (Counterparty $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ])->all(),
            ],
        ]);
    }

    public function show(Request $request, RecurringPlan $recurringPlan): Response
    {
        Gate::authorize('view', $recurringPlan);

        $recurringPlan->load([
            'ownerEntity:id,name,type,color',
            'counterparty:id,name,kind,entity_id',
            'account:id,name,currency',
            'phases',
        ]);

        $user = $request->user();

        $upcoming = PlannedTransaction::query()
            ->where('recurring_plan_id', $recurringPlan->id)
            ->with(['transactions' => fn ($q) => $q->orderByDesc('occurred_on')->orderByDesc('id')])
            ->orderBy('due_date')
            ->limit(12)
            ->get()
            ->map(fn (PlannedTransaction $txn) => [
                'id' => $txn->id,
                'amount' => $txn->amount,
                'currency' => $txn->currency,
                'due_date' => $txn->due_date?->toDateString(),
                'purpose' => $txn->purpose,
                'status' => $txn->status,
                'is_mandatory' => $txn->is_mandatory,
                'note' => $txn->note,
                'recorded_count' => $txn->transactions->count(),
            ])
            ->all();

        $entities = $user->entities()
            ->with(['accounts:id,entity_id,name,currency'])
            ->orderByRaw("CASE WHEN type = 'personal' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'color']);

        $projection = $this->projectTotal($recurringPlan);

        return Inertia::render('recurring-plans/Show', [
            'projection' => $projection,
            'plan' => [
                'id' => $recurringPlan->id,
                'label' => $recurringPlan->label,
                'direction' => $recurringPlan->direction,
                'currency' => $recurringPlan->currency,
                'purpose' => $recurringPlan->purpose,
                'is_mandatory' => $recurringPlan->is_mandatory,
                'status' => $recurringPlan->status,
                'starts_on' => $recurringPlan->starts_on?->toDateString(),
                'ends_on' => $recurringPlan->ends_on?->toDateString(),
                'note' => $recurringPlan->note,
                'owner_entity' => [
                    'id' => $recurringPlan->ownerEntity->id,
                    'name' => $recurringPlan->ownerEntity->name,
                    'type' => $recurringPlan->ownerEntity->type,
                    'color' => $recurringPlan->ownerEntity->color,
                ],
                'counterparty' => [
                    'id' => $recurringPlan->counterparty->id,
                    'name' => $recurringPlan->counterparty->displayName(),
                    'kind' => $recurringPlan->counterparty->kind,
                ],
                'account' => $recurringPlan->account ? [
                    'id' => $recurringPlan->account->id,
                    'name' => $recurringPlan->account->name,
                    'currency' => $recurringPlan->account->currency,
                ] : null,
                'phases' => $recurringPlan->phases
                    ->sortBy('starts_on')
                    ->values()
                    ->map(fn (RecurringPlanPhase $phase) => [
                        'id' => $phase->id,
                        'amount' => $phase->amount,
                        'frequency' => $phase->frequency,
                        'interval_step' => $phase->interval_step,
                        'anchor_day' => $phase->anchor_day,
                        'starts_on' => $phase->starts_on?->toDateString(),
                        'ends_on' => $phase->ends_on?->toDateString(),
                        'occurrence_count' => $phase->occurrence_count,
                        'reason' => $phase->reason,
                        'is_current' => $phase->ends_on === null,
                    ])
                    ->all(),
            ],
            'upcoming' => $upcoming,
            'options' => [
                'frequencies' => array_map(
                    fn (RecurringFrequency $f) => ['value' => $f->value, 'label' => $f->label()],
                    RecurringFrequency::cases(),
                ),
                'currencies' => array_map(
                    fn (Currency $c) => ['value' => $c->value, 'label' => $c->label(), 'symbol' => $c->symbol()],
                    Currency::cases(),
                ),
                'statuses' => array_map(
                    fn (PlannedTransactionStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    PlannedTransactionStatus::cases(),
                ),
                'accounts' => $entities
                    ->where('id', $recurringPlan->owner_entity_id)
                    ->first()
                    ?->accounts
                    ->map(fn (Account $a) => ['id' => $a->id, 'name' => $a->name, 'currency' => $a->currency])
                    ->all() ?? [],
            ],
        ]);
    }

    public function store(StoreRecurringPlanRequest $request, CreateRecurringPlan $createRecurringPlan): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $owner = Entity::findOrFail($validated['owner_entity_id']);
        Gate::authorize('update', $owner);
        Gate::authorize('create', RecurringPlan::class);

        $account = null;
        if (! empty($validated['account_id'])) {
            $account = Account::where('entity_id', $owner->id)->findOrFail($validated['account_id']);
        }

        $counterparty = $this->resolveCounterparty($request, $owner);

        $createRecurringPlan->execute(
            owner: $owner,
            counterparty: $counterparty,
            account: $account,
            direction: PlannedTransactionDirection::from($validated['direction']),
            currency: Currency::from($validated['currency']),
            label: $validated['label'],
            purpose: $validated['purpose'] ?? null,
            isMandatory: (bool) $validated['is_mandatory'],
            startsOn: $validated['starts_on'],
            endsOn: $validated['ends_on'] ?? null,
            note: $validated['note'] ?? null,
            amount: (float) $validated['amount'],
            frequency: RecurringFrequency::from($validated['frequency']),
            intervalStep: (int) ($validated['interval_step'] ?? 1),
            anchorDay: isset($validated['anchor_day']) ? (int) $validated['anchor_day'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring plan added.')]);

        return to_route('recurring-plans.index');
    }

    public function update(
        UpdateRecurringPlanRequest $request,
        RecurringPlan $recurringPlan,
        UpdateRecurringPlan $updateRecurringPlan,
    ): RedirectResponse {
        Gate::authorize('update', $recurringPlan);

        $validated = $request->validated();

        $account = null;
        if (! empty($validated['account_id'])) {
            $account = Account::where('entity_id', $recurringPlan->owner_entity_id)->findOrFail($validated['account_id']);
        }

        $updateRecurringPlan->execute(
            plan: $recurringPlan,
            label: $validated['label'],
            account: $account,
            purpose: $validated['purpose'] ?? null,
            isMandatory: (bool) $validated['is_mandatory'],
            endsOn: $validated['ends_on'] ?? null,
            note: $validated['note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring plan updated.')]);

        return back();
    }

    public function addPhase(
        AddRecurringPlanPhaseRequest $request,
        RecurringPlan $recurringPlan,
        AddRecurringPlanPhase $addRecurringPlanPhase,
    ): RedirectResponse {
        Gate::authorize('update', $recurringPlan);

        $validated = $request->validated();

        $addRecurringPlanPhase->execute(
            plan: $recurringPlan,
            amount: (float) $validated['amount'],
            frequency: RecurringFrequency::from($validated['frequency']),
            intervalStep: (int) ($validated['interval_step'] ?? 1),
            anchorDay: isset($validated['anchor_day']) ? (int) $validated['anchor_day'] : null,
            effectiveFrom: $validated['effective_from'],
            reason: $validated['reason'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring plan amount changed going forward.')]);

        return back();
    }

    public function pause(RecurringPlan $recurringPlan, PauseRecurringPlan $pauseRecurringPlan): RedirectResponse
    {
        Gate::authorize('update', $recurringPlan);

        $pauseRecurringPlan->execute($recurringPlan);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring plan paused.')]);

        return back();
    }

    public function resume(RecurringPlan $recurringPlan, ResumeRecurringPlan $resumeRecurringPlan): RedirectResponse
    {
        Gate::authorize('update', $recurringPlan);

        $resumeRecurringPlan->execute($recurringPlan);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring plan resumed.')]);

        return back();
    }

    public function end(
        EndRecurringPlanRequest $request,
        RecurringPlan $recurringPlan,
        EndRecurringPlan $endRecurringPlan,
    ): RedirectResponse {
        Gate::authorize('update', $recurringPlan);

        $endRecurringPlan->execute($recurringPlan, $request->validated('ends_on'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring plan ended.')]);

        return back();
    }

    public function destroy(
        DeleteRecurringPlanRequest $request,
        RecurringPlan $recurringPlan,
        DeleteRecurringPlan $deleteRecurringPlan,
    ): RedirectResponse {
        Gate::authorize('delete', $recurringPlan);

        $deleteRecurringPlan->execute($recurringPlan, $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring plan deleted.')]);

        return to_route('recurring-plans.index');
    }

    private function resolveCounterparty(StoreRecurringPlanRequest $request, Entity $owner): Counterparty
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

        if ($request->filled('counterparty_id')) {
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
     * Project the total cost of a plan with a fixed end date by walking each phase
     * within the plan's window and summing amount × occurrence count. Returns null
     * for open-ended plans.
     *
     * @return array{total: string, occurrences: int, starts_on: string, ends_on: string}|null
     */
    private function projectTotal(RecurringPlan $plan): ?array
    {
        if (! $plan->ends_on) {
            return null;
        }

        $plan->loadMissing('phases');

        $planEnd = CarbonImmutable::parse($plan->ends_on->toDateString());
        $totalAmount = 0.0;
        $totalCount = 0;

        foreach ($plan->phases->sortBy('starts_on') as $phase) {
            /** @var RecurringPlanPhase $phase */
            $phaseStart = CarbonImmutable::parse($phase->starts_on->toDateString());
            $phaseEnd = $phase->ends_on
                ? CarbonImmutable::parse($phase->ends_on->toDateString())
                : $planEnd;

            $windowEnd = $phaseEnd->lt($planEnd) ? $phaseEnd : $planEnd;

            if ($phaseStart->gt($windowEnd)) {
                continue;
            }

            $current = $phaseStart;
            $count = 0;

            while ($current->lte($windowEnd)) {
                if ($phase->occurrence_count !== null && $count >= $phase->occurrence_count) {
                    break;
                }

                $count++;
                $current = $phase->advance($current);
            }

            $totalCount += $count;
            $totalAmount += (float) $phase->amount * $count;
        }

        return [
            'total' => number_format($totalAmount, 2, '.', ''),
            'occurrences' => $totalCount,
            'starts_on' => $plan->starts_on->toDateString(),
            'ends_on' => $plan->ends_on->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlan(RecurringPlan $plan): array
    {
        $current = $plan->phases->whereNull('ends_on')->sortByDesc('starts_on')->first();

        return [
            'id' => $plan->id,
            'label' => $plan->label,
            'direction' => $plan->direction,
            'currency' => $plan->currency,
            'purpose' => $plan->purpose,
            'is_mandatory' => $plan->is_mandatory,
            'status' => $plan->status,
            'starts_on' => $plan->starts_on?->toDateString(),
            'ends_on' => $plan->ends_on?->toDateString(),
            'note' => $plan->note,
            'owner_entity' => [
                'id' => $plan->ownerEntity->id,
                'name' => $plan->ownerEntity->name,
                'type' => $plan->ownerEntity->type,
                'color' => $plan->ownerEntity->color,
            ],
            'counterparty' => [
                'id' => $plan->counterparty->id,
                'name' => $plan->counterparty->displayName(),
                'kind' => $plan->counterparty->kind,
            ],
            'account' => $plan->account ? [
                'id' => $plan->account->id,
                'name' => $plan->account->name,
                'currency' => $plan->account->currency,
            ] : null,
            'current_phase' => $current ? [
                'id' => $current->id,
                'amount' => $current->amount,
                'frequency' => $current->frequency,
                'interval_step' => $current->interval_step,
                'anchor_day' => $current->anchor_day,
                'starts_on' => $current->starts_on?->toDateString(),
            ] : null,
        ];
    }
}
