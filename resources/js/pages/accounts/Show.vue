<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, ChevronLeft, Pencil, Plus, Star, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ENTITY_COLOR_SWATCH } from '@/pages/entities/colors';
import DeleteTransactionDialog from '@/pages/transactions/DeleteTransactionDialog.vue';
import TransactionDialog from '@/pages/transactions/TransactionDialog.vue';
import * as entityRoutes from '@/routes/entities';
import * as transactionsRoutes from '@/routes/transactions';
import type {
    EntityWithAccounts,
    TransactionKind as TKind,
    TransactionListItem,
} from '@/types';

type Option = { value: string; label: string };

type LedgerOtherAccount = {
    id: number;
    name: string;
    currency: string;
    entity: { id: number; name: string; color: string } | null;
};

type LedgerEntry = {
    id: number;
    occurred_on: string;
    direction: 'incoming' | 'outgoing';
    amount: string;
    signed_amount: string;
    running_balance: string;
    currency: string;
    kind: TKind;
    note: string | null;
    other_account: LedgerOtherAccount | null;
    external_party: string | null;
    planned_transaction: { id: number; purpose: string | null; due_date: string | null } | null;
    from_account_id: number | null;
    to_account_id: number | null;
    counterparty_id: number | null;
};

type AccountRef = {
    id: number;
    name: string;
    currency: string;
    opening_balance: string;
    current_balance: string;
    is_main: boolean;
    entity: { id: number; name: string; color: string };
};

const props = defineProps<{
    account: AccountRef;
    ledger: LedgerEntry[];
    transaction_options: {
        entities: EntityWithAccounts[];
        kinds: Option[];
        currencies: { value: string; label: string; symbol: string }[];
        external_counterparties: { id: number; name: string }[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Entities', href: entityRoutes.index() },
        ],
    },
});

const createOpen = ref(false);
const editTarget = ref<TransactionListItem | null>(null);
const editOpen = ref(false);
const deleteTarget = ref<TransactionListItem | null>(null);
const deleteOpen = ref(false);

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
    return props.transaction_options.kinds.find((k) => k.value === value)?.label ?? value;
}

function toTransactionListItem(entry: LedgerEntry): TransactionListItem {
    const findAccount = (id: number | null) => {
        if (id === null) {
            return null;
        }
        for (const entity of props.transaction_options.entities) {
            const found = entity.accounts.find((a) => a.id === id);
            if (found) {
                return {
                    id: found.id,
                    name: found.name,
                    currency: found.currency,
                    entity: { id: entity.id, name: entity.name, color: entity.color },
                };
            }
        }
        return null;
    };

    const findCounterparty = (id: number | null) => {
        if (id === null) {
            return null;
        }
        const cp = props.transaction_options.external_counterparties.find((c) => c.id === id);
        return cp ? { id: cp.id, name: cp.name, kind: 'external' as const } : null;
    };

    return {
        id: entry.id,
        amount: entry.amount,
        currency: entry.currency,
        kind: entry.kind,
        occurred_on: entry.occurred_on,
        note: entry.note,
        from_account: findAccount(entry.from_account_id),
        to_account: findAccount(entry.to_account_id),
        counterparty: findCounterparty(entry.counterparty_id),
        planned_transaction: entry.planned_transaction
            ? {
                id: entry.planned_transaction.id,
                purpose: entry.planned_transaction.purpose,
                due_date: entry.planned_transaction.due_date,
                amount: '0',
                counterparty: null,
            }
            : null,
    };
}

function openEdit(entry: LedgerEntry): void {
    editTarget.value = toTransactionListItem(entry);
    editOpen.value = true;
}

function openDelete(entry: LedgerEntry): void {
    deleteTarget.value = toTransactionListItem(entry);
    deleteOpen.value = true;
}
</script>

