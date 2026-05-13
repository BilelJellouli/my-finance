<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/AccountController';
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

type CurrencyOption = { value: string; label: string; symbol: string };

const props = defineProps<{
    entityId: number;
    currencies: CurrencyOption[];
}>();

const defaultCurrency = props.currencies[0]?.value ?? 'TND';
const open = ref(false);

const form = useForm({
    name: '',
    currency: defaultCurrency,
    amount: 0,
    is_main: false,
});

function submit(): void {
    form.submit({
        ...AccountController.store(props.entityId),
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">
                <Plus class="size-4" />
                Add account
            </Button>
        </DialogTrigger>
        <DialogContent>
            <form @submit.prevent="submit" class="space-y-5">
                <DialogHeader>
                    <DialogTitle>Add account</DialogTitle>
                    <DialogDescription>
                        Create another account on this entity.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="add-account-name">Account name</Label>
                    <Input
                        id="add-account-name"
                        v-model="form.name"
                        placeholder="e.g. Savings, Reserve"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-3 sm:grid-cols-[1fr_180px]">
                    <div class="grid gap-2">
                        <Label for="add-account-amount">Amount</Label>
                        <Input
                            id="add-account-amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            inputmode="decimal"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="add-account-currency">Currency</Label>
                        <Select v-model="form.currency">
                            <SelectTrigger id="add-account-currency">
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

                <label for="add-account-main" class="flex items-center gap-2 text-sm select-none">
                    <Checkbox id="add-account-main" v-model="form.is_main" />
                    <span>Make this the main account</span>
                </label>
                <p
                    v-if="form.is_main"
                    class="-mt-3 text-xs text-muted-foreground"
                >
                    The current main account will be demoted.
                </p>
                <InputError :message="form.errors.is_main" />

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">Add account</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
