<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import RecurringPlanController from '@/actions/App/Http/Controllers/RecurringPlanController';
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
import ExternalCounterpartyCombobox from '@/pages/planned-transactions/ExternalCounterpartyCombobox.vue';

type EntityOption = {
    id: number;
    name: string;
    type: 'personal' | 'llc';
    color: string;
    accounts: { id: number; name: string; currency: string }[];
};
type CurrencyOption = { value: string; label: string; symbol: string };
type Option = { value: string; label: string };
type ExternalCp = { id: number; name: string };

const props = defineProps<{
    entities: EntityOption[];
    directions: Option[];
    currencies: CurrencyOption[];
    frequencies: Option[];
    externalCounterparties: ExternalCp[];
}>();

const open = defineModel<boolean>('open', { default: false });

const defaultEntityId = props.entities[0]?.id ?? 0;
const defaultCurrency = props.currencies[0]?.value ?? 'TND';

type FormShape = {
    owner_entity_id: number;
    account_id: number | null;
    counterparty_mode: 'internal' | 'external';
    internal_entity_id: number | null;
    counterparty_id: number | null;
    external_name: string;
    direction: 'incoming' | 'outgoing';
    currency: string;
    label: string;
    purpose: string;
    is_mandatory: boolean;
    starts_on: string;
    ends_on: string;
    note: string;
    amount: string | number;
    frequency: 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'yearly';
    interval_step: number;
    anchor_day: number | null;
};

function todayIso(): string {
    return new Date().toISOString().slice(0, 10);
}

function blankForm(): FormShape {
    return {
        owner_entity_id: defaultEntityId,
        account_id: null,
        counterparty_mode: 'external',
        internal_entity_id: null,
        counterparty_id: null,
        external_name: '',
        direction: 'outgoing',
        currency: defaultCurrency,
        label: '',
        purpose: '',
        is_mandatory: true,
        starts_on: todayIso(),
        ends_on: '',
        note: '',
        amount: '',
        frequency: 'monthly',
        interval_step: 1,
        anchor_day: null,
    };
}

const form = useForm<FormShape>(blankForm());

const ownerEntity = computed(() =>
    props.entities.find((e) => e.id === Number(form.owner_entity_id)) ?? null,
);

const accountOptions = computed(() => ownerEntity.value?.accounts ?? []);

const otherEntities = computed(() =>
    props.entities.filter((e) => e.id !== Number(form.owner_entity_id)),
);

watch(
    () => form.owner_entity_id,
    () => {
        if (
            form.account_id !== null &&
            !accountOptions.value.some((a) => a.id === form.account_id)
        ) {
            form.account_id = null;
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
    form.submit(RecurringPlanController.store(), {
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
        form.defaults(blankForm());
        form.reset();
        form.clearErrors();
    } else {
        form.clearErrors();
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button>
                <Plus class="size-4" />
                New recurring plan
            </Button>
        </DialogTrigger>
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[600px]">
            <form @submit.prevent="submit" class="space-y-5">
                <DialogHeader>
                    <DialogTitle>New recurring plan</DialogTitle>
                    <DialogDescription>
                        A rule that generates planned transactions on a schedule. Set the cadence below; you can change the amount going forward at any time.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="rp-label">Label</Label>
                    <Input
                        id="rp-label"
                        v-model="form.label"
                        placeholder="Apartment rent, Gym, Car loan…"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.label" />
                </div>

                <div class="grid gap-2">
                    <Label for="rp-owner">Entity</Label>
                    <Select v-model="form.owner_entity_id">
                        <SelectTrigger id="rp-owner">
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
                    <Label for="rp-account">Account <span class="text-xs text-muted-foreground">(optional — which account funds this)</span></Label>
                    <Select v-model="form.account_id">
                        <SelectTrigger id="rp-account">
                            <SelectValue placeholder="No specific account" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="a in accountOptions"
                                :key="a.id"
                                :value="a.id"
                            >
                                {{ a.name }} ({{ a.currency }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.account_id" />
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
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="rounded-md border px-3 py-2 text-xs transition"
                            :class="
                                form.counterparty_mode === 'external'
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-sidebar-border/70 hover:bg-muted'
                            "
                            @click="form.counterparty_mode = 'external'"
                        >
                            External
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

                    <div v-if="form.counterparty_mode === 'external'" class="grid gap-1.5">
                        <ExternalCounterpartyCombobox
                            :counterparty-id="form.counterparty_id"
                            :external-name="form.external_name"
                            :options="externalCounterparties"
                            :invalid="!!form.errors.external_name || !!form.errors.counterparty_id"
                            @update:counterparty-id="form.counterparty_id = $event"
                            @update:external-name="form.external_name = $event"
                        />
                        <InputError :message="form.errors.external_name || form.errors.counterparty_id" />
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
                        <InputError :message="form.errors.internal_entity_id" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-[1fr_140px]">
                    <div class="grid gap-2">
                        <Label for="rp-amount">Amount per occurrence</Label>
                        <Input
                            id="rp-amount"
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
                        <Label for="rp-currency">Currency</Label>
                        <Select v-model="form.currency">
                            <SelectTrigger id="rp-currency">
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

                <div class="grid gap-3 sm:grid-cols-[1fr_100px_100px]">
                    <div class="grid gap-2">
                        <Label for="rp-frequency">Frequency</Label>
                        <Select v-model="form.frequency">
                            <SelectTrigger id="rp-frequency">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="f in frequencies"
                                    :key="f.value"
                                    :value="f.value"
                                >
                                    {{ f.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.frequency" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="rp-interval">Every</Label>
                        <Input
                            id="rp-interval"
                            v-model.number="form.interval_step"
                            type="number"
                            min="1"
                            max="52"
                        />
                        <InputError :message="form.errors.interval_step" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="rp-anchor">Day <span class="text-xs text-muted-foreground">(opt)</span></Label>
                        <Input
                            id="rp-anchor"
                            v-model.number="form.anchor_day"
                            type="number"
                            min="0"
                            max="31"
                            placeholder="—"
                        />
                        <InputError :message="form.errors.anchor_day" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="rp-starts">Starts on</Label>
                        <Input
                            id="rp-starts"
                            v-model="form.starts_on"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.starts_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="rp-ends">Ends on <span class="text-xs text-muted-foreground">(optional)</span></Label>
                        <Input
                            id="rp-ends"
                            v-model="form.ends_on"
                            type="date"
                        />
                        <InputError :message="form.errors.ends_on" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="rp-purpose">Purpose</Label>
                        <Input
                            id="rp-purpose"
                            v-model="form.purpose"
                            placeholder="Rent, Salary, Tax…"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.purpose" />
                    </div>
                    <label
                        for="rp-mandatory"
                        class="flex items-center gap-2 pt-7 text-sm select-none"
                    >
                        <Checkbox id="rp-mandatory" v-model="form.is_mandatory" />
                        <span>Mandatory (uncheck if flexible)</span>
                    </label>
                </div>

                <div class="grid gap-2">
                    <Label for="rp-note">Note</Label>
                    <Input
                        id="rp-note"
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
                        Add recurring plan
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
