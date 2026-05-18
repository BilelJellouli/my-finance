<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import PlannedTransactionController from '@/actions/App/Http/Controllers/PlannedTransactionController';
import InputError from '@/components/InputError.vue';
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

type Option = { value: string; label: string };

type Occurrence = {
    id: number;
    amount: string;
    currency: string;
    due_date: string | null;
    purpose: string | null;
    status: 'planned' | 'settled' | 'overdue' | 'cancelled';
    is_mandatory: boolean;
    note: string | null;
};

const props = defineProps<{
    occurrence: Occurrence;
    statuses: Option[];
    planCurrency: string;
}>();

const open = defineModel<boolean>('open', { default: false });

function fromOccurrence(o: Occurrence) {
    return {
        amount: o.amount,
        currency: o.currency,
        due_date: o.due_date ?? '',
        purpose: o.purpose ?? '',
        status: o.status,
        is_mandatory: o.is_mandatory,
        note: o.note ?? '',
    };
}

const form = useForm(fromOccurrence(props.occurrence));

watch(open, (value) => {
    if (value) {
        form.defaults(fromOccurrence(props.occurrence));
        form.reset();
        form.clearErrors();
    } else {
        form.clearErrors();
    }
});

function submit(): void {
    form.submit(PlannedTransactionController.update(props.occurrence.id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.clearErrors();
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[480px]">
            <form @submit.prevent="submit" class="space-y-5">
                <DialogHeader>
                    <DialogTitle>Edit this occurrence</DialogTitle>
                    <DialogDescription>
                        Adjust just this row. Future rows stay on the current phase amount.
                        To change all future occurrences, use "Change going forward" on the current phase.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-[1fr_120px]">
                    <div class="grid gap-2">
                        <Label for="occ-amount">Amount</Label>
                        <Input
                            id="occ-amount"
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
                        <Label>Currency</Label>
                        <Input :model-value="planCurrency" disabled />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="occ-date">Due date</Label>
                    <Input
                        id="occ-date"
                        v-model="form.due_date"
                        type="date"
                    />
                    <InputError :message="form.errors.due_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="occ-status">Status</Label>
                    <Select v-model="form.status">
                        <SelectTrigger id="occ-status">
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

                <div class="grid gap-2">
                    <Label for="occ-note">Note <span class="text-xs text-muted-foreground">(optional)</span></Label>
                    <Input
                        id="occ-note"
                        v-model="form.note"
                        placeholder="Why this one differs from the plan default…"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.note" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">
                        Save changes
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
