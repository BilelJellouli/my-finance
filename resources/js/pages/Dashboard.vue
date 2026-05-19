<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowUpRight, CalendarDays, CalendarOff, CheckCircle2, AlertTriangle, Circle, Star } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, nextTick, watch } from 'vue';
import { dashboard } from '@/routes';
import { ENTITY_COLOR_SWATCH, ENTITY_COLOR_RING } from '@/pages/entities/colors';

type Account = {
    id: number;
    name: string;
    currency: string;
    symbol: string;
    amount: string;
    is_main: boolean;
};

type CurrencyTotals = {
    currency: string;
    symbol: string;
    cash_now: string;
    incoming: string;
    outgoing: string;
    end_balance: string;
    is_covered: boolean;
};

type Entity = {
    id: number;
    name: string;
    type: 'personal' | 'llc';
    color: string;
    is_selected: boolean;
    currencies: CurrencyTotals[];
    primary_currency: string | null;
    accounts: Account[];
};

type Flow = {
    from_entity_id: number;
    to_entity_id: number;
    currency: string;
    symbol: string;
    amount: string;
    count: number;
};

type TimelineEvent = {
    id: number;
    date: string | null;
    is_overdue: boolean;
    is_past: boolean;
    label: string;
    counterparty: string;
    direction: 'incoming' | 'outgoing';
    amount: string;
    currency: string;
    symbol: string;
    running_balance: string;
    is_mandatory: boolean;
};

type UndatedItem = {
    id: number;
    direction: 'incoming' | 'outgoing';
    amount: string;
    currency: string;
    symbol: string;
    purpose: string | null;
    counterparty: string;
    is_mandatory: boolean;
    note: string | null;
};

type UndatedTotals = {
    currency: string;
    symbol: string;
    incoming: string;
    outgoing: string;
    count: number;
};

const props = defineProps<{
    period: { days: number; from: string; to: string };
    period_options: number[];
    entities: Entity[];
    flows: Flow[];
    timeline: TimelineEvent[];
    undated: { items: UndatedItem[]; totals: UndatedTotals[] };
    selected_entity_id: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard().url }],
    },
});

function n(value: string | number): number {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return Number.isFinite(num) ? num : 0;
}

