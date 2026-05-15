<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PlannedTransactionController from '@/actions/App/Http/Controllers/PlannedTransactionController';
import InputError from '@/components/InputError.vue';
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

type EntityOption = { id: number; name: string; type: 'personal' | 'llc'; color: string };
type CurrencyOption = { value: string; label: string; symbol: string };
type DirectionOption = { value: string; label: string };
type StatusOption = { value: string; label: string };
type ExternalCp = { id: number; name: string };

const props = defineProps<{
    entities: EntityOption[];
    directions: DirectionOption[];
    statuses: StatusOption[];
    currencies: CurrencyOption[];
    externalCounterparties: ExternalCp[];
}>();

const open = ref(false);

const defaultEntityId = props.entities[0]?.id ?? 0;
const defaultCurrency = props.currencies[0]?.value ?? 'TND';

const form = useForm({
    owner_entity_id: defaultEntityId,
    counterparty_mode: 'external_new' as 'internal' | 'external_existing' | 'external_new',
    internal_entity_id: null as number | null,
    counterparty_id: null as number | null,
    external_name: '',
    direction: 'outgoing' as 'incoming' | 'outgoing',
    amount: '' as string | number,
    currency: defaultCurrency,
    due_date: '' as string,
    purpose: '',
    status: 'planned' as 'planned' | 'settled' | 'overdue' | 'cancelled',
    is_mandatory: true,
    note: '',
});

const otherEntities = computed(() =>
    props.entities.filter((e) => e.id !== Number(form.owner_entity_id)),
);

watch(
    () => form.owner_entity_id,
    () => {
        if (
            form.internal_entity_id !== null &&
            !otherEntities.value.some((e) => e.id === form.internal_entity_id)
        ) {
            form.internal_entity_id = null;
        }
    },
);

function reset(): void {
    form.reset();
    form.clearErrors();
    form.owner_entity_id = defaultEntityId;
    form.currency = defaultCurrency;
    form.due_date = '';
}

function submit(): void {
    form.submit({
        ...PlannedTransactionController.store(),
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            reset();
        },
    });
}

watch(open, (value) => {
    if (!value) {
        reset();
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button>
                <Plus class="size-4" />
                New planned transaction
            </Button>
        </DialogTrigger>
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[560px]">
            <form @submit.prevent="submit" class="space-y-5">
                <DialogHeader>
                    <DialogTitle>New planned transaction</DialogTitle>
                    <DialogDescription>
                        Money you plan to pay or receive. Pick the entity it belongs to and the other side.
                    </DialogDescription>
                </DialogHeader>

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
                    <Button type="submit" :disabled="form.processing">Add planned transaction</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
