<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import PlannedTransactionController from '@/actions/App/Http/Controllers/PlannedTransactionController';
import { ENTITY_COLOR_SWATCH } from '@/pages/entities/colors';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { Label } from '@/components/ui/label';

type DeletableTransaction = {
    id: number;
    direction: 'incoming' | 'outgoing';
    amount: string;
    currency: string;
    purpose: string | null;
    transfer_group_id: string | null;
    owner_entity: { id: number; name: string; type: 'personal' | 'llc'; color: string };
    counterparty: { id: number; name: string; kind: 'internal' | 'external' };
};

const props = defineProps<{
    transaction: DeletableTransaction;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    deletion_reason: '',
});

function submit(): void {
    form.submit({
        ...PlannedTransactionController.destroy(props.transaction.id),
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
        form.reset();
        form.clearErrors();
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-[500px]">
            <form @submit.prevent="submit" class="space-y-5">
                <DialogHeader>
                    <DialogTitle>Delete planned transaction</DialogTitle>
                    <DialogDescription>
                        This is a soft delete — the record is hidden from your list but kept for audit. Tell us why so it's recorded.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 rounded-md border border-sidebar-border/60 bg-muted/40 p-3 text-sm dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs uppercase tracking-wide text-muted-foreground">Entity</span>
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-block size-2.5 shrink-0 rounded-full"
                                :class="ENTITY_COLOR_SWATCH[transaction.owner_entity.color]"
                            />
                            <span>{{ transaction.owner_entity.name }}</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs uppercase tracking-wide text-muted-foreground">{{ transaction.direction === 'incoming' ? 'From' : 'To' }}</span>
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
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs uppercase tracking-wide text-muted-foreground">Amount</span>
                        <span class="font-mono tabular-nums">{{ transaction.amount }} {{ transaction.currency }}</span>
                    </div>
                    <p v-if="transaction.transfer_group_id" class="text-xs text-muted-foreground">
                        Linked transfer — the matching row on the other entity will be deleted too.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="ptx-deletion-reason">Reason for deletion</Label>
                    <textarea
                        id="ptx-deletion-reason"
                        v-model="form.deletion_reason"
                        rows="3"
                        required
                        autofocus
                        placeholder="e.g. Duplicate entry, plan cancelled, paid in another channel…"
                        class="border-input dark:bg-input/30 placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <InputError :message="form.errors.deletion_reason" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" variant="destructive" :disabled="form.processing">
                        Delete planned transaction
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
