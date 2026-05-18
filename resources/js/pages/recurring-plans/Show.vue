<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Ban, Pause, Pencil, Play, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import RecurringPlanController from '@/actions/App/Http/Controllers/RecurringPlanController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ENTITY_COLOR_SWATCH } from '@/pages/entities/colors';
import AddPhaseDialog from '@/pages/recurring-plans/AddPhaseDialog.vue';
import DeleteRecurringPlanDialog from '@/pages/recurring-plans/DeleteRecurringPlanDialog.vue';
import EditOccurrenceDialog from '@/pages/recurring-plans/EditOccurrenceDialog.vue';
import EndPlanDialog from '@/pages/recurring-plans/EndPlanDialog.vue';
import * as recurringRoutes from '@/routes/recurring-plans';

type Option = { value: string; label: string };

type Phase = {
    id: number;
    amount: string;
    frequency: 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'yearly';
    interval_step: number;
    anchor_day: number | null;
    starts_on: string | null;
    ends_on: string | null;
    occurrence_count: number | null;
    reason: string | null;
    is_current: boolean;
};

type Plan = {
    id: number;
    label: string;
    direction: 'incoming' | 'outgoing';
    currency: string;
    purpose: string | null;
    is_mandatory: boolean;
    status: 'active' | 'paused' | 'ended';
    starts_on: string | null;
    ends_on: string | null;
    note: string | null;
    owner_entity: { id: number; name: string; type: 'personal' | 'llc'; color: string };
    counterparty: { id: number; name: string; kind: 'internal' | 'external' };
    account: { id: number; name: string; currency: string } | null;
    phases: Phase[];
};

type Upcoming = {
    id: number;
    amount: string;
    currency: string;
    due_date: string | null;
    purpose: string | null;
    status: 'planned' | 'settled' | 'overdue' | 'cancelled';
    is_mandatory: boolean;
    note: string | null;
    recorded_count: number;
};

type Projection = {
    total: string;
    occurrences: number;
    starts_on: string;
    ends_on: string;
};

const props = defineProps<{
    plan: Plan;
    upcoming: Upcoming[];
    projection: Projection | null;
    options: {
        frequencies: Option[];
        currencies: { value: string; label: string; symbol: string }[];
        statuses: Option[];
        accounts: { id: number; name: string; currency: string }[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Recurring plans', href: recurringRoutes.index() },
        ],
    },
});

const frequencyLabel: Record<Phase['frequency'], string> = {
    weekly: 'Weekly',
    biweekly: 'Every 2 weeks',
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
};

const statusVariant: Record<Plan['status'], 'default' | 'secondary' | 'outline'> = {
    active: 'default',
    paused: 'secondary',
    ended: 'outline',
};

const rowStatusVariant = {
    planned: 'secondary',
    settled: 'default',
    overdue: 'destructive',
    cancelled: 'outline',
} as const;

function formatAmount(amount: string): string {
    const n = parseFloat(amount);

    if (Number.isNaN(n)) {
        return '0.00';
    }

    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso + 'T00:00:00').toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function cadenceLabel(p: Phase): string {
    const base = frequencyLabel[p.frequency];

    if (p.interval_step > 1) {
        return `Every ${p.interval_step} × ${base.toLowerCase()}`;
    }

    return base;
}

const addPhaseOpen = ref(false);
const endOpen = ref(false);
const deleteOpen = ref(false);
const editingOccurrence = ref<Upcoming | null>(null);
const editOccurrenceOpen = ref(false);

function openEditOccurrence(row: Upcoming): void {
    editingOccurrence.value = row;
    editOccurrenceOpen.value = true;
}

watch(editOccurrenceOpen, (value) => {
    if (!value) {
        editingOccurrence.value = null;
    }
});

const currentPhase = props.plan.phases.find((p) => p.is_current) ?? null;