<template>
    <Head :title="`${account.entity.name} — ${account.name}`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child :aria-label="`Back to ${account.entity.name}`">
                    <Link :href="entityRoutes.edit(account.entity.id).url">
                        <ChevronLeft class="size-4" />
                    </Link>
                </Button>
                <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                        <Star v-if="account.is_main" class="size-3.5 fill-amber-400 text-amber-400" />
                        <span
                            class="inline-block size-3 rounded-full"
                            :class="ENTITY_COLOR_SWATCH[account.entity.color] ?? 'bg-slate-300'"
                        />
                        <Heading
                            :title="`${account.entity.name} — ${account.name}`"
                            description="All recorded transactions touching this account."
                        />
                    </div>
                </div>
            </div>
            <Button @click="createOpen = true">
                <Plus class="size-4" /> Record transaction
            </Button>
        </div>

        <div class="grid gap-3 rounded-md border border-sidebar-border/60 bg-muted/30 p-4 dark:border-sidebar-border sm:grid-cols-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Opening balance</p>
                <p class="font-mono tabular-nums">{{ formatAmount(account.opening_balance) }} {{ account.currency }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Current balance</p>
                <p
                    class="font-mono text-lg tabular-nums"
                    :class="parseFloat(account.current_balance) < 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'"
                >
                    {{ formatAmount(account.current_balance) }} {{ account.currency }}
                </p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Recorded transactions</p>
                <p class="font-mono tabular-nums">{{ ledger.length }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-md border border-sidebar-border/60 dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead class="bg-muted/40 text-left uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Other side</th>
                        <th class="px-3 py-2">Kind</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                        <th class="px-3 py-2 text-right">Running</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="ledger.length === 0">
                        <td colspan="6" class="px-3 py-8 text-center text-muted-foreground">
                            No transactions yet on this account.
                        </td>
                    </tr>
                    <tr
                        v-for="entry in ledger"
                        :key="entry.id"
                        class="border-t border-sidebar-border/40 dark:border-sidebar-border"
                    >
                        <td class="px-3 py-2 font-mono tabular-nums">{{ formatDate(entry.occurred_on) }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-1.5">
                                <ArrowDownLeft v-if="entry.direction === 'incoming'" class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                                <ArrowUpRight v-else class="size-3.5 text-amber-600 dark:text-amber-400" />
                                <template v-if="entry.other_account">
                                    <span
                                        class="inline-block size-2 shrink-0 rounded-full"
                                        :class="entry.other_account.entity ? (ENTITY_COLOR_SWATCH[entry.other_account.entity.color] ?? 'bg-slate-300') : 'bg-slate-300'"
                                    />
                                    <span class="text-muted-foreground">{{ entry.other_account.entity?.name }} —</span>
                                    <span>{{ entry.other_account.name }}</span>
                                </template>
                                <template v-else-if="entry.external_party">
                                    <span class="text-muted-foreground">{{ entry.external_party }}</span>
                                </template>
                                <template v-else>
                                    <span class="text-muted-foreground">—</span>
                                </template>
                                <Badge
                                    v-if="entry.planned_transaction"
                                    variant="secondary"
                                    class="ml-1 text-[10px]"
                                    :title="entry.planned_transaction.due_date ?? ''"
                                >
                                    {{ entry.planned_transaction.purpose ?? 'planned' }}
                                </Badge>
                            </div>
                            <p v-if="entry.note" class="ml-5 text-xs text-muted-foreground">{{ entry.note }}</p>
                        </td>
                        <td class="px-3 py-2">
                            <Badge variant="outline">{{ kindLabel(entry.kind) }}</Badge>
                        </td>
                        <td
                            class="px-3 py-2 text-right font-mono tabular-nums"
                            :class="entry.direction === 'incoming' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
                        >
                            {{ entry.direction === 'incoming' ? '+' : '−' }}{{ formatAmount(entry.amount) }}
                            <span class="text-xs text-muted-foreground">{{ entry.currency }}</span>
                        </td>
                        <td class="px-3 py-2 text-right font-mono tabular-nums">
                            {{ formatAmount(entry.running_balance) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon" @click="openEdit(entry)">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button variant="ghost" size="icon" @click="openDelete(entry)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <TransactionDialog
            v-model:open="createOpen"
            :entities="transaction_options.entities"
            :kinds="transaction_options.kinds"
            :currencies="transaction_options.currencies"
            :external-counterparties="transaction_options.external_counterparties"
            :default-from-account-id="account.id"
        />

        <TransactionDialog
            v-if="editTarget"
            :key="`edit-${editTarget.id}`"
            v-model:open="editOpen"
            :entities="transaction_options.entities"
            :kinds="transaction_options.kinds"
            :currencies="transaction_options.currencies"
            :external-counterparties="transaction_options.external_counterparties"
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
