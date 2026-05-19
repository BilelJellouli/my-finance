<?php

namespace App\Http\Controllers;

use App\Enums\CounterpartyKind;
use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Models\Account;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller implements HasMiddleware
{
    private const PERIOD_OPTIONS = [30, 60, 90, 180, 365];

    public static function middleware(): array
    {
        return [
            new Middleware(['auth', 'verified']),
        ];
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $periodDays = $this->resolvePeriod($request);

        /** @var Collection<int, Entity> $entities */
        $entities = $user->entities()
            ->with(['accounts' => fn ($q) => $q->orderByDesc('is_main')->orderBy('name')])
            ->orderByRaw("CASE WHEN type = 'personal' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        if ($entities->isEmpty()) {
            return Inertia::render('Dashboard', [
                'period' => $this->periodPayload($periodDays),
                'period_options' => self::PERIOD_OPTIONS,
                'entities' => [],
                'flows' => [],
                'timeline' => [],
                'undated' => ['items' => [], 'totals' => []],
                'selected_entity_id' => null,
            ]);
        }

        $selectedId = $this->resolveSelectedEntity($request, $entities);
        $from = CarbonImmutable::today();
        $to = $from->addDays($periodDays);

        $plannedRows = $this->fetchPlannedRows($entities, $from, $to);

        $entityPayload = $this->buildEntityPayload($entities, $plannedRows, $selectedId);
        $flows = $this->buildFlows($entities, $plannedRows);
        $timeline = $this->buildTimeline(
            $plannedRows->where('owner_entity_id', $selectedId),
            $entityPayload[$selectedId]['cash_by_currency'] ?? [],
            $from,
        );
        $undated = $this->buildUndatedForEntity($selectedId);

        return Inertia::render('Dashboard', [
            'period' => $this->periodPayload($periodDays),
            'period_options' => self::PERIOD_OPTIONS,
            'entities' => array_values($entityPayload),
            'flows' => $flows,
            'timeline' => $timeline,
            'undated' => $undated,
            'selected_entity_id' => $selectedId,
        ]);
    }

    private function resolvePeriod(Request $request): int
    {
        $requested = (int) $request->query('period_days', 60);

        if (in_array($requested, self::PERIOD_OPTIONS, true)) {
            return $requested;
        }

        return 60;
    }

    /**
     * @param  Collection<int, Entity>  $entities
     */
    private function resolveSelectedEntity(Request $request, Collection $entities): int
    {
        $requested = (int) $request->query('entity_id', 0);

        if ($requested > 0 && $entities->contains('id', $requested)) {
            return $requested;
        }

        return (int) $entities->first()->id;
    }

    /**
     * @param  Collection<int, Entity>  $entities
     * @return Collection<int, PlannedTransaction>
     */
    private function fetchPlannedRows(Collection $entities, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return PlannedTransaction::query()
            ->with('counterparty:id,kind,entity_id,name')
            ->whereIn('owner_entity_id', $entities->pluck('id'))
            ->whereIn('status', [
                PlannedTransactionStatus::PLANNED->value,
                PlannedTransactionStatus::OVERDUE->value,
            ])
            ->where('due_date', '<=', $to->toDateString())
            ->get();
    }

    /**
     * @param  Collection<int, Entity>  $entities
     * @param  Collection<int, PlannedTransaction>  $plannedRows
     * @return array<int, array<string, mixed>>
     */
    private function buildEntityPayload(Collection $entities, Collection $plannedRows, int $selectedId): array
    {
        $payload = [];

        foreach ($entities as $entity) {
            $cashByCurrency = $this->sumCashByCurrency($entity->accounts);
            $rowsForEntity = $plannedRows->where('owner_entity_id', $entity->id);

            $currencyKeys = collect($cashByCurrency)
                ->keys()
                ->merge($rowsForEntity->pluck('currency')->map(fn (Currency $c) => $c->value))
                ->unique()
                ->values();

            $currencyTotals = [];
            foreach ($currencyKeys as $currencyValue) {
                $currency = Currency::from($currencyValue);
                $cash = (float) ($cashByCurrency[$currencyValue] ?? 0);
                $incoming = (float) $rowsForEntity
                    ->where('currency', $currency)
                    ->where('direction', PlannedTransactionDirection::INCOMING)
                    ->sum(fn (PlannedTransaction $r) => (float) $r->amount);
                $outgoing = (float) $rowsForEntity
                    ->where('currency', $currency)
                    ->where('direction', PlannedTransactionDirection::OUTGOING)
                    ->sum(fn (PlannedTransaction $r) => (float) $r->amount);
                $end = $cash + $incoming - $outgoing;

                $currencyTotals[] = [
                    'currency' => $currencyValue,
                    'symbol' => $currency->symbol(),
                    'cash_now' => $this->money($cash),
                    'incoming' => $this->money($incoming),
                    'outgoing' => $this->money($outgoing),
                    'end_balance' => $this->money($end),
                    'is_covered' => $end >= 0,
                ];
            }

            $primaryCurrency = $this->pickPrimaryCurrency($currencyTotals);

            $payload[$entity->id] = [
                'id' => $entity->id,
                'name' => $entity->name,
                'type' => $entity->type->value,
                'color' => $entity->color->value,
                'is_selected' => $entity->id === $selectedId,
                'currencies' => $currencyTotals,
                'primary_currency' => $primaryCurrency,
                'cash_by_currency' => $cashByCurrency,
                'accounts' => $entity->accounts->map(fn (Account $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'currency' => $a->currency->value,
                    'symbol' => $a->currency->symbol(),
                    'amount' => $this->money((float) $a->amount),
                    'is_main' => $a->is_main,
                ])->all(),
            ];
        }

        return $payload;
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return array<string, float>
     */
    private function sumCashByCurrency(Collection $accounts): array
    {
        $totals = [];

        foreach ($accounts as $account) {
            $code = $account->currency->value;
            $totals[$code] = ($totals[$code] ?? 0.0) + (float) $account->amount;
        }

        return $totals;
    }

    /**
     * @param  array<int, array<string, mixed>>  $currencyTotals
     */
    private function pickPrimaryCurrency(array $currencyTotals): ?string
    {
        if ($currencyTotals === []) {
            return null;
        }

        $best = $currencyTotals[0];
        foreach ($currencyTotals as $row) {
            if ((float) $row['cash_now'] > (float) $best['cash_now']) {
                $best = $row;
            }
        }

        return (string) $best['currency'];
    }

    /**
     * @param  Collection<int, Entity>  $entities
     * @param  Collection<int, PlannedTransaction>  $plannedRows
     * @return array<int, array<string, mixed>>
     */
    private function buildFlows(Collection $entities, Collection $plannedRows): array
    {
        $entityIds = $entities->pluck('id')->all();
        $aggregated = [];

        foreach ($plannedRows as $row) {
            $counterparty = $row->counterparty;
            if (! $counterparty || $counterparty->kind !== CounterpartyKind::INTERNAL) {
                continue;
            }

            $otherEntityId = $counterparty->entity_id;
            if ($otherEntityId === null || ! in_array($otherEntityId, $entityIds, true)) {
                continue;
            }

            if ($row->direction === PlannedTransactionDirection::OUTGOING) {
                $from = (int) $row->owner_entity_id;
                $to = $otherEntityId;
            } else {
                continue;
            }

            $key = sprintf('%d:%d:%s', $from, $to, $row->currency->value);
            $aggregated[$key] = $aggregated[$key] ?? [
                'from_entity_id' => $from,
                'to_entity_id' => $to,
                'currency' => $row->currency->value,
                'symbol' => $row->currency->symbol(),
                'amount' => 0.0,
                'count' => 0,
            ];
            $aggregated[$key]['amount'] += (float) $row->amount;
            $aggregated[$key]['count']++;
        }

        return array_map(fn (array $row) => [
            ...$row,
            'amount' => $this->money($row['amount']),
        ], array_values($aggregated));
    }

    /**
     * @param  Collection<int, PlannedTransaction>  $rows
     * @param  array<string, float>  $cashByCurrency
     * @return array<int, array<string, mixed>>
     */
    private function buildTimeline(Collection $rows, array $cashByCurrency, CarbonImmutable $today): array
    {
        $sorted = $rows->sortBy([
            ['due_date', 'asc'],
            ['id', 'asc'],
        ])->values();

        $running = $cashByCurrency;
        $events = [];

        foreach ($sorted as $row) {
            $code = $row->currency->value;
            $running[$code] = ($running[$code] ?? 0.0) + (
                $row->direction === PlannedTransactionDirection::INCOMING
                    ? (float) $row->amount
                    : -(float) $row->amount
            );

            $counterpartyName = $row->counterparty?->kind === CounterpartyKind::INTERNAL
                && $row->counterparty?->entity
                ? $row->counterparty->entity->name
                : ($row->counterparty?->name ?? '');

            $events[] = [
                'id' => $row->id,
                'date' => $row->due_date?->toDateString(),
                'is_overdue' => $row->status === PlannedTransactionStatus::OVERDUE,
                'is_past' => $row->due_date && $row->due_date->lt($today),
                'label' => $row->purpose ?: $counterpartyName,
                'counterparty' => $counterpartyName,
                'direction' => $row->direction->value,
                'amount' => $this->money((float) $row->amount),
                'currency' => $code,
                'symbol' => $row->currency->symbol(),
                'running_balance' => $this->money($running[$code]),
                'is_mandatory' => $row->is_mandatory,
            ];
        }

        return $events;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, totals: array<int, array<string, mixed>>}
     */
    private function buildUndatedForEntity(int $entityId): array
    {
        $rows = PlannedTransaction::query()
            ->with('counterparty:id,kind,entity_id,name')
            ->where('owner_entity_id', $entityId)
            ->whereNull('due_date')
            ->whereIn('status', [
                PlannedTransactionStatus::PLANNED->value,
                PlannedTransactionStatus::OVERDUE->value,
            ])
            ->orderByDesc('is_mandatory')
            ->orderByDesc('amount')
            ->get();

        $items = [];
        $totals = [];

        foreach ($rows as $row) {
            $counterpartyName = $row->counterparty?->kind === CounterpartyKind::INTERNAL
                && $row->counterparty?->entity
                ? $row->counterparty->entity->name
                : ($row->counterparty?->name ?? '');

            $code = $row->currency->value;
            $totals[$code] = $totals[$code] ?? [
                'currency' => $code,
                'symbol' => $row->currency->symbol(),
                'incoming' => 0.0,
                'outgoing' => 0.0,
                'count' => 0,
            ];

            if ($row->direction === PlannedTransactionDirection::INCOMING) {
                $totals[$code]['incoming'] += (float) $row->amount;
            } else {
                $totals[$code]['outgoing'] += (float) $row->amount;
            }
            $totals[$code]['count']++;

            $items[] = [
                'id' => $row->id,
                'direction' => $row->direction->value,
                'amount' => $this->money((float) $row->amount),
                'currency' => $code,
                'symbol' => $row->currency->symbol(),
                'purpose' => $row->purpose,
                'counterparty' => $counterpartyName,
                'is_mandatory' => $row->is_mandatory,
                'note' => $row->note,
            ];
        }

        $totalsArray = array_map(fn (array $row) => [
            ...$row,
            'incoming' => $this->money($row['incoming']),
            'outgoing' => $this->money($row['outgoing']),
        ], array_values($totals));

        return [
            'items' => $items,
            'totals' => $totalsArray,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function periodPayload(int $days): array
    {
        $from = CarbonImmutable::today();

        return [
            'days' => $days,
            'from' => $from->toDateString(),
            'to' => $from->addDays($days)->toDateString(),
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