function fmt(value: string | number): string {
    return n(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function fmtCompact(value: string | number): string {
    const abs = Math.abs(n(value));
    if (abs >= 1000) {
        return n(value).toLocaleString(undefined, { maximumFractionDigits: 1 });
    }
    return fmt(value);
}

function fmtDate(date: string | null): string {
    if (!date) {
        return '—';
    }
    return new Date(date + 'T00:00:00').toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

const selectedEntity = computed(() =>
    props.entities.find((e) => e.id === props.selected_entity_id) ?? null,
);

const otherEntities = computed(() =>
    props.entities.filter((e) => e.id !== props.selected_entity_id),
);

const flowsForSelected = computed(() => {
    if (!selectedEntity.value) {
        return [];
    }
    return props.flows.filter(
        (f) =>
            f.from_entity_id === selectedEntity.value!.id ||
            f.to_entity_id === selectedEntity.value!.id,
    );
});

function flowForOrbit(orbitId: number): { incoming: Flow[]; outgoing: Flow[] } {
    const incoming: Flow[] = [];
    const outgoing: Flow[] = [];
    for (const f of flowsForSelected.value) {
        if (f.from_entity_id === orbitId && f.to_entity_id === props.selected_entity_id) {
            incoming.push(f);
        } else if (f.from_entity_id === props.selected_entity_id && f.to_entity_id === orbitId) {
            outgoing.push(f);
        }
    }
    return { incoming, outgoing };
}

function selectEntity(id: number): void {
    router.get(
        dashboard().url,
        { entity_id: id, period_days: props.period.days },
        { preserveScroll: true, preserveState: false },
    );
}

function changePeriod(event: Event): void {
    const days = (event.target as HTMLSelectElement).value;
    router.get(
        dashboard().url,
        { entity_id: props.selected_entity_id ?? undefined, period_days: days },
        { preserveScroll: true, preserveState: false },
    );
}

// Arrow overlay computation
const stageRef = ref<HTMLElement | null>(null);
const centerRef = ref<HTMLElement | null>(null);
const orbitRefs = ref<Map<number, HTMLElement>>(new Map());

type ArrowGeometry = {
    key: string;
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    label: string;
    direction: 'in' | 'out';
};

const arrows = ref<ArrowGeometry[]>([]);
const svgSize = ref<{ w: number; h: number }>({ w: 0, h: 0 });

function setOrbitRef(id: number, el: Element | null): void {
    if (el instanceof HTMLElement) {
        orbitRefs.value.set(id, el);
    } else {
        orbitRefs.value.delete(id);
    }
}

function recomputeArrows(): void {
    const stage = stageRef.value;
    const center = centerRef.value;
    if (!stage || !center) {
        arrows.value = [];
        return;
    }
    const stageBox = stage.getBoundingClientRect();
    svgSize.value = { w: stageBox.width, h: stageBox.height };

    const centerBox = center.getBoundingClientRect();
    const cx = centerBox.left + centerBox.width / 2 - stageBox.left;
    const cy = centerBox.top + centerBox.height / 2 - stageBox.top;

    const next: ArrowGeometry[] = [];

    for (const orbit of otherEntities.value) {
        const el = orbitRefs.value.get(orbit.id);
        if (!el) {
            continue;
        }
        const box = el.getBoundingClientRect();
        const ox = box.left + box.width / 2 - stageBox.left;
        const oy = box.top + box.height / 2 - stageBox.top;

        const { incoming, outgoing } = flowForOrbit(orbit.id);

        if (incoming.length > 0) {
            const total = incoming.reduce((acc, f) => acc + n(f.amount), 0);
            const symbol = incoming[0].symbol;
            next.push({
                key: `in-${orbit.id}`,
                x1: ox,
                y1: oy,
                x2: cx,
                y2: cy,
                label: `${symbol}${fmtCompact(total)}`,
                direction: 'in',
            });
        }
        if (outgoing.length > 0) {
            const total = outgoing.reduce((acc, f) => acc + n(f.amount), 0);
            const symbol = outgoing[0].symbol;
            next.push({
                key: `out-${orbit.id}`,
                x1: cx,
                y1: cy,
                x2: ox,
                y2: oy,
                label: `${symbol}${fmtCompact(total)}`,
                direction: 'out',
            });
        }
    }

    arrows.value = next;
}

let resizeObserver: ResizeObserver | null = null;

onMounted(async () => {
    await nextTick();
    recomputeArrows();
    if (typeof ResizeObserver !== 'undefined' && stageRef.value) {
        resizeObserver = new ResizeObserver(() => recomputeArrows());
        resizeObserver.observe(stageRef.value);
    }
    window.addEventListener('resize', recomputeArrows);
});

onBeforeUnmount(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
    }
    window.removeEventListener('resize', recomputeArrows);
});

watch(
    () => [props.selected_entity_id, props.entities, props.flows],
    async () => {
        await nextTick();
        recomputeArrows();
    },
    { deep: true },
);

// Timeline week/month markers
const timelineWeekMarkers = computed(() => {
    if (props.period.days <= 0) {
        return [];
    }
    const start = new Date(props.period.from + 'T00:00:00');
    const stepDays = props.period.days <= 60 ? 7 : 30;
    const markers: { offset: number; label: string }[] = [];
    for (let d = 0; d <= props.period.days; d += stepDays) {
        const date = new Date(start.getTime());
        date.setDate(start.getDate() + d);
        markers.push({
            offset: (d / props.period.days) * 100,
            label: date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }),
        });
    }
    return markers;
});

const timelineEventOffsets = computed(() => {
    const start = new Date(props.period.from + 'T00:00:00').getTime();
    const span = props.period.days * 86400000;
    return props.timeline.map((ev) => {
        const t = ev.date ? new Date(ev.date + 'T00:00:00').getTime() : start;
        const offset = Math.max(0, Math.min(100, ((t - start) / span) * 100));
        return { ev, offset };
    });
});

