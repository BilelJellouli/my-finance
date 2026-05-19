<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AccountCombobox from '@/components/AccountCombobox.vue';
import ExternalCounterpartyCombobox from '@/pages/planned-transactions/ExternalCounterpartyCombobox.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import * as transactionsRoutes from '@/routes/transactions';
import type {
    EntityWithAccounts,
    OpenPlannedRef,
    TransactionKind,
    TransactionListItem,
} from '@/types';

type Option = { value: string; label: string };

export type SelectedPlanned = {
    id: number;
    direction: 'incoming' | 'outgoing';
    amount: string;
    settled_amount: string;
    currency: string;
    due_date: string | null;
    purpose: string | null;
    status: 'planned' | 'settled' | 'overdue' | 'cancelled';
    account_id: number | null;
    mirror_account_id: number | null;
    owner_entity_id: number;
    counterparty: { id: number; name: string; kind: 'internal' | 'external'; entity_id: number | null };
    real_transactions: { id: number; amount: string; occurred_on: string; note: string | null }[];
};

const props = defineProps<{
    entities: EntityWithAccounts[];
    kinds: Option[];
    currencies: { value: string; label: string; symbol: string }[];
    externalCounterparties: { id: number; name: string }[];
    openPlanned?: OpenPlannedRef[];
    transaction?: TransactionListItem | null;
    selectedPlanned?: SelectedPlanned | null;
    defaultFromAccountId?: number | null;
    defaultToAccountId?: number | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.transaction != null);
const isSettling = computed(() => props.selectedPlanned != null && !isEdit.value);

const accountOptions = computed(() =>
    props.entities.flatMap((entity) =>
        entity.accounts.map((account) => ({
            id: account.id,
            name: account.name,
            currency: account.currency,
            current_balance: account.current_balance,
            entity: { id: entity.id, name: entity.name, color: entity.color },
        })),
    ),
);

const ownerEntity = computed(() => {
    if (!props.selectedPlanned) {
        return null;
    }
    return props.entities.find((e) => e.id === props.selectedPlanned!.owner_entity_id) ?? null;
});

const otherEntity = computed(() => {
    if (!props.selectedPlanned || props.selectedPlanned.counterparty.kind !== 'internal') {
        return null;
    }
    return (
        props.entities.find((e) => e.id === props.selectedPlanned!.counterparty.entity_id) ?? null
    );
});

const isOutgoingPlanned = computed(() => props.selectedPlanned?.direction === 'outgoing');
const isExternalPlanned = computed(
    () => props.selectedPlanned?.counterparty.kind === 'external',
);

const fromSideEntity = computed(() => {
    if (!isSettling.value) {
        return null;
    }
    if (isOutgoingPlanned.value) {
        return ownerEntity.value;
    }
    return isExternalPlanned.value ? null : otherEntity.value;
});

const toSideEntity = computed(() => {
    if (!isSettling.value) {
        return null;
    }
    if (isOutgoingPlanned.value) {
        return isExternalPlanned.value ? null : otherEntity.value;
    }
    return ownerEntity.value;
});

function optionsForEntity(entity: EntityWithAccounts | null) {
    if (!entity) {
        return [];
    }
    return entity.accounts.map((a) => ({
        id: a.id,
        name: a.name,
        currency: a.currency,
        current_balance: a.current_balance,
        entity: { id: entity.id, name: entity.name, color: entity.color },
    }));
}

const fromAccountOptions = computed(() =>
    isSettling.value ? optionsForEntity(fromSideEntity.value) : accountOptions.value,
);
const toAccountOptions = computed(() =>
    isSettling.value ? optionsForEntity(toSideEntity.value) : accountOptions.value,
);

const showFromAccountPicker = computed(
    () => !isSettling.value || fromSideEntity.value !== null,
);
const showToAccountPicker = computed(
    () => !isSettling.value || toSideEntity.value !== null,
);

