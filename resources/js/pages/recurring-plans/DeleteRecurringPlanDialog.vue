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
    planLabel: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({ reason: '' });

watch(open, (value) => {
    if (value) {
        form.defaults({ reason: '' });
        form.reset();
        form.clearErrors();
    }
});

function submit(): void {
    form.submit(RecurringPlanController.destroy(props.planId), {
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
                    <DialogTitle>Delete "{{ planLabel }}"?</DialogTitle>
                    <DialogDescription>
                        Future planned rows will be cancelled. Past recorded transactions stay as history. The plan is archived (soft-deleted) — not permanently destroyed.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="del-reason">Reason <span class="text-xs text-muted-foreground">(optional)</span></Label>
                    <Input
                        id="del-reason"
                        v-model="form.reason"
                        placeholder="Lease ended, switched providers…"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.reason" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" variant="destructive" :disabled="form.processing">
                        Delete plan
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
