<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
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

type RealTransaction = {
    id: number;
    amount: string;
    occurred_on: string;
    note: string | null;
};

type PlannedSummary = {
    id: number;
    direction: 'incoming' | 'outgoing';
    amount: string;
    settled_amount: string;
    currency: string;
    due_date: string | null;
    purpose: string | null;
    status: 'planned' | 'settled' | 'overdue' | 'cancelled';
    counterparty: { id: number; name: string; kind: 'internal' | 'external' };
    real_transactions: RealTransaction[];
};

const props = defineProps<{
    planned: PlannedSummary;
}>();

const open = defineModel<boolean>('open', { default: false });

function today(): string {
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');

    return `${yyyy}-${mm}-${dd}`;
}

const remaining = computed(() => {
    const planned = parseFloat(props.planned.amount);
    const settled = parseFloat(props.planned.settled_amount);

    if (Number.isNaN(planned)) {
        return 0;
    }

    return Math.max(0, Math.round((planned - settled) * 100) / 100);
});

const form = useForm<{ amount: string; occurred_on: string; note: string }>({
    amount: remaining.value.toFixed(2),
    occurred_on: today(),
    note: '',
});

const isCancelled = computed(() => props.planned.status === 'cancelled');
const isFullySettled = computed(() => remaining.value <= 0);

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

function submit(): void {
    form.submit(TransactionController.store(props.planned.id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
            form.clearErrors();
        },
    });
}

watch(open, (value) => {
    if (value) {
        form.defaults({
            amount: remaining.value.toFixed(2),
            occurred_on: today(),
            note: '',
        });
        form.reset();
        form.clearErrors();
    } else {
        form.clearErrors();
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[560px]">
            <form @submit.prevent="submit" class="space-y-5">
                <DialogHeader>
                    <DialogTitle>Record a transaction</DialogTitle>
                    <DialogDescription>
                        Log money that actually moved against this planned transaction. Multiple partial entries are fine — the total can't exceed the planned amount.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 rounded-md border border-sidebar-border/60 bg-muted/40 p-3 text-sm dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs uppercase tracking-wide text-muted-foreground">Counterparty</span>
                        <div class="flex items-center gap-1.5">
                            <span>{{ planned.counterparty.name }}</span>
                            <Badge v-if="planned.counterparty.kind === 'internal'" variant="outline" class="text-[10px]">
                                Internal
                            </Badge>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs uppercase tracking-wide text-muted-foreground">Direction</span>
                        <Badge :variant="planned.direction === 'incoming' ? 'default' : 'secondary'">
                            {{ planned.direction === 'incoming' ? 'Incoming' : 'Outgoing' }}
                        </Badge>
                    </div>
                    <div v-if="planned.purpose" class="flex items-center justify-between gap-3">
                        <span class="text-xs uppercase tracking-wide text-muted-foreground">Purpose</span>
                        <span>{{ planned.purpose }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 pt-1 font-mono tabular-nums">
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-muted-foreground">Planned</p>
                            <p>{{ formatAmount(planned.amount) }} <span class="text-xs text-muted-foreground">{{ planned.currency }}</span></p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-muted-foreground">Settled</p>
                            <p>{{ formatAmount(planned.settled_amount) }}</p>
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

                <div class="grid gap-3 sm:grid-cols-[1fr_180px]">
                    <div class="grid gap-2">
                        <Label for="txn-amount">Amount</Label>
                        <Input
                            id="txn-amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="remaining"
                            inputmode="decimal"
                            autocomplete="off"
                            :disabled="isCancelled || isFullySettled"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Up to {{ remaining.toFixed(2) }} {{ planned.currency }} remaining.
                        </p>
                        <InputError :message="form.errors.amount" />
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

                <div v-if="planned.real_transactions.length" class="grid gap-2">
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
                                    v-for="t in planned.real_transactions"
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
                        Record transaction
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
