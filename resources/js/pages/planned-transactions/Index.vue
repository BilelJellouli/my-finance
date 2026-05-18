<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-vue-next';
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
import DeletePlannedTransactionDialog from '@/pages/planned-transactions/DeletePlannedTransactionDialog.vue';
import PlannedTransactionDialog from '@/pages/planned-transactions/PlannedTransactionDialog.vue';
import * as plannedRoutes from '@/routes/planned-transactions';

type Option = { value: string; label: string };
type EntityOption = { id: number; name: string; type: 'personal' | 'llc'; color: string };

type Transaction = {
    id: number;
    direction: 'incoming' | 'outgoing';
    amount: string;
    currency: string;
    due_date: string | null;
    purpose: string | null;
    status: 'planned' | 'settled' | 'overdue' | 'cancelled';
    is_mandatory: boolean;
    note: string | null;
    transfer_group_id: string | null;
    owner_entity: EntityOption;
    counterparty: { id: number; name: string; kind: 'internal' | 'external' };
};

type Meta = { current_page: number; last_page: number; per_page: number; total: number };

type Filters = {
    direction: string | null;
    entity_id: number | null;
    status: string | null;
    purpose: string | null;
    mandatory: boolean | null;
    due_from: string | null;
    due_to: string | null;
    sort: string;
    dir: 'asc' | 'desc';
};

const props = defineProps<{
    transactions: { data: Transaction[]; meta: Meta };
    filters: Filters;
    options: {
        entities: EntityOption[];
        directions: Option[];
        statuses: Option[];
        currencies: { value: string; label: string; symbol: string }[];
        purposes: string[];
        external_counterparties: { id: number; name: string }[];
        sortable: string[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Planned transactions', href: plannedRoutes.index() }],
    },
});

const ALL = '__all__';

const form = reactive({
    direction: props.filters.direction ?? ALL,
    entity_id: props.filters.entity_id ? String(props.filters.entity_id) : ALL,
    status: props.filters.status ?? ALL,
    purpose: props.filters.purpose ?? ALL,
    mandatory:
        props.filters.mandatory === true ? 'yes' : props.filters.mandatory === false ? 'no' : ALL,
    due_from: props.filters.due_from ?? '',
    due_to: props.filters.due_to ?? '',
});

const currencySymbol = computed(() => {
    const map: Record<string, string> = {};

    for (const c of props.options.currencies) {
map[c.value] = c.symbol;
}

    return map;
});

function buildQuery() {
    const query: Record<string, string | number> = {};

    if (form.direction !== ALL) {
query.direction = form.direction;
}

    if (form.entity_id !== ALL) {
query.entity_id = Number(form.entity_id);
}

    if (form.status !== ALL) {
query.status = form.status;
}

    if (form.purpose !== ALL) {
query.purpose = form.purpose;
}

    if (form.mandatory !== ALL) {
query.mandatory = form.mandatory;
}

    if (form.due_from) {
query.due_from = form.due_from;
}

    if (form.due_to) {
query.due_to = form.due_to;
}

    query.sort = props.filters.sort;
    query.dir = props.filters.dir;

    return query;
}