const periodLabel = computed(() => `${props.period.days} days`);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header: entity chips + period selector -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="entity in entities"
                    :key="entity.id"
                    type="button"
                    @click="selectEntity(entity.id)"
                    :class="[
                        'flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition',
                        entity.is_selected
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-sidebar-border/70 bg-background hover:border-foreground/40 dark:border-sidebar-border',
                    ]"
                >
                    <span
                        class="inline-block size-2.5 rounded-full"
                        :class="ENTITY_COLOR_SWATCH[entity.color]"
                    />
                    <span class="truncate">{{ entity.name }}</span>
                </button>
            </div>

            <label class="flex items-center gap-2 text-sm text-muted-foreground">
                <CalendarDays class="size-4" />
                <span>Period</span>
                <select
                    :value="period.days"
                    @change="changePeriod"
                    class="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                >
                    <option v-for="opt in period_options" :key="opt" :value="opt">
                        {{ opt }} days
                    </option>
                </select>
            </label>
        </div>

        <div
            v-if="entities.length === 0"
            class="rounded-xl border border-dashed border-sidebar-border/70 p-12 text-center text-muted-foreground dark:border-sidebar-border"
        >
            No entities yet. Create one to start projecting your finances.
        </div>

        <template v-else-if="selectedEntity">
            <!-- Stage: orbits + center + arrows -->
            <div
                ref="stageRef"
                class="relative grid gap-4 rounded-xl border border-sidebar-border/70 bg-background/40 p-4 dark:border-sidebar-border lg:grid-cols-[1fr_2fr_1fr] lg:gap-6 lg:p-8"
            >
                <!-- Left orbit column -->
                <div class="flex flex-col gap-3 lg:order-1">
                    <div
                        v-for="orbit in otherEntities.filter((_, i) => i % 2 === 0)"
                        :key="orbit.id"
                        :ref="(el) => setOrbitRef(orbit.id, el as Element | null)"
                        class="relative z-10"
                    >
                        <button
                            type="button"
                            class="group block w-full rounded-lg border border-sidebar-border/70 bg-background p-3 text-left transition hover:border-foreground/40 dark:border-sidebar-border"
                            @click="selectEntity(orbit.id)"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-block size-2.5 rounded-full"
                                    :class="ENTITY_COLOR_SWATCH[orbit.color]"
                                />
                                <span class="truncate text-sm font-medium">{{ orbit.name }}</span>
                            </div>
                            <div v-if="orbit.currencies.length > 0" class="mt-2 space-y-1">
                                <div
                                    v-for="row in orbit.currencies.slice(0, 1)"
                                    :key="row.currency"
                                    class="space-y-0.5"
                                >
                                    <div class="font-mono text-lg tabular-nums">
                                        {{ row.symbol }}{{ fmtCompact(row.cash_now) }}
                                    </div>
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="flex items-center gap-0.5 text-emerald-600 dark:text-emerald-400">
                                            <ArrowDownRight class="size-3" />+{{ fmtCompact(row.incoming) }}
                                        </span>
                                        <span class="flex items-center gap-0.5 text-rose-600 dark:text-rose-400">
                                            <ArrowUpRight class="size-3" />−{{ fmtCompact(row.outgoing) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1 text-xs">
                                        <CheckCircle2
                                            v-if="row.is_covered"
                                            class="size-3 text-emerald-600 dark:text-emerald-400"
                                        />
                                        <AlertTriangle v-else class="size-3 text-amber-500" />
                                        <span class="text-muted-foreground">
                                            end: {{ row.symbol }}{{ fmtCompact(row.end_balance) }}
                                        </span>
                                    </div>
                                </div>
                                <div
                                    v-if="orbit.currencies.length > 1"
                                    class="text-[10px] uppercase tracking-wide text-muted-foreground"
                                >
                                    + {{ orbit.currencies.length - 1 }} more currency
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Center: focused entity -->
                <div class="flex items-center justify-center lg:order-2">
                    <div
                        ref="centerRef"
                        :class="[
                            'relative z-10 w-full max-w-lg rounded-xl border-2 bg-background p-5 shadow-sm ring-2',
                            ENTITY_COLOR_RING[selectedEntity.color],
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <Star class="size-4 fill-amber-400 text-amber-400" />
                                <span class="text-xs uppercase tracking-wider text-muted-foreground">Focused</span>
                            </div>
                            <span
                                class="inline-block size-3 rounded-full"
                                :class="ENTITY_COLOR_SWATCH[selectedEntity.color]"
                            />
                        </div>

                        <h2 class="mt-1 text-xl font-semibold">{{ selectedEntity.name }}</h2>
                        <div class="text-xs uppercase tracking-wide text-muted-foreground">
                            {{ selectedEntity.type === 'personal' ? 'Personal' : 'LLC' }}
                        </div>

                        <div
                            v-for="row in selectedEntity.currencies"
                            :key="row.currency"
                            class="mt-4 space-y-2 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border"
                        >
                            <div class="flex items-baseline justify-between">
                                <span class="text-xs uppercase tracking-wide text-muted-foreground">Cash now</span>
                                <span class="font-mono text-2xl tabular-nums">
                                    {{ row.symbol }}{{ fmt(row.cash_now) }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-md bg-emerald-50 p-2 dark:bg-emerald-950/40">
                                    <div class="flex items-center gap-1 text-xs text-emerald-700 dark:text-emerald-400">
                                        <ArrowDownRight class="size-3" />
                                        Incoming
                                    </div>
                                    <div class="font-mono text-base tabular-nums text-emerald-700 dark:text-emerald-400">
                                        +{{ row.symbol }}{{ fmt(row.incoming) }}
                                    </div>
                                </div>
                                <div class="rounded-md bg-rose-50 p-2 dark:bg-rose-950/40">
                                    <div class="flex items-center gap-1 text-xs text-rose-700 dark:text-rose-400">
                                        <ArrowUpRight class="size-3" />
                                        Outgoing
                                    </div>
                                    <div class="font-mono text-base tabular-nums text-rose-700 dark:text-rose-400">
                                        −{{ row.symbol }}{{ fmt(row.outgoing) }}
                                    </div>
                                </div>
                            </div>

                            <div
                                :class="[
                                    'flex items-center justify-between rounded-md border p-2',
                                    row.is_covered
                                        ? 'border-emerald-300 bg-emerald-50/60 dark:border-emerald-800 dark:bg-emerald-950/30'
                                        : 'border-amber-300 bg-amber-50/60 dark:border-amber-800 dark:bg-amber-950/30',
                                ]"
                            >
                                <div class="flex items-center gap-2 text-sm">
                                    <CheckCircle2
                                        v-if="row.is_covered"
                                        class="size-4 text-emerald-600 dark:text-emerald-400"
                                    />
                                    <AlertTriangle v-else class="size-4 text-amber-500" />
                                    <span class="font-medium">End of {{ periodLabel }}</span>
                                </div>
                                <span class="font-mono text-lg tabular-nums">
                                    {{ row.symbol }}{{ fmt(row.end_balance) }}
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="undated.items.length > 0"
                            class="mt-4 rounded-md border border-dashed border-amber-300 bg-amber-50/40 p-3 dark:border-amber-800 dark:bg-amber-950/20"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <CalendarOff class="size-4 text-amber-600 dark:text-amber-400" />
                                    <span class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">
                                        No date yet
                                    </span>
                                </div>
                                <span class="text-[10px] uppercase tracking-wider text-muted-foreground">
                                    not in projection
                                </span>
                            </div>

                            <div
                                v-for="row in undated.totals"
                                :key="row.currency"
                                class="mt-2 flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground">{{ row.count }} pending {{ row.count === 1 ? 'item' : 'items' }}</span>
                                <div class="flex items-center gap-3 font-mono tabular-nums">
                                    <span
                                        v-if="n(row.incoming) > 0"
                                        class="flex items-center gap-0.5 text-emerald-700 dark:text-emerald-400"
                                    >
                                        <ArrowDownRight class="size-3" />+{{ row.symbol }}{{ fmtCompact(row.incoming) }}
                                    </span>
                                    <span
                                        v-if="n(row.outgoing) > 0"
                                        class="flex items-center gap-0.5 text-rose-700 dark:text-rose-400"
                                    >
                                        <ArrowUpRight class="size-3" />−{{ row.symbol }}{{ fmtCompact(row.outgoing) }}
                                    </span>
                                </div>
                            </div>

                            <ul class="mt-3 space-y-1.5 border-t border-amber-300/50 pt-2 dark:border-amber-800/50">
                                <li
                                    v-for="item in undated.items"
                                    :key="item.id"
                                    class="flex items-start justify-between gap-2 text-xs"
                                >
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <span
                                            :class="[
                                                'inline-block size-1.5 shrink-0 rounded-full',
                                                item.direction === 'incoming' ? 'bg-emerald-500' : 'bg-rose-500',
                                            ]"
                                        />
                                        <span class="truncate">
                                            {{ item.purpose || item.counterparty || '—' }}
                                        </span>
                                        <span
                                            v-if="item.purpose && item.counterparty"
                                            class="truncate text-muted-foreground"
                                        >
                                            · {{ item.counterparty }}
                                        </span>
                                        <span
                                            v-if="!item.is_mandatory"
                                            class="rounded bg-muted px-1 py-0.5 text-[9px] uppercase tracking-wider text-muted-foreground"
                                        >
                                            flex
                                        </span>
                                    </div>
                                    <span
                                        :class="[
                                            'shrink-0 font-mono tabular-nums',
                                            item.direction === 'incoming'
                                                ? 'text-emerald-700 dark:text-emerald-400'
                                                : 'text-rose-700 dark:text-rose-400',
                                        ]"
                                    >
                                        {{ item.direction === 'incoming' ? '+' : '−' }}{{ item.symbol }}{{ fmtCompact(item.amount) }}
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div
                            v-if="selectedEntity.accounts.length > 0"
                            class="mt-4 border-t border-sidebar-border/70 pt-3 dark:border-sidebar-border"
                        >
                            <div class="mb-2 text-xs uppercase tracking-wide text-muted-foreground">Accounts</div>
                            <ul class="space-y-1.5">
                                <li
                                    v-for="account in selectedEntity.accounts"
                                    :key="account.id"
                                    class="flex items-center justify-between gap-2 text-sm"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <Star
                                            v-if="account.is_main"
                                            class="size-3.5 shrink-0 fill-amber-400 text-amber-400"
                                        />
                                        <Circle v-else class="size-3.5 shrink-0 text-muted-foreground/40" />
                                        <span class="truncate">{{ account.name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono tabular-nums">{{ fmt(account.amount) }}</span>
                                        <span class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground">
                                            {{ account.currency }}
                                        </span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Right orbit column -->
                <div class="flex flex-col gap-3 lg:order-3">
                    <div
                        v-for="orbit in otherEntities.filter((_, i) => i % 2 === 1)"
                        :key="orbit.id"
                        :ref="(el) => setOrbitRef(orbit.id, el as Element | null)"
                        class="relative z-10"
                    >
                        <button
                            type="button"
                            class="group block w-full rounded-lg border border-sidebar-border/70 bg-background p-3 text-left transition hover:border-foreground/40 dark:border-sidebar-border"
                            @click="selectEntity(orbit.id)"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-block size-2.5 rounded-full"
                                    :class="ENTITY_COLOR_SWATCH[orbit.color]"
                                />
                                <span class="truncate text-sm font-medium">{{ orbit.name }}</span>
                            </div>
                            <div v-if="orbit.currencies.length > 0" class="mt-2 space-y-1">
                                <div
                                    v-for="row in orbit.currencies.slice(0, 1)"
                                    :key="row.currency"
                                    class="space-y-0.5"
                                >
                                    <div class="font-mono text-lg tabular-nums">
                                        {{ row.symbol }}{{ fmtCompact(row.cash_now) }}
                                    </div>
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="flex items-center gap-0.5 text-emerald-600 dark:text-emerald-400">
                                            <ArrowDownRight class="size-3" />+{{ fmtCompact(row.incoming) }}
                                        </span>
                                        <span class="flex items-center gap-0.5 text-rose-600 dark:text-rose-400">
                                            <ArrowUpRight class="size-3" />−{{ fmtCompact(row.outgoing) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1 text-xs">
                                        <CheckCircle2
                                            v-if="row.is_covered"
                                            class="size-3 text-emerald-600 dark:text-emerald-400"
                                        />
                                        <AlertTriangle v-else class="size-3 text-amber-500" />
                                        <span class="text-muted-foreground">
                                            end: {{ row.symbol }}{{ fmtCompact(row.end_balance) }}
                                        </span>
                                    </div>
                                </div>
                                <div
                                    v-if="orbit.currencies.length > 1"
                                    class="text-[10px] uppercase tracking-wide text-muted-foreground"
                                >
                                    + {{ orbit.currencies.length - 1 }} more currency
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- SVG arrows overlay -->
                <svg
                    v-if="arrows.length > 0"
                    :width="svgSize.w"
                    :height="svgSize.h"
                    class="pointer-events-none absolute inset-0 z-0 hidden lg:block"
                    aria-hidden="true"
                >
                    <defs>
                        <marker id="arrow-in" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto">
                            <path d="M0,0 L10,5 L0,10 Z" class="fill-emerald-500" />
                        </marker>
                        <marker id="arrow-out" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto">
                            <path d="M0,0 L10,5 L0,10 Z" class="fill-rose-500" />
                        </marker>
                    </defs>
                    <g v-for="arrow in arrows" :key="arrow.key">
                        <line
                            :x1="arrow.x1"
                            :y1="arrow.y1"
                            :x2="arrow.x2"
                            :y2="arrow.y2"
                            stroke-width="2"
                            stroke-dasharray="4 4"
                            :class="arrow.direction === 'in' ? 'stroke-emerald-500/70' : 'stroke-rose-500/70'"
                            :marker-end="arrow.direction === 'in' ? 'url(#arrow-in)' : 'url(#arrow-out)'"
                        />
                        <g :transform="`translate(${(arrow.x1 + arrow.x2) / 2}, ${(arrow.y1 + arrow.y2) / 2})`">
                            <rect
                                x="-30"
                                y="-10"
                                width="60"
                                height="20"
                                rx="10"
                                :class="
                                    arrow.direction === 'in'
                                        ? 'fill-emerald-50 stroke-emerald-300 dark:fill-emerald-950 dark:stroke-emerald-800'
                                        : 'fill-rose-50 stroke-rose-300 dark:fill-rose-950 dark:stroke-rose-800'
                                "
                                stroke-width="1"
                            />
                            <text
                                text-anchor="middle"
                                dominant-baseline="central"
                                :class="[
                                    'font-mono text-[11px]',
                                    arrow.direction === 'in'
                                        ? 'fill-emerald-700 dark:fill-emerald-400'
                                        : 'fill-rose-700 dark:fill-rose-400',
                                ]"
                            >
                                {{ arrow.label }}
                            </text>
                        </g>
                    </g>
                </svg>
            </div>

            <!-- Timeline -->
            <div class="rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Timeline — {{ selectedEntity.name }}</h3>
                    <span class="text-xs text-muted-foreground">
                        {{ fmtDate(period.from) }} → {{ fmtDate(period.to) }}
                    </span>
                </div>

                <div class="relative h-32">
                    <div class="absolute left-0 right-0 top-1/2 h-px bg-sidebar-border/70 dark:bg-sidebar-border" />

                    <div
                        v-for="(marker, i) in timelineWeekMarkers"
                        :key="`m-${i}`"
                        class="absolute top-1/2 -translate-y-1/2"
                        :style="{ left: `${marker.offset}%` }"
                    >
                        <div class="-translate-x-1/2">
                            <div class="mx-auto h-2 w-px bg-sidebar-border dark:bg-sidebar-border" />
                            <div class="mt-1 whitespace-nowrap text-[10px] text-muted-foreground">
                                {{ marker.label }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-for="(item, i) in timelineEventOffsets"
                        :key="`ev-${item.ev.id}-${i}`"
                        class="absolute -translate-x-1/2"
                        :style="{
                            left: `${item.offset}%`,
                            top: item.ev.direction === 'incoming' ? '6%' : '54%',
                        }"
                    >
                        <div class="group relative flex flex-col items-center">
                            <div
                                :class="[
                                    'size-3 rounded-full ring-2 ring-background',
                                    item.ev.direction === 'incoming'
                                        ? 'bg-emerald-500'
                                        : item.ev.is_overdue
                                            ? 'bg-amber-500'
                                            : 'bg-rose-500',
                                ]"
                            />
                            <div
                                :class="[
                                    'mt-1 max-w-[110px] truncate text-center text-[10px]',
                                    item.ev.direction === 'incoming'
                                        ? 'text-emerald-700 dark:text-emerald-400'
                                        : 'text-rose-700 dark:text-rose-400',
                                ]"
                            >
                                {{ item.ev.direction === 'incoming' ? '+' : '−' }}{{ item.ev.symbol }}{{ fmtCompact(item.ev.amount) }}
                            </div>

                            <div class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden -translate-x-1/2 group-hover:block">
                                <div class="whitespace-nowrap rounded-md border border-sidebar-border/70 bg-popover px-2 py-1.5 text-xs shadow-md dark:border-sidebar-border">
                                    <div class="font-medium">{{ item.ev.label || item.ev.counterparty }}</div>
                                    <div class="text-muted-foreground">
                                        {{ fmtDate(item.ev.date) }} · running {{ item.ev.symbol }}{{ fmt(item.ev.running_balance) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="absolute top-0 bottom-0 w-px bg-foreground/60" :style="{ left: '0%' }">
                        <div class="absolute top-0 left-0 -translate-x-1/2 rounded-sm bg-foreground px-1 py-0.5 text-[9px] uppercase tracking-wider text-background">
                            now
                        </div>
                    </div>
                </div>

                <div v-if="timeline.length === 0" class="mt-2 text-center text-xs text-muted-foreground">
                    No planned events in this window.
                </div>
            </div>

            <div class="flex justify-end">
                <Link
                    :href="`/planned-transactions?entity_id=${selectedEntity.id}`"
                    class="text-xs text-muted-foreground underline-offset-4 hover:underline"
                >
                    See all planned transactions →
                </Link>
            </div>
        </template>
    </div>
</template>
