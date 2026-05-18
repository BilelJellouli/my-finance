<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import PlannedTransactionController from '@/actions/App/Http/Controllers/PlannedTransactionController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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
import { ENTITY_COLOR_SWATCH } from '@/pages/entities/colors';

type EntityOption = { id: number; name: string; type: 'personal' | 'llc'; color: string };
type CurrencyOption = { value: string; label: string; symbol: string };
type DirectionOption = { value: string; label: string };
type StatusOption = { value: string; label: string };
type ExternalCp = { id: number; name: string };

type EditableTransaction = {
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
    owner_entity: { id: number; name: string; type: 'personal' | 'llc'; color: string };
    counterparty: { id: number; name: string; kind: 'internal' | 'external' };
};

const props = defineProps<{
    entities: EntityOption[];
    directions: DirectionOption[];
    statuses: StatusOption[];
    currencies: CurrencyOption[];
    externalCounterparties: ExternalCp[];
    transaction?: EditableTransaction | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => !!props.transaction);

const defaultEntityId = props.entities[0]?.id ?? 0;
const defaultCurrency = props.currencies[0]?.value ?? 'TND';

type FormShape = {
    owner_entity_id: number;
    counterparty_mode: 'internal' | 'external_existing' | 'external_new';
    internal_entity_id: number | null;
    counterparty_id: number | null;
    external_name: string;
    direction: 'incoming' | 'outgoing';
    amount: string | number;
    currency: string;
    due_date: string;
    purpose: string;
    status: 'planned' | 'settled' | 'overdue' | 'cancelled';
    is_mandatory: boolean;
    note: string;
};

function blankForm(): FormShape {
    return {
        owner_entity_id: defaultEntityId,
        counterparty_mode: 'external_new',
        internal_entity_id: null,
        counterparty_id: null,
        external_name: '',
        direction: 'outgoing',
        amount: '',
        currency: defaultCurrency,
        due_date: '',
        purpose: '',
        status: 'planned',
        is_mandatory: true,
        note: '',
    };
}

function formFromTransaction(t: EditableTransaction): FormShape {
    return {
        owner_entity_id: t.owner_entity.id,
        counterparty_mode: 'external_new',
        internal_entity_id: null,
        counterparty_id: null,
        external_name: '',
        direction: t.direction,
        amount: t.amount,
        currency: t.currency,
        due_date: t.due_date ?? '',
        purpose: t.purpose ?? '',
        status: t.status,
        is_mandatory: t.is_mandatory,
        note: t.note ?? '',
    };
}

const form = useForm<FormShape>(
    props.transaction ? formFromTransaction(props.transaction) : blankForm(),
);

const otherEntities = computed(() =>
    props.entities.filter((e) => e.id !== Number(form.owner_entity_id)),
);

watch(
    () => form.owner_entity_id,
    () => {
        if (isEdit.value) {
            return;
        }

        if (
            form.internal_entity_id !== null &&
            !otherEntities.value.some((e) => e.id === form.internal_entity_id)
        ) {
            form.internal_entity_id = null;
        }
    },
);

function submit(): void {
    const route =
        isEdit.value && props.transaction
            ? PlannedTransactionController.update(props.transaction.id)
            : PlannedTransactionController.store();

    form.submit(route, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;

            if (!isEdit.value) {
                form.reset();
            }

            form.clearErrors();
        },
    });
}

watch(open, (value) => {
    if (value) {
        form.defaults(props.transaction ? formFromTransaction(props.transaction) : blankForm());
        form.reset();
        form.clearErrors();
    } else {
        form.clearErrors();
    }
});