function applyFilters() {
    router.get(plannedRoutes.index().url, buildQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    form.direction = ALL;
    form.entity_id = ALL;
    form.status = ALL;
    form.purpose = ALL;
    form.mandatory = ALL;
    form.due_from = '';
    form.due_to = '';
    router.get(
        plannedRoutes.index().url,
        { sort: props.filters.sort, dir: props.filters.dir },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(
    () => [form.direction, form.entity_id, form.status, form.purpose, form.mandatory],
    () => applyFilters(),
);

function applyDates() {
    applyFilters();
}

function toggleSort(column: string) {
    if (!props.options.sortable.includes(column)) {
return;
}

    const sameColumn = props.filters.sort === column;
    const nextDir = sameColumn && props.filters.dir === 'asc' ? 'desc' : 'asc';
    const query = buildQuery();
    query.sort = column;
    query.dir = nextDir;
    router.get(plannedRoutes.index().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function goToPage(page: number) {
    if (page < 1 || page > props.transactions.meta.last_page) {
return;
}

    const query = buildQuery();
    query.page = page;
    router.get(plannedRoutes.index().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

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

const statusVariant = {
    planned: 'secondary',
    settled: 'default',
    overdue: 'destructive',
    cancelled: 'outline',
} as const;

const editingTransaction = ref<Transaction | null>(null);
const editDialogOpen = ref(false);

function openEdit(txn: Transaction): void {
    editingTransaction.value = txn;
    editDialogOpen.value = true;
}

watch(editDialogOpen, (value) => {
    if (!value) {
        editingTransaction.value = null;
    }
});

const deletingTransaction = ref<Transaction | null>(null);
const deleteDialogOpen = ref(false);

function openDelete(txn: Transaction): void {
    deletingTransaction.value = txn;
    deleteDialogOpen.value = true;
}

watch(deleteDialogOpen, (value) => {
    if (!value) {
        deletingTransaction.value = null;
    }
});
</script>

<template>
    <Head title="Planned transactions" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Planned transactions"
                description="Money you plan to pay or receive, by entity and date."
            />
            <PlannedTransactionDialog
                :entities="options.entities"
                :directions="options.directions"
                :statuses="options.statuses"
                :currencies="options.currencies"
                :external-counterparties="options.external_counterparties"
            />
        </div>

        <div class="grid gap-3 rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border md:grid-cols-3 lg:grid-cols-6">
            <div class="grid gap-1.5">
                <Label for="filter-direction">Type</Label>
                <Select v-model="form.direction">
                    <SelectTrigger id="filter-direction">
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All</SelectItem>
                        <SelectItem
                            v-for="d in options.directions"
                            :key="d.value"
                            :value="d.value"
                        >
                            {{ d.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <Label for="filter-entity">Entity</Label>
                <Select v-model="form.entity_id">
                    <SelectTrigger id="filter-entity">
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All</SelectItem>
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
                <Label for="filter-status">Status</Label>
                <Select v-model="form.status">
                    <SelectTrigger id="filter-status">
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All</SelectItem>
                        <SelectItem
                            v-for="s in options.statuses"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <Label for="filter-purpose">Purpose</Label>
                <Select v-model="form.purpose">
                    <SelectTrigger id="filter-purpose">
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All</SelectItem>
                        <SelectItem
                            v-for="p in options.purposes"
                            :key="p"
                            :value="p"
                        >
                            {{ p }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <Label for="filter-mandatory">Mandatory</Label>
                <Select v-model="form.mandatory">
                    <SelectTrigger id="filter-mandatory">
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All</SelectItem>
                        <SelectItem value="yes">Mandatory</SelectItem>
                        <SelectItem value="no">Flexible</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <Label>Due date</Label>
                <div class="flex items-center gap-2">
                    <Input
                        type="date"
                        v-model="form.due_from"
                        @change="applyDates"
                        aria-label="From date"
                    />
                    <span class="text-xs text-muted-foreground">to</span>
                    <Input
                        type="date"
                        v-model="form.due_to"
                        @change="applyDates"
                        aria-label="To date"
                    />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">
                {{ transactions.meta.total }} planned transaction{{ transactions.meta.total === 1 ? '' : 's' }}
            </p>
            <Button variant="ghost" size="sm" @click="resetFilters">Reset filters</Button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead class="border-b border-sidebar-border/60 text-left text-xs uppercase tracking-wide text-muted-foreground dark:border-sidebar-border">
                    <tr>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                                @click="toggleSort('due_date')"
                            >
                                Due date
                                <ArrowUp v-if="filters.sort === 'due_date' && filters.dir === 'asc'" class="size-3" />
                                <ArrowDown v-else-if="filters.sort === 'due_date' && filters.dir === 'desc'" class="size-3" />
                                <ArrowUpDown v-else class="size-3 opacity-40" />
                            </button>
                        </th>
                        <th class="px-3 py-2">Entity</th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                                @click="toggleSort('direction')"
                            >
                                Type
                                <ArrowUp v-if="filters.sort === 'direction' && filters.dir === 'asc'" class="size-3" />
                                <ArrowDown v-else-if="filters.sort === 'direction' && filters.dir === 'desc'" class="size-3" />
                                <ArrowUpDown v-else class="size-3 opacity-40" />
                            </button>
                        </th>
                        <th class="px-3 py-2">Counterparty</th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                                @click="toggleSort('purpose')"
                            >
                                Purpose
                                <ArrowUp v-if="filters.sort === 'purpose' && filters.dir === 'asc'" class="size-3" />
                                <ArrowDown v-else-if="filters.sort === 'purpose' && filters.dir === 'desc'" class="size-3" />
                                <ArrowUpDown v-else class="size-3 opacity-40" />
                            </button>
                        </th>
                        <th class="px-3 py-2 text-right">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                                @click="toggleSort('amount')"
                            >
                                Amount
                                <ArrowUp v-if="filters.sort === 'amount' && filters.dir === 'asc'" class="size-3" />
                                <ArrowDown v-else-if="filters.sort === 'amount' && filters.dir === 'desc'" class="size-3" />
                                <ArrowUpDown v-else class="size-3 opacity-40" />
                            </button>
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                                @click="toggleSort('status')"
                            >
                                Status
                                <ArrowUp v-if="filters.sort === 'status' && filters.dir === 'asc'" class="size-3" />
                                <ArrowDown v-else-if="filters.sort === 'status' && filters.dir === 'desc'" class="size-3" />
                                <ArrowUpDown v-else class="size-3 opacity-40" />
                            </button>
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                                @click="toggleSort('is_mandatory')"
                            >
                                Flag
                                <ArrowUp v-if="filters.sort === 'is_mandatory' && filters.dir === 'asc'" class="size-3" />
                                <ArrowDown v-else-if="filters.sort === 'is_mandatory' && filters.dir === 'desc'" class="size-3" />
                                <ArrowUpDown v-else class="size-3 opacity-40" />
                            </button>
                        </th>
                        <th class="px-3 py-2 w-px"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="transactions.data.length === 0">
                        <td colspan="9" class="px-3 py-10 text-center text-muted-foreground">
                            No planned transactions match these filters.
                        </td>
                    </tr>
                    <tr
                        v-for="txn in transactions.data"
                        :key="txn.id"
                        class="cursor-pointer border-t border-sidebar-border/40 hover:bg-muted/40 dark:border-sidebar-border"
                        @click="openEdit(txn)"
                    >
                        <td class="px-3 py-2 font-mono tabular-nums">{{ formatDate(txn.due_date) }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-block size-2.5 shrink-0 rounded-full"
                                    :class="ENTITY_COLOR_SWATCH[txn.owner_entity.color]"
                                />
                                <span class="truncate">{{ txn.owner_entity.name }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <Badge :variant="txn.direction === 'incoming' ? 'default' : 'secondary'">
                                {{ txn.direction === 'incoming' ? 'In' : 'Out' }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-1.5">
                                <span class="truncate">{{ txn.counterparty.name }}</span>
                                <Badge
                                    v-if="txn.counterparty.kind === 'internal'"
                                    variant="outline"
                                    class="text-[10px]"
                                >
                                    Internal
                                </Badge>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">{{ txn.purpose ?? '—' }}</td>
                        <td class="px-3 py-2 text-right font-mono tabular-nums">
                            <span :class="txn.direction === 'incoming' ? 'text-emerald-600 dark:text-emerald-400' : ''">
                                {{ txn.direction === 'incoming' ? '+' : '−' }}{{ formatAmount(txn.amount) }}
                            </span>
                            <span class="ml-1 text-xs text-muted-foreground">
                                {{ currencySymbol[txn.currency] ?? txn.currency }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <Badge :variant="statusVariant[txn.status]" class="capitalize">
                                {{ txn.status }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2">
                            <Badge :variant="txn.is_mandatory ? 'destructive' : 'outline'">
                                {{ txn.is_mandatory ? 'Mandatory' : 'Flexible' }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    aria-label="Edit planned transaction"
                                    @click.stop="openEdit(txn)"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 text-destructive hover:text-destructive"
                                    aria-label="Delete planned transaction"
                                    @click.stop="openDelete(txn)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PlannedTransactionDialog
            v-if="editingTransaction"
            :key="editingTransaction.id"
            v-model:open="editDialogOpen"
            :entities="options.entities"
            :directions="options.directions"
            :statuses="options.statuses"
            :currencies="options.currencies"
            :external-counterparties="options.external_counterparties"
            :transaction="editingTransaction"
        />

        <DeletePlannedTransactionDialog
            v-if="deletingTransaction"
            :key="`delete-${deletingTransaction.id}`"
            v-model:open="deleteDialogOpen"
            :transaction="deletingTransaction"
        />

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
    </div>
</template>
