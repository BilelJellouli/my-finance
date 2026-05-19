<script setup lang="ts">
import { Form, useForm } from '@inertiajs/vue3';
import { ChevronDown, Star, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/AccountController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
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

type Account = {
    id: number;
    name: string;
    currency: string;
    amount: string | number;
    current_balance: string;
    is_main: boolean;
};

type CurrencyOption = { value: string; label: string; symbol: string };

const props = defineProps<{
    account: Account;
    currencies: CurrencyOption[];
    open: boolean;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();

const initialAmount = typeof props.account.amount === 'string'
    ? parseFloat(props.account.amount) || 0
    : props.account.amount;

const form = useForm({
    currency: props.account.currency,
    amount: initialAmount,
});

function formatAmount(amount: string | number): string {
    const n = typeof amount === 'string' ? parseFloat(amount) : amount;

    if (Number.isNaN(n)) {
return '0.00';
}

    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function submit(): void {
    form.submit(AccountController.update(props.account.id), {
        preserveScroll: true,
    });
}

const deleteDialogOpen = ref(false);
</script>

<template>
    <Collapsible
        :open="open"
        @update:open="(value) => emit('update:open', value)"
        class="rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
    >
        <CollapsibleTrigger
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
            :aria-label="`Edit ${account.name}`"
        >
            <div class="flex min-w-0 items-center gap-2">
                <Star
                    v-if="account.is_main"
                    class="size-3.5 shrink-0 fill-amber-400 text-amber-400"
                />
                <span v-else class="inline-block size-3.5 shrink-0" aria-hidden="true" />
                <span class="truncate font-medium">{{ account.name }}</span>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="font-mono tabular-nums">{{ formatAmount(account.current_balance) }}</span>
                <span class="rounded-md bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground">
                    {{ account.currency }}
                </span>
                <ChevronDown
                    class="size-4 transition-transform"
                    :class="open ? 'rotate-180' : ''"
                />
            </div>
        </CollapsibleTrigger>
        <CollapsibleContent class="border-t border-sidebar-border/60 px-4 pb-4 pt-3 dark:border-sidebar-border">
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-[1fr_180px] sm:items-end">
                    <div class="grid gap-2">
                        <Label :for="`account-${account.id}-amount`">Opening balance</Label>
                        <Input
                            :id="`account-${account.id}-amount`"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            inputmode="decimal"
                            autocomplete="off"
                        />
                        <p class="text-xs text-muted-foreground">
                            Current balance: <span class="font-mono tabular-nums">{{ formatAmount(account.current_balance) }} {{ account.currency }}</span> after recorded transactions.
                        </p>
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`account-${account.id}-currency`">Currency</Label>
                        <Select v-model="form.currency">
                            <SelectTrigger :id="`account-${account.id}-currency`">
                                <SelectValue />
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
                        <InputError :message="form.errors.currency" />
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <Button type="submit" :disabled="form.processing">Save account</Button>

                    <Dialog v-if="!account.is_main" v-model:open="deleteDialogOpen">
                        <DialogTrigger as-child>
                            <Button type="button" variant="ghost" size="sm">
                                <Trash2 class="size-4 text-destructive" />
                                Delete
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="AccountController.destroy.form(account.id)"
                                :options="{ preserveScroll: true }"
                                @success="deleteDialogOpen = false"
                                class="space-y-6"
                                v-slot="{ processing: deleteProcessing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>Delete {{ account.name }}?</DialogTitle>
                                    <DialogDescription>
                                        This account will be permanently removed.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button type="button" variant="secondary">Cancel</Button>
                                    </DialogClose>
                                    <Button type="submit" variant="destructive" :disabled="deleteProcessing">
                                        Delete account
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </form>
        </CollapsibleContent>
    </Collapsible>
</template>
