<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { ENTITY_COLOR_SWATCH } from '@/pages/entities/colors';
import RecurringPlanDialog from '@/pages/recurring-plans/RecurringPlanDialog.vue';
import * as recurringRoutes from '@/routes/recurring-plans';

type Option = { value: string; label: string };
type EntityOption = {
    id: number;
    name: string;
    type: 'personal' | 'llc';
    color: string;
    accounts: { id: number; name: string; currency: string }[];
};
type ExternalCp = { id: number; name: string };

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
    current_phase: {
        id: number;
        amount: string;
        frequency: 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'yearly';
        interval_step: number;
        anchor_day: number | null;
        starts_on: string | null;
    } | null;
};

const props = defineProps<{
    plans: Plan[];
    options: {
        entities: EntityOption[];
        directions: Option[];
        currencies: { value: string; label: string; symbol: string }[];
        frequencies: Option[];
        statuses: Option[];
        external_counterparties: ExternalCp[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recurring plans', href: recurringRoutes.index() }],
    },
});

const addDialogOpen = ref(false);

const statusVariant: Record<Plan['status'], 'default' | 'secondary' | 'outline'> = {
    active: 'default',
    paused: 'secondary',
    ended: 'outline',
};

const frequencyLabel: Record<Plan['current_phase'] extends infer P ? P extends { frequency: infer F } ? F & string : never : never, string> = {
    weekly: 'Weekly',
    biweekly: 'Every 2 weeks',
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
};

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
</script>

<template>
    <Head title="Recurring plans" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Recurring plans"
                description="Long-lived rules that generate planned transactions on a schedule — rent, loans, subscriptions."
            />
            <RecurringPlanDialog
                v-model:open="addDialogOpen"
                :entities="options.entities"
                :directions="options.directions"
                :currencies="options.currencies"
                :frequencies="options.frequencies"
                :external-counterparties="options.external_counterparties"
            />
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead class="border-b border-sidebar-border/60 text-left text-xs uppercase tracking-wide text-muted-foreground dark:border-sidebar-border">
                    <tr>
                        <th class="px-3 py-2">Label</th>
                        <th class="px-3 py-2">Entity</th>
                        <th class="px-3 py-2">Counterparty</th>
                        <th class="px-3 py-2">Account</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2 text-right">Current amount</th>
                        <th class="px-3 py-2">Cadence</th>
                        <th class="px-3 py-2">Starts</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="plans.length === 0">
                        <td colspan="9" class="px-3 py-10 text-center text-muted-foreground">
                            No recurring plans yet. Create one for something like rent or a subscription.
                        </td>
                    </tr>
                    <tr
                        v-for="plan in plans"
                        :key="plan.id"
                        class="border-t border-sidebar-border/40 hover:bg-muted/40 dark:border-sidebar-border"
                    >
                        <td class="px-3 py-2 font-medium">
                            <Link :href="recurringRoutes.show(plan.id).url" class="hover:underline">
                                {{ plan.label }}
                            </Link>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-block size-2.5 shrink-0 rounded-full"
                                    :class="ENTITY_COLOR_SWATCH[plan.owner_entity.color]"
                                />
                                <span class="truncate">{{ plan.owner_entity.name }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-1.5">
                                <span class="truncate">{{ plan.counterparty.name }}</span>
                                <Badge
                                    v-if="plan.counterparty.kind === 'internal'"
                                    variant="outline"
                                    class="text-[10px]"
                                >
                                    Internal
                                </Badge>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ plan.account?.name ?? '—' }}
                        </td>
                        <td class="px-3 py-2">
                            <Badge :variant="plan.direction === 'incoming' ? 'default' : 'secondary'">
                                {{ plan.direction === 'incoming' ? 'In' : 'Out' }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-right font-mono tabular-nums">
                            <template v-if="plan.current_phase">
                                {{ plan.direction === 'incoming' ? '+' : '−' }}{{ formatAmount(plan.current_phase.amount) }}
                                <span class="ml-1 text-xs text-muted-foreground">{{ plan.currency }}</span>
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ plan.current_phase ? frequencyLabel[plan.current_phase.frequency] : '—' }}
                        </td>
                        <td class="px-3 py-2 font-mono tabular-nums text-muted-foreground">
                            {{ formatDate(plan.starts_on) }}
                        </td>
                        <td class="px-3 py-2">
                            <Badge :variant="statusVariant[plan.status]" class="capitalize">
                                {{ plan.status }}
                            </Badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
