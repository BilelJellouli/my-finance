<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeftRight, ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ENTITY_COLOR_SWATCH } from '@/pages/entities/colors';
import DeleteTransactionDialog from '@/pages/transactions/DeleteTransactionDialog.vue';
import TransactionDialog from '@/pages/transactions/TransactionDialog.vue';
import * as transactionsRoutes from '@/routes/transactions';
import type {
    EntityWithAccounts,
    OpenPlannedRef,
    TransactionListItem,
} from '@/types';

type Option = { value: string; label: string };
type Meta = { current_page: number; last_page: number; per_page: number; total: number };

type Filters = {
    entity_id: number | null;
    kind: string | null;
    account_id: number | null;
    has_planned: string | null;
    from: string | null;
    to: string | null;
};

const props = defineProps<{
    transactions: { data: TransactionListItem[]; meta: Meta };
    filters: Filters;
    options: {
        entities: EntityWithAccounts[];
        kinds: Option[];
        currencies: { value: string; label: string; symbol: string }[];
        external_counterparties: { id: number; name: string }[];
        open_planned: OpenPlannedRef[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Transactions', href: transactionsRoutes.index() }],
    },
});

const ALL = '__all__';

const form = reactive({
    entity_id: props.filters.entity_id ? String(props.filters.entity_id) : ALL,
    kind: props.filters.kind ?? ALL,
    account_id: props.filters.account_id ? String(props.filters.account_id) : ALL,
    has_planned: props.filters.has_planned ?? ALL,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

watch(form, () => {
    router.get(
        transactionsRoutes.index().url,
        {
            entity_id: form.entity_id === ALL ? undefined : form.entity_id,
            kind: form.kind === ALL ? undefined : form.kind,
            account_id: form.account_id === ALL ? undefined : form.account_id,
            has_planned: form.has_planned === ALL ? undefined : form.has_planned,
            from: form.from || undefined,
            to: form.to || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, { deep: true });

const createOpen = ref(false);
const editTarget = ref<TransactionListItem | null>(null);
const editOpen = ref(false);
const deleteTarget = ref<TransactionListItem | null>(null);
const deleteOpen = ref(false);

const accountsForCurrentEntity = computed(() => {
    const flat: { id: number; name: string; currency: string; entity_id: number }[] = [];
    for (const entity of props.options.entities) {
        for (const account of entity.accounts) {
            flat.push({
                id: account.id,
                name: account.name,
                currency: account.currency,
                entity_id: entity.id,
            });
        }
    }
    if (form.entity_id === ALL) {
        return flat;
    }
    const entityId = Number(form.entity_id);
    return flat.filter((a) => a.entity_id === entityId);
});

function openCreate(): void {
    editTarget.value = null;
    createOpen.value = true;
}

function openEdit(t: TransactionListItem): void {
    editTarget.value = t;
    editOpen.value = true;
}

function openDelete(t: TransactionListItem): void {
    deleteTarget.value = t;
    deleteOpen.value = true;
}

function goToPage(page: number): void {
    router.get(
        transactionsRoutes.index().url,
        {
            entity_id: form.entity_id === ALL ? undefined : form.entity_id,
            kind: form.kind === ALL ? undefined : form.kind,
            account_id: form.account_id === ALL ? undefined : form.account_id,
            has_planned: form.has_planned === ALL ? undefined : form.has_planned,
            from: form.from || undefined,
            to: form.to || undefined,
            page,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function formatAmount(amount: string): string {
    const n = parseFloat(amount);
    if (Number.isNaN(n)) {
        return '0.00';
    }
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(iso: string): string {
    return new Date(iso + 'T00:00:00').toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function kindLabel(value: string): string {
    return props.options.kinds.find((k) => k.value === value)?.label ?? value;
}
</script>

<template>
    <Head title="Transactions" />

    <div class="space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <Heading title="Transactions" description="Real money movements across your accounts — settle planned items or log standalone moves." />
            <Button @click="openCreate">
                <Plus class="size-4" /> Record transaction
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="grid gap-1.5">
                <Label class="text-xs">Entity</Label>
                <Select v-model="form.entity_id">
                    <SelectTrigger>
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All entities</SelectItem>
                        <SelectItem
                            v-for="e in options.entities"
                            :key="e.id"
                            :value="String(e.id)"
                        >
                            {{ e.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Kind</Label>
                <Select v-model="form.kind">
                    <SelectTrigger>
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All kinds</SelectItem>
                        <SelectItem
                            v-for="k in options.kinds"
                            :key="k.value"
                            :value="k.value"
                        >
                            {{ k.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Account</Label>
                <Select v-model="form.account_id">
                    <SelectTrigger>
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All accounts</SelectItem>
                        <SelectItem
                            v-for="a in accountsForCurrentEntity"
                            :key="a.id"
                            :value="String(a.id)"
                        >
                            {{ a.name }} ({{ a.currency }})
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Planned link</Label>
                <Select v-model="form.has_planned">
                    <SelectTrigger>
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All</SelectItem>
                        <SelectItem value="yes">Linked</SelectItem>
                        <SelectItem value="no">Standalone</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">From date</Label>
                <Input v-model="form.from" type="date" />
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">To date</Label>
                <Input v-model="form.to" type="date" />
            </div>
        </div>

        <div class="overflow-hidden rounded-md border border-sidebar-border/60 dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead class="bg-muted/40 text-left uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">From</th>
                        <th class="px-3 py-2">To</th>
                        <th class="px-3 py-2">Kind</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                        <th class="px-3 py-2">Planned</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="transactions.data.length === 0">
                        <td colspan="7" class="px-3 py-8 text-center text-muted-foreground">
                            <ArrowLeftRight class="mx-auto mb-2 size-5 opacity-60" />
                            No transactions yet.
                        </td>
                    </tr>
                    <tr
                        v-for="t in transactions.data"
                        :key="t.id"
                        class="border-t border-sidebar-border/40 dark:border-sidebar-border"
                    >
                        <td class="px-3 py-2 font-mono tabular-nums">{{ formatDate(t.occurred_on) }}</td>
                        <td class="px-3 py-2">
                            <span v-if="t.from_account" class="flex items-center gap-1.5">
                                <span
                                    class="inline-block size-2 shrink-0 rounded-full"
                                    :class="t.from_account.entity ? (ENTITY_COLOR_SWATCH[t.from_account.entity.color] ?? 'bg-slate-300') : 'bg-slate-300'"
                                />
                                <span class="text-muted-foreground">{{ t.from_account.entity?.name }} —</span>
                                <span>{{ t.from_account.name }}</span>
                            </span>
                            <span v-else-if="t.counterparty" class="text-muted-foreground">
                                {{ t.counterparty.name }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-3 py-2">
                            <span v-if="t.to_account" class="flex items-center gap-1.5">
                                <span
                                    class="inline-block size-2 shrink-0 rounded-full"
                                    :class="t.to_account.entity ? (ENTITY_COLOR_SWATCH[t.to_account.entity.color] ?? 'bg-slate-300') : 'bg-slate-300'"
                                />
                                <span class="text-muted-foreground">{{ t.to_account.entity?.name }} —</span>
                                <span>{{ t.to_account.name }}</span>
                            </span>
                            <span v-else-if="t.counterparty" class="text-muted-foreground">
                                {{ t.counterparty.name }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-3 py-2">
                            <Badge variant="outline">{{ kindLabel(t.kind) }}</Badge>
                        </td>
                        <td class="px-3 py-2 text-right font-mono tabular-nums">
                            {{ formatAmount(t.amount) }} <span class="text-xs text-muted-foreground">{{ t.currency }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <Badge v-if="t.planned_transaction" variant="secondary" class="text-[10px]">
                                {{ t.planned_transaction.purpose ?? 'planned' }}
                            </Badge>
                            <span v-else class="text-xs text-muted-foreground">—</span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon" @click="openEdit(t)">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button variant="ghost" size="icon" @click="openDelete(t)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="transactions.meta.last_page > 1" class="flex items-center justify-end gap-2">
            <Button
                variant="outline"
                size="icon"
                :disabled="transactions.meta.current_page === 1"
                @click="goToPage(transactions.meta.current_page - 1)"
            >
                <ChevronLeft class="size-4" />
            </Button>
            <span class="text-sm text-muted-foreground">
                Page {{ transactions.meta.current_page }} of {{ transactions.meta.last_page }}
            </span>
            <Button
                variant="outline"
                size="icon"
                :disabled="transactions.meta.current_page === transactions.meta.last_page"
                @click="goToPage(transactions.meta.current_page + 1)"
            >
                <ChevronRight class="size-4" />
            </Button>
        </div>

        <TransactionDialog
            v-model:open="createOpen"
            :entities="options.entities"
            :kinds="options.kinds"
            :currencies="options.currencies"
            :external-counterparties="options.external_counterparties"
            :open-planned="options.open_planned"
        />

        <TransactionDialog
            v-if="editTarget"
            :key="`edit-${editTarget.id}`"
            v-model:open="editOpen"
            :entities="options.entities"
            :kinds="options.kinds"
            :currencies="options.currencies"
            :external-counterparties="options.external_counterparties"
            :open-planned="options.open_planned"
            :transaction="editTarget"
        />

        <DeleteTransactionDialog
            v-if="deleteTarget"
            :key="`delete-${deleteTarget.id}`"
            v-model:open="deleteOpen"
            :transaction="deleteTarget"
        />
    </div>
</template>