function pauseOrResume(): void {
    const route =
        props.plan.status === 'paused'
            ? RecurringPlanController.resume(props.plan.id)
            : RecurringPlanController.pause(props.plan.id);

    router.visit(route, {
        method: route.method,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="plan.label" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <a :href="recurringRoutes.index().url" aria-label="Back">
                        <ArrowLeft class="size-4" />
                    </a>
                </Button>
                <Heading
                    :title="plan.label"
                    :description="plan.purpose ?? 'Recurring plan'"
                />
            </div>
            <div class="flex items-center gap-2">
                <Button
                    v-if="plan.status !== 'ended'"
                    variant="outline"
                    size="sm"
                    @click="pauseOrResume"
                >
                    <Play v-if="plan.status === 'paused'" class="size-3.5" />
                    <Pause v-else class="size-3.5" />
                    {{ plan.status === 'paused' ? 'Resume' : 'Pause' }}
                </Button>
                <Button
                    v-if="plan.status !== 'ended'"
                    variant="outline"
                    size="sm"
                    @click="endOpen = true"
                >
                    <Ban class="size-3.5" />
                    End
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    @click="deleteOpen = true"
                >
                    <Trash2 class="size-3.5" />
                    Delete
                </Button>
            </div>
        </div>

        <section class="grid gap-4 rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border md:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Entity</p>
                <div class="mt-1 flex items-center gap-2">
                    <span
                        class="inline-block size-2.5 shrink-0 rounded-full"
                        :class="ENTITY_COLOR_SWATCH[plan.owner_entity.color]"
                    />
                    <span>{{ plan.owner_entity.name }}</span>
                </div>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Counterparty</p>
                <div class="mt-1 flex items-center gap-1.5">
                    <span>{{ plan.counterparty.name }}</span>
                    <Badge
                        v-if="plan.counterparty.kind === 'internal'"
                        variant="outline"
                        class="text-[10px]"
                    >
                        Internal
                    </Badge>
                </div>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Account</p>
                <p class="mt-1">{{ plan.account?.name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Status</p>
                <Badge :variant="statusVariant[plan.status]" class="mt-1 capitalize">
                    {{ plan.status }}
                </Badge>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Direction</p>
                <Badge :variant="plan.direction === 'incoming' ? 'default' : 'secondary'" class="mt-1">
                    {{ plan.direction === 'incoming' ? 'Incoming' : 'Outgoing' }}
                </Badge>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Currency</p>
                <p class="mt-1">{{ plan.currency }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Starts</p>
                <p class="mt-1 font-mono tabular-nums">{{ formatDate(plan.starts_on) }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Ends</p>
                <p class="mt-1 font-mono tabular-nums">{{ formatDate(plan.ends_on) }}</p>
            </div>
        </section>

        <section
            v-if="projection"
            class="rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-background to-muted/40 p-4 dark:border-sidebar-border"
        >
            <p class="text-xs uppercase tracking-wide text-muted-foreground">Projected total</p>
            <p class="mt-2 font-mono text-3xl font-semibold tabular-nums">
                {{ plan.direction === 'incoming' ? '+' : '−' }}{{ formatAmount(projection.total) }}
                <span class="text-lg text-muted-foreground">{{ plan.currency }}</span>
            </p>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ projection.occurrences }} occurrence{{ projection.occurrences === 1 ? '' : 's' }}
                · {{ formatDate(projection.starts_on) }} → {{ formatDate(projection.ends_on) }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Based on each phase's amount across the plan's date range. Per-row tweaks aren't reflected here.
            </p>
        </section>

        <section class="rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border">
            <header class="flex items-center justify-between border-b border-sidebar-border/60 px-4 py-3 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold">Phases</h2>
                <Button
                    v-if="currentPhase && plan.status !== 'ended'"
                    variant="default"
                    size="sm"
                    @click="addPhaseOpen = true"
                >
                    <Pencil class="size-3.5" />
                    Change going forward
                </Button>
            </header>
            <ol class="divide-y divide-sidebar-border/40 dark:divide-sidebar-border">
                <li
                    v-for="phase in plan.phases"
                    :key="phase.id"
                    class="flex items-start justify-between gap-4 px-4 py-3"
                    :class="phase.is_current ? 'bg-muted/30' : ''"
                >
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2 font-mono tabular-nums">
                            <span class="font-medium">
                                {{ plan.direction === 'incoming' ? '+' : '−' }}{{ formatAmount(phase.amount) }}
                                <span class="ml-1 text-xs text-muted-foreground">{{ plan.currency }}</span>
                            </span>
                            <Badge v-if="phase.is_current" variant="default" class="text-[10px]">Current</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ cadenceLabel(phase) }}
                            ·
                            {{ formatDate(phase.starts_on) }} → {{ formatDate(phase.ends_on) || 'ongoing' }}
                            <span v-if="phase.reason"> · {{ phase.reason }}</span>
                        </p>
                    </div>
                </li>
            </ol>
        </section>

        <section class="rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border">
            <header class="flex items-center justify-between border-b border-sidebar-border/60 px-4 py-3 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold">Upcoming occurrences</h2>
                <p class="text-xs text-muted-foreground">Click a row to adjust just that occurrence.</p>
            </header>
            <table class="w-full text-sm">
                <tbody>
                    <tr v-if="upcoming.length === 0">
                        <td colspan="4" class="px-4 py-6 text-center text-muted-foreground">
                            No upcoming occurrences. {{ plan.status === 'active' ? 'Try the daily materializer.' : 'Resume to generate them.' }}
                        </td>
                    </tr>
                    <tr
                        v-for="row in upcoming"
                        :key="row.id"
                        class="cursor-pointer border-t border-sidebar-border/40 hover:bg-muted/40 dark:border-sidebar-border"
                        @click="openEditOccurrence(row)"
                    >
                        <td class="px-4 py-2 font-mono tabular-nums">{{ formatDate(row.due_date) }}</td>
                        <td class="px-4 py-2 text-right font-mono tabular-nums">
                            {{ plan.direction === 'incoming' ? '+' : '−' }}{{ formatAmount(row.amount) }}
                            <span class="ml-1 text-xs text-muted-foreground">{{ plan.currency }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <Badge :variant="rowStatusVariant[row.status]" class="capitalize">
                                {{ row.status }}
                            </Badge>
                            <span
                                v-if="row.recorded_count > 0"
                                class="ml-2 text-xs text-emerald-600 dark:text-emerald-400"
                            >
                                {{ row.recorded_count }}× paid
                            </span>
                        </td>
                        <td class="px-4 py-2 w-px text-right">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-7"
                                aria-label="Edit occurrence"
                                @click.stop="openEditOccurrence(row)"
                            >
                                <Pencil class="size-3.5" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <AddPhaseDialog
            v-model:open="addPhaseOpen"
            :plan-id="plan.id"
            :current-phase="currentPhase"
            :frequencies="options.frequencies"
        />

        <EndPlanDialog
            v-model:open="endOpen"
            :plan-id="plan.id"
        />

        <DeleteRecurringPlanDialog
            v-model:open="deleteOpen"
            :plan-id="plan.id"
            :plan-label="plan.label"
        />

        <EditOccurrenceDialog
            v-if="editingOccurrence"
            :key="editingOccurrence.id"
            v-model:open="editOccurrenceOpen"
            :occurrence="editingOccurrence"
            :statuses="options.statuses"
            :plan-currency="plan.currency"
        />
    </div>
</template>
