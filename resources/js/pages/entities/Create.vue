<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EntityController from '@/actions/App/Http/Controllers/EntityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import * as entities from '@/routes/entities';
import { ENTITY_COLOR_RING, ENTITY_COLOR_SWATCH } from './colors';

type ColorOption = { value: string; label: string };
type CurrencyOption = { value: string; label: string; symbol: string };

const props = defineProps<{
    colors: ColorOption[];
    currencies: CurrencyOption[];
}>();

type AccountRow = {
    name: string;
    currency: string;
    amount: number;
    is_main: boolean;
};

const defaultCurrency = props.currencies[0]?.value ?? 'TND';

const form = useForm<{
    name: string;
    color: string;
    accounts: AccountRow[];
}>({
    name: '',
    color: props.colors[0]?.value ?? 'blue',
    accounts: [{ name: 'Main', currency: defaultCurrency, amount: 0, is_main: true }],
});

const step = ref<1 | 2>(1);

const canProceedFromStep1 = computed(() => form.name.trim().length > 0 && !!form.color);
const mainCount = computed(() => form.accounts.filter((account) => account.is_main).length);
const canSubmit = computed(
    () =>
        form.accounts.length > 0 &&
        mainCount.value === 1 &&
        form.accounts.every((account) => account.name.trim().length > 0 && account.currency),
);

function toStep(target: 1 | 2): void {
    step.value = target;
}

function addAccount(): void {
    form.accounts.push({
        name: '',
        currency: defaultCurrency,
        amount: 0,
        is_main: false,
    });
}

function removeAccount(index: number): void {
    const wasMain = form.accounts[index]?.is_main ?? false;
    form.accounts.splice(index, 1);
    if (wasMain && form.accounts.length > 0) {
        form.accounts[0].is_main = true;
    }
}

function setMain(index: number): void {
    form.accounts.forEach((account, i) => {
        account.is_main = i === index;
    });
}

function submit(): void {
    form.submit(EntityController.store());
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Entities', href: entities.index() },
            { title: 'New entity', href: entities.create() },
        ],
    },
});
</script>

<template>
    <Head title="New entity" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="New entity"
            description="Set up an LLC and its accounts in two quick steps."
        />

        <!-- Step indicator -->
        <ol class="flex items-center gap-3 text-sm" aria-label="Wizard steps">
            <li class="flex items-center gap-2">
                <span
                    class="flex size-6 items-center justify-center rounded-full border text-xs font-semibold"
                    :class="
                        step === 1
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-emerald-500 bg-emerald-500 text-white'
                    "
                >
                    <Check v-if="step > 1" class="size-3.5" />
                    <span v-else>1</span>
                </span>
                <span :class="step === 1 ? 'font-medium' : 'text-muted-foreground'">Entity</span>
            </li>
            <li class="h-px w-8 bg-border" aria-hidden="true" />
            <li class="flex items-center gap-2">
                <span
                    class="flex size-6 items-center justify-center rounded-full border text-xs font-semibold"
                    :class="
                        step === 2
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-input bg-background text-muted-foreground'
                    "
                >
                    2
                </span>
                <span :class="step === 2 ? 'font-medium' : 'text-muted-foreground'">Accounts</span>
            </li>
        </ol>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">
            <!-- Step 1: Entity -->
            <section v-show="step === 1" class="space-y-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        required
                        autocomplete="off"
                        placeholder="e.g. Acme LLC"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label>Color</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="color in colors"
                            :key="color.value"
                            type="button"
                            @click="form.color = color.value"
                            class="size-8 rounded-full ring-offset-2 ring-offset-background transition focus:outline-none focus-visible:ring-2"
                            :class="[
                                ENTITY_COLOR_SWATCH[color.value],
                                form.color === color.value ? `ring-2 ${ENTITY_COLOR_RING[color.value]}` : '',
                            ]"
                            :aria-label="color.label"
                            :aria-pressed="form.color === color.value"
                        />
                    </div>
                    <InputError :message="form.errors.color" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <Button type="button" :disabled="!canProceedFromStep1" @click="toStep(2)">
                        Next
                        <ArrowRight class="size-4" />
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link :href="entities.index().url">Cancel</Link>
                    </Button>
                </div>
            </section>

            <!-- Step 2: Accounts -->
            <section v-show="step === 2" class="space-y-4">
                <div class="space-y-1">
                    <Label>Accounts</Label>
                    <p class="text-sm text-muted-foreground">
                        Add at least one account. Mark exactly one as the main account.
                    </p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(account, index) in form.accounts"
                        :key="index"
                        class="grid gap-3 rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border sm:grid-cols-[1fr_140px_140px_auto_auto] sm:items-end"
                    >
                        <div class="grid gap-2">
                            <Label :for="`account-name-${index}`">Account name</Label>
                            <Input
                                :id="`account-name-${index}`"
                                v-model="account.name"
                                placeholder="e.g. Main, Savings, Operating"
                                autocomplete="off"
                            />
                            <InputError :message="(form.errors as Record<string, string>)[`accounts.${index}.name`]" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`account-amount-${index}`">Amount</Label>
                            <Input
                                :id="`account-amount-${index}`"
                                v-model="account.amount"
                                type="number"
                                step="0.01"
                                inputmode="decimal"
                                autocomplete="off"
                            />
                            <InputError :message="(form.errors as Record<string, string>)[`accounts.${index}.amount`]" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`account-currency-${index}`">Currency</Label>
                            <Select v-model="account.currency">
                                <SelectTrigger :id="`account-currency-${index}`">
                                    <SelectValue placeholder="Pick a currency" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="currency in currencies"
                                        :key="currency.value"
                                        :value="currency.value"
                                    >
                                        {{ currency.value }} — {{ currency.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="(form.errors as Record<string, string>)[`accounts.${index}.currency`]" />
                        </div>

                        <label
                            :for="`account-main-${index}`"
                            class="flex items-center gap-2 text-sm select-none"
                        >
                            <Checkbox
                                :id="`account-main-${index}`"
                                :model-value="account.is_main"
                                @update:model-value="(value) => value && setMain(index)"
                            />
                            <span>Main</span>
                        </label>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="`Remove account ${index + 1}`"
                            :disabled="form.accounts.length === 1"
                            @click="removeAccount(index)"
                        >
                            <Trash2 class="size-4 text-destructive" />
                        </Button>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <Button type="button" variant="outline" @click="addAccount">
                        <Plus class="size-4" />
                        Add account
                    </Button>
                    <p
                        v-if="mainCount !== 1"
                        class="text-xs text-destructive"
                    >
                        Exactly one account must be marked as main.
                    </p>
                </div>

                <InputError :message="form.errors.accounts" />

                <div class="flex items-center gap-3 pt-2">
                    <Button type="button" variant="outline" @click="toStep(1)">
                        <ArrowLeft class="size-4" />
                        Back
                    </Button>
                    <Button type="submit" :disabled="form.processing || !canSubmit">
                        Create entity
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link :href="entities.index().url">Cancel</Link>
                    </Button>
                </div>
            </section>
        </form>
    </div>
</template>