function today(): string {
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

const remaining = computed(() => {
    if (!props.selectedPlanned) {
        return 0;
    }
    const planned = parseFloat(props.selectedPlanned.amount);
    const settled = parseFloat(props.selectedPlanned.settled_amount);
    if (Number.isNaN(planned)) {
        return 0;
    }
    return Math.max(0, Math.round((planned - settled) * 100) / 100);
});

const isCancelled = computed(() => props.selectedPlanned?.status === 'cancelled');
const isFullySettled = computed(() => isSettling.value && remaining.value <= 0);

type FormShape = {
    planned_transaction_id: number | null;
    from_account_id: number | null;
    to_account_id: number | null;
    counterparty_id: number | null;
    external_counterparty_name: string;
    amount: string;
    currency: string;
    kind: TransactionKind;
    occurred_on: string;
    note: string;
};

function defaultForm(): FormShape {
    if (props.transaction) {
        return {
            planned_transaction_id: props.transaction.planned_transaction?.id ?? null,
            from_account_id: props.transaction.from_account?.id ?? null,
            to_account_id: props.transaction.to_account?.id ?? null,
            counterparty_id:
                props.transaction.counterparty && props.transaction.counterparty.kind === 'external'
                    ? props.transaction.counterparty.id
                    : null,
            external_counterparty_name: '',
            amount: props.transaction.amount,
            currency: props.transaction.currency,
            kind: props.transaction.kind,
            occurred_on: props.transaction.occurred_on,
            note: props.transaction.note ?? '',
        };
    }

    if (props.selectedPlanned) {
        const isOutgoing = props.selectedPlanned.direction === 'outgoing';
        const isExternalCp = props.selectedPlanned.counterparty.kind === 'external';
        const ownerSideAccount = props.selectedPlanned.account_id;
        const otherSideAccount = isExternalCp ? null : props.selectedPlanned.mirror_account_id;
        return {
            planned_transaction_id: props.selectedPlanned.id,
            from_account_id: isOutgoing ? ownerSideAccount : otherSideAccount,
            to_account_id: isOutgoing ? otherSideAccount : ownerSideAccount,
            counterparty_id: null,
            external_counterparty_name: '',
            amount: remaining.value.toFixed(2),
            currency: props.selectedPlanned.currency,
            kind: (props.kinds[0]?.value as TransactionKind) ?? 'bank_transfer',
            occurred_on: today(),
            note: '',
        };
    }

    const defaultFromId = props.defaultFromAccountId ?? null;
    const defaultToId = props.defaultToAccountId ?? null;
    const seedAccountId = defaultFromId ?? defaultToId;
    const seedAccount = seedAccountId !== null
        ? accountOptions.value.find((a) => a.id === seedAccountId)
        : null;

    return {
        planned_transaction_id: null,
        from_account_id: defaultFromId,
        to_account_id: defaultToId,
        counterparty_id: null,
        external_counterparty_name: '',
        amount: '',
        currency: seedAccount?.currency ?? props.currencies[0]?.value ?? 'EUR',
        kind: (props.kinds[0]?.value as TransactionKind) ?? 'cash',
        occurred_on: today(),
        note: '',
    };
}

const form = useForm<FormShape>(defaultForm());

const fromAccount = computed(() =>
    accountOptions.value.find((a) => a.id === form.from_account_id) ?? null,
);
const toAccount = computed(() =>
    accountOptions.value.find((a) => a.id === form.to_account_id) ?? null,
);

// Keep the form's currency in sync with whichever account is selected (locked while an account is picked).
watch([fromAccount, toAccount], ([from, to]) => {
    const inferred = from?.currency ?? to?.currency ?? null;
    if (inferred && form.currency !== inferred) {
        form.currency = inferred;
    }
});

const isTransfer = computed(() => form.from_account_id !== null && form.to_account_id !== null);
const hasOneAccount = computed(
    () =>
        (form.from_account_id !== null && form.to_account_id === null) ||
        (form.from_account_id === null && form.to_account_id !== null),
);
const showCounterpartyField = computed(
    () =>
        !isSettling.value &&
        !isTransfer.value &&
        (hasOneAccount.value ||
            (!form.from_account_id && !form.to_account_id && form.planned_transaction_id !== null)),
);
const currencyLocked = computed(() => fromAccount.value !== null || toAccount.value !== null || isSettling.value);

const formatAmount = (amount: string): string => {
    const n = parseFloat(amount);
    if (Number.isNaN(n)) {
        return '0.00';
    }
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (iso: string | null): string => {
    if (!iso) {
        return '—';
    }
    return new Date(iso + 'T00:00:00').toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

watch(open, (value) => {
    if (value) {
        form.defaults(defaultForm());
        form.reset();
        form.clearErrors();
    } else {
        form.clearErrors();
    }
});

function submit(): void {
    const payload: Record<string, unknown> = {
        from_account_id: form.from_account_id,
        to_account_id: form.to_account_id,
        counterparty_id: form.counterparty_id,
        kind: form.kind,
        currency: form.currency,
        amount: form.amount,
        occurred_on: form.occurred_on,
        note: form.note || null,
    };
    if (!isEdit.value) {
        payload.planned_transaction_id = form.planned_transaction_id;
    }

    if (isEdit.value && props.transaction) {
        form
            .transform(() => payload)
            .put(transactionsRoutes.update(props.transaction.id).url, {
                preserveScroll: true,
                onSuccess: () => {
                    open.value = false;
                    form.reset();
                    form.clearErrors();
                },
            });
        return;
    }

    form
        .transform(() => payload)
        .post(transactionsRoutes.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                form.reset();
                form.clearErrors();
            },
        });
}

function pickedFromOpenPlanned(): OpenPlannedRef | null {
    if (!form.planned_transaction_id || !props.openPlanned) {
        return null;
    }
    return props.openPlanned.find((p) => p.id === form.planned_transaction_id) ?? null;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[640px]">
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>
                        {{ isEdit ? 'Edit transaction' : isSettling ? 'Settle planned — record transaction' : 'Record a transaction' }}
                    </DialogTitle>
                    <DialogDescription>
                        Track money that actually moved. Pick at least one account, or link to a planned transaction to settle it.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="isSettling && selectedPlanned"
                    class="grid gap-3 rounded-md border border-sidebar-border/60 bg-muted/40 p-3 text-sm dark:border-sidebar-border"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs uppercase tracking-wide text-muted-foreground">Counterparty</span>
                        <div class="flex items-center gap-1.5">
                            <span>{{ selectedPlanned.counterparty.name }}</span>
                            <Badge v-if="selectedPlanned.counterparty.kind === 'internal'" variant="outline" class="text-[10px]">
                                Internal
                            </Badge>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs uppercase tracking-wide text-muted-foreground">Direction</span>
                        <Badge :variant="selectedPlanned.direction === 'incoming' ? 'default' : 'secondary'">
                            {{ selectedPlanned.direction === 'incoming' ? 'Incoming' : 'Outgoing' }}
                        </Badge>
                    </div>
                    <div v-if="selectedPlanned.purpose" class="flex items-center justify-between gap-3">
                        <span class="text-xs uppercase tracking-wide text-muted-foreground">Purpose</span>
                        <span>{{ selectedPlanned.purpose }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 pt-1 font-mono tabular-nums">
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-muted-foreground">Planned</p>
                            <p>{{ formatAmount(selectedPlanned.amount) }} <span class="text-xs text-muted-foreground">{{ selectedPlanned.currency }}</span></p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-muted-foreground">Settled</p>
                            <p>{{ formatAmount(selectedPlanned.settled_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-muted-foreground">Remaining</p>
                            <p :class="remaining > 0 ? '' : 'text-emerald-600 dark:text-emerald-400'">
                                {{ remaining.toFixed(2) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="isCancelled" class="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                    This planned transaction is cancelled — you can't record new transactions on it.
                </div>
                <div v-else-if="isFullySettled" class="rounded-md border border-emerald-500/40 bg-emerald-500/5 p-3 text-sm text-emerald-700 dark:text-emerald-400">
                    This planned transaction is fully settled.
                </div>

                <div class="grid gap-2">
                    <Label for="txn-kind">Type</Label>
                    <Select v-model="form.kind" :disabled="isCancelled || isFullySettled">
                        <SelectTrigger id="txn-kind">
                            <SelectValue placeholder="Pick a type…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in kinds"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.kind" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-if="showFromAccountPicker" class="grid gap-2">
                        <Label for="txn-from">
                            From account<span v-if="isSettling && fromSideEntity"> ({{ fromSideEntity.name }})</span>
                        </Label>
                        <AccountCombobox
                            v-model="form.from_account_id"
                            :options="fromAccountOptions"
                            :placeholder="isSettling ? 'Pick an account…' : '— None (external) —'"
                            :invalid="!!form.errors.from_account_id"
                            :disabled="isCancelled || isFullySettled"
                            :allow-clear="!isSettling"
                        />
                        <p v-if="fromAccount" class="text-xs text-muted-foreground">
                            Available: <span class="font-mono tabular-nums">{{ fromAccount.current_balance ?? '—' }} {{ fromAccount.currency }}</span>
                        </p>
                        <InputError :message="form.errors.from_account_id" />
                    </div>
                    <div v-else-if="isSettling && selectedPlanned" class="grid gap-2">
                        <Label>From</Label>
                        <div class="flex h-9 items-center gap-2 rounded-md border border-dashed border-sidebar-border/60 bg-muted/30 px-3 text-sm dark:border-sidebar-border">
                            <span class="text-muted-foreground">External:</span>
                            <span>{{ selectedPlanned.counterparty.name }}</span>
                        </div>
                    </div>

                    <div v-if="showToAccountPicker" class="grid gap-2">
                        <Label for="txn-to">
                            To account<span v-if="isSettling && toSideEntity"> ({{ toSideEntity.name }})</span>
                        </Label>
                        <AccountCombobox
                            v-model="form.to_account_id"
                            :options="toAccountOptions"
                            :placeholder="isSettling ? 'Pick an account…' : '— None (external) —'"
                            :invalid="!!form.errors.to_account_id"
                            :disabled="isCancelled || isFullySettled"
                            :allow-clear="!isSettling"
                        />
                        <InputError :message="form.errors.to_account_id" />
                    </div>
                    <div v-else-if="isSettling && selectedPlanned" class="grid gap-2">
                        <Label>To</Label>
                        <div class="flex h-9 items-center gap-2 rounded-md border border-dashed border-sidebar-border/60 bg-muted/30 px-3 text-sm dark:border-sidebar-border">
                            <span class="text-muted-foreground">External:</span>
                            <span>{{ selectedPlanned.counterparty.name }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="showCounterpartyField" class="grid gap-2">
                    <Label>Counterparty (external)</Label>
                    <ExternalCounterpartyCombobox
                        :counterparty-id="form.counterparty_id"
                        :external-name="form.external_counterparty_name"
                        :options="externalCounterparties"
                        placeholder="Pick or skip…"
                        @update:counterparty-id="form.counterparty_id = $event"
                        @update:external-name="form.external_counterparty_name = $event"
                    />
                    <p class="text-xs text-muted-foreground">
                        Only used when one side is outside your tracked accounts. Internal entity-to-entity moves are implicit from the chosen accounts.
                    </p>
                    <InputError :message="form.errors.counterparty_id" />
                </div>

                <div v-if="!isEdit && !isSettling && openPlanned && openPlanned.length > 0" class="grid gap-2">
                    <Label for="txn-planned">Link to planned (optional)</Label>
                    <Select
                        :model-value="form.planned_transaction_id === null ? '__none__' : String(form.planned_transaction_id)"
                        @update:model-value="(value) => (form.planned_transaction_id = value === '__none__' ? null : Number(value))"
                    >
                        <SelectTrigger id="txn-planned">
                            <SelectValue placeholder="Not linked" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">— Not linked —</SelectItem>
                            <SelectItem
                                v-for="p in openPlanned"
                                :key="p.id"
                                :value="String(p.id)"
                            >
                                {{ p.due_date ?? 'No date' }} · {{ p.owner_entity.name }} · {{ p.direction }} ·
                                {{ p.amount }} {{ p.currency }}{{ p.purpose ? ` · ${p.purpose}` : '' }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div
                        v-if="pickedFromOpenPlanned()"
                        class="rounded-md border border-sidebar-border/60 bg-muted/30 p-2 text-xs dark:border-sidebar-border"
                    >
                        Settling
                        <Badge variant="outline" class="mx-1 text-[10px]">{{ pickedFromOpenPlanned()?.direction }}</Badge>
                        of {{ pickedFromOpenPlanned()?.amount }} {{ pickedFromOpenPlanned()?.currency }}
                        with {{ pickedFromOpenPlanned()?.counterparty.name }}.
                    </div>
                    <InputError :message="form.errors.planned_transaction_id" />
                </div>

                <div class="grid gap-3 sm:grid-cols-[2fr_1fr_1fr]">
                    <div class="grid gap-2">
                        <Label for="txn-amount">Amount</Label>
                        <Input
                            id="txn-amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="isSettling ? remaining : undefined"
                            inputmode="decimal"
                            autocomplete="off"
                            :disabled="isCancelled || isFullySettled"
                            required
                        />
                        <p v-if="isSettling" class="text-xs text-muted-foreground">
                            Up to {{ remaining.toFixed(2) }} {{ selectedPlanned?.currency }} remaining.
                        </p>
                        <InputError :message="form.errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="txn-currency">Currency</Label>
                        <Select v-model="form.currency" :disabled="currencyLocked || isCancelled || isFullySettled">
                            <SelectTrigger id="txn-currency">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="c in currencies"
                                    :key="c.value"
                                    :value="c.value"
                                >
                                    {{ c.value }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.currency" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="txn-date">Date</Label>
                        <Input
                            id="txn-date"
                            v-model="form.occurred_on"
                            type="date"
                            :disabled="isCancelled || isFullySettled"
                            required
                        />
                        <InputError :message="form.errors.occurred_on" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="txn-note">Note</Label>
                    <Input
                        id="txn-note"
                        v-model="form.note"
                        placeholder="Optional"
                        autocomplete="off"
                        :disabled="isCancelled || isFullySettled"
                    />
                    <InputError :message="form.errors.note" />
                </div>

                <div v-if="isSettling && selectedPlanned && selectedPlanned.real_transactions.length > 0" class="grid gap-2">
                    <Label>History</Label>
                    <div class="overflow-hidden rounded-md border border-sidebar-border/60 dark:border-sidebar-border">
                        <table class="w-full text-xs">
                            <thead class="bg-muted/40 text-left uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-1.5">Date</th>
                                    <th class="px-3 py-1.5 text-right">Amount</th>
                                    <th class="px-3 py-1.5">Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="t in selectedPlanned.real_transactions"
                                    :key="t.id"
                                    class="border-t border-sidebar-border/40 dark:border-sidebar-border"
                                >
                                    <td class="px-3 py-1.5 font-mono tabular-nums">{{ formatDate(t.occurred_on) }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ formatAmount(t.amount) }}</td>
                                    <td class="px-3 py-1.5 text-muted-foreground">{{ t.note ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing || isCancelled || isFullySettled">
                        {{ isEdit ? 'Save changes' : isSettling ? 'Record transaction' : 'Record transaction' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