const ownerEntityForDisplay = computed(() => props.transaction?.owner_entity);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger v-if="!isEdit" as-child>
            <Button>
                <Plus class="size-4" />
                New planned transaction
            </Button>
        </DialogTrigger>
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[560px]">
            <form @submit.prevent="submit" class="space-y-5">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Edit planned transaction' : 'New planned transaction' }}</DialogTitle>
                    <DialogDescription>
                        <template v-if="isEdit">
                            Update the amount, dates, status or note. The entity and counterparty are locked — to change them, delete and recreate.
                        </template>
                        <template v-else>
                            Money you plan to pay or receive. Pick the entity it belongs to and the other side.
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <template v-if="isEdit && transaction && ownerEntityForDisplay">
                    <div class="grid gap-3 rounded-md border border-sidebar-border/60 bg-muted/40 p-3 text-sm dark:border-sidebar-border">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs uppercase tracking-wide text-muted-foreground">Entity</span>
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-block size-2.5 shrink-0 rounded-full"
                                    :class="ENTITY_COLOR_SWATCH[ownerEntityForDisplay.color]"
                                />
                                <span>{{ ownerEntityForDisplay.name }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs uppercase tracking-wide text-muted-foreground">Direction</span>
                            <Badge :variant="transaction.direction === 'incoming' ? 'default' : 'secondary'">
                                {{ transaction.direction === 'incoming' ? 'Incoming' : 'Outgoing' }}
                            </Badge>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs uppercase tracking-wide text-muted-foreground">Counterparty</span>
                            <div class="flex items-center gap-1.5">
                                <span>{{ transaction.counterparty.name }}</span>
                                <Badge
                                    v-if="transaction.counterparty.kind === 'internal'"
                                    variant="outline"
                                    class="text-[10px]"
                                >
                                    Internal
                                </Badge>
                            </div>
                        </div>
                        <p v-if="transaction.transfer_group_id" class="text-xs text-muted-foreground">
                            Linked transfer — the matching row on the other entity will be updated too.
                        </p>
                    </div>
                </template>

                <template v-else>
                    <div class="grid gap-2">
                        <Label for="ptx-owner">Entity</Label>
                        <Select v-model="form.owner_entity_id">
                            <SelectTrigger id="ptx-owner">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="entity in entities"
                                    :key="entity.id"
                                    :value="entity.id"
                                >
                                    {{ entity.name }} ({{ entity.type === 'personal' ? 'Personal' : 'LLC' }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.owner_entity_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Direction</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="rounded-md border px-3 py-2 text-sm transition"
                                :class="
                                    form.direction === 'outgoing'
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-sidebar-border/70 hover:bg-muted'
                                "
                                @click="form.direction = 'outgoing'"
                            >
                                Outgoing (money out)
                            </button>
                            <button
                                type="button"
                                class="rounded-md border px-3 py-2 text-sm transition"
                                :class="
                                    form.direction === 'incoming'
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-sidebar-border/70 hover:bg-muted'
                                "
                                @click="form.direction = 'incoming'"
                            >
                                Incoming (money in)
                            </button>
                        </div>
                        <InputError :message="form.errors.direction" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Counterparty</Label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                class="rounded-md border px-3 py-2 text-xs transition"
                                :class="
                                    form.counterparty_mode === 'external_new'
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-sidebar-border/70 hover:bg-muted'
                                "
                                @click="form.counterparty_mode = 'external_new'"
                            >
                                New external
                            </button>
                            <button
                                type="button"
                                class="rounded-md border px-3 py-2 text-xs transition"
                                :class="
                                    form.counterparty_mode === 'external_existing'
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-sidebar-border/70 hover:bg-muted'
                                "
                                :disabled="externalCounterparties.length === 0"
                                @click="form.counterparty_mode = 'external_existing'"
                            >
                                Existing external
                            </button>
                            <button
                                type="button"
                                class="rounded-md border px-3 py-2 text-xs transition"
                                :class="
                                    form.counterparty_mode === 'internal'
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-sidebar-border/70 hover:bg-muted'
                                "
                                :disabled="otherEntities.length === 0"
                                @click="form.counterparty_mode = 'internal'"
                            >
                                Another entity
                            </button>
                        </div>

                        <div v-if="form.counterparty_mode === 'external_new'" class="grid gap-1.5">
                            <Input
                                v-model="form.external_name"
                                placeholder="e.g. Landlord, Tax office, ACME Client"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.external_name" />
                        </div>

                        <div v-else-if="form.counterparty_mode === 'external_existing'" class="grid gap-1.5">
                            <Select v-model="form.counterparty_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pick a counterparty" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="cp in externalCounterparties"
                                        :key="cp.id"
                                        :value="cp.id"
                                    >
                                        {{ cp.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.counterparty_id" />
                        </div>

                        <div v-else class="grid gap-1.5">
                            <Select v-model="form.internal_entity_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pick another entity" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="entity in otherEntities"
                                        :key="entity.id"
                                        :value="entity.id"
                                    >
                                        {{ entity.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">
                                A matching incoming row will be created on that entity too. The pair settles together when paid.
                            </p>
                            <InputError :message="form.errors.internal_entity_id" />
                        </div>
                    </div>
                </template>

                <div class="grid gap-3 sm:grid-cols-[1fr_140px]">
                    <div class="grid gap-2">
                        <Label for="ptx-amount">Amount</Label>
                        <Input
                            id="ptx-amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            inputmode="decimal"
                            autocomplete="off"
                            required
                        />
                        <InputError :message="form.errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ptx-currency">Currency</Label>
                        <Select v-model="form.currency">
                            <SelectTrigger id="ptx-currency">
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
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="ptx-due">Due date <span class="text-xs text-muted-foreground">(optional)</span></Label>
                        <Input
                            id="ptx-due"
                            v-model="form.due_date"
                            type="date"
                        />
                        <InputError :message="form.errors.due_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ptx-purpose">Purpose</Label>
                        <Input
                            id="ptx-purpose"
                            v-model="form.purpose"
                            placeholder="Rent, Salary, Tax…"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.purpose" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="ptx-status">Status</Label>
                        <Select v-model="form.status">
                            <SelectTrigger id="ptx-status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="s in statuses"
                                    :key="s.value"
                                    :value="s.value"
                                >
                                    {{ s.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>
                    <label
                        for="ptx-mandatory"
                        class="flex items-center gap-2 pt-7 text-sm select-none"
                    >
                        <Checkbox id="ptx-mandatory" v-model="form.is_mandatory" />
                        <span>Mandatory (uncheck if flexible / adjustable)</span>
                    </label>
                </div>

                <div class="grid gap-2">
                    <Label for="ptx-note">Note</Label>
                    <Input
                        id="ptx-note"
                        v-model="form.note"
                        placeholder="Optional"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.note" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Add planned transaction' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
