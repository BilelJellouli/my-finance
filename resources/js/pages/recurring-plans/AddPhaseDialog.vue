<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import RecurringPlanController from '@/actions/App/Http/Controllers/RecurringPlanController';
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

type CurrentPhase = {
    id: number;
    amount: string;
    frequency: 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'yearly';
    interval_step: number;
    anchor_day: number | null;
};

const props = defineProps<{
    planId: number;
    currentPhase: CurrentPhase | null;
    frequencies: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

function todayIso(): string {
    return new Date().toISOString().slice(0, 10);
}

function blankForm() {
    return {
        amount: props.currentPhase?.amount ?? '',
        frequency: props.currentPhase?.frequency ?? 'monthly',
        interval_step: props.currentPhase?.interval_step ?? 1,
        anchor_day: props.currentPhase?.anchor_day ?? null,
        effective_from: todayIso(),
        reason: '',
    };
}

const form = useForm(blankForm());

watch(open, (value) => {
    if (value) {
        form.defaults(blankForm());
        form.reset();
        form.clearErrors();
    } else {
        form.clearErrors();
    }
});

function submit(): void {
    form.submit(RecurringPlanController.addPhase(props.planId), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
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
                    <DialogTitle>Change going forward</DialogTitle>
                    <DialogDescription>
                        Close the current phase and start a new one. Past materialized rows stay untouched; future planned rows are regenerated with the new amount.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-[1fr_120px_120px]">
                    <div class="grid gap-2">
                        <Label for="ph-amount">Amount per occurrence</Label>
                        <Input
                            id="ph-amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            inputmode="decimal"
                            required
                        />
                        <InputError :message="form.errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ph-interval">Every</Label>
                        <Input
                            id="ph-interval"
                            v-model.number="form.interval_step"
                            type="number"
                            min="1"
                            max="52"
                        />
                        <InputError :message="form.errors.interval_step" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ph-anchor">Day</Label>
                        <Input
                            id="ph-anchor"
                            v-model.number="form.anchor_day"
                            type="number"
                            min="0"
                            max="31"
                            placeholder="—"
                        />
                        <InputError :message="form.errors.anchor_day" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="ph-frequency">Frequency</Label>
                    <Select v-model="form.frequency">
                        <SelectTrigger id="ph-frequency">
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
                    <Label for="ph-effective">Effective from</Label>
                    <Input
                        id="ph-effective"
                        v-model="form.effective_from"
                        type="date"
                        required
                    />
                    <p class="text-xs text-muted-foreground">
                        The current phase will end the day before. All upcoming planned rows from this date onwards will be regenerated.
                    </p>
                    <InputError :message="form.errors.effective_from" />
                </div>

                <div class="grid gap-2">
                    <Label for="ph-reason">Reason <span class="text-xs text-muted-foreground">(optional)</span></Label>
                    <Input
                        id="ph-reason"
                        v-model="form.reason"
                        placeholder="Rent increase, promo ended…"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.reason" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">
                        Apply going forward
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
