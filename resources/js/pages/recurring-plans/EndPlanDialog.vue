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

const props = defineProps<{
    planId: number;
}>();

const open = defineModel<boolean>('open', { default: false });

function todayIso(): string {
    return new Date().toISOString().slice(0, 10);
}

const form = useForm({ ends_on: todayIso() });

watch(open, (value) => {
    if (value) {
        form.defaults({ ends_on: todayIso() });
        form.reset();
        form.clearErrors();
    }
});

function submit(): void {
    form.submit(RecurringPlanController.end(props.planId), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-[440px]">
            <form @submit.prevent="submit" class="space-y-5">
                <DialogHeader>
                    <DialogTitle>End recurring plan</DialogTitle>
                    <DialogDescription>
                        No more planned transactions will be generated after the end date. Future planned rows past that date are cancelled. Past rows stay as history.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="end-date">End date</Label>
                    <Input
                        id="end-date"
                        v-model="form.ends_on"
                        type="date"
                        required
                    />
                    <InputError :message="form.errors.ends_on" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" variant="destructive" :disabled="form.processing">
                        End plan
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
