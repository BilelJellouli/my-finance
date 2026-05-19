<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
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
import * as transactionsRoutes from '@/routes/transactions';
import type { TransactionListItem } from '@/types';

const props = defineProps<{ transaction: TransactionListItem }>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({});

function submit(): void {
    form.delete(transactionsRoutes.destroy(props.transaction.id).url, {
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
            <DialogHeader>
                <DialogTitle>Delete this transaction?</DialogTitle>
                <DialogDescription>
                    The amount will no longer count against any linked planned transaction —
                    if it had pushed the planned row to settled, it will revert to planned/overdue.
                </DialogDescription>
            </DialogHeader>

            <div class="rounded-md border border-sidebar-border/60 bg-muted/30 p-3 text-sm dark:border-sidebar-border">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wide text-muted-foreground">Amount</span>
                    <span class="font-mono tabular-nums">{{ transaction.amount }} {{ transaction.currency }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wide text-muted-foreground">Date</span>
                    <span class="font-mono tabular-nums">{{ transaction.occurred_on }}</span>
                </div>
                <div v-if="transaction.from_account" class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wide text-muted-foreground">From</span>
                    <span>{{ transaction.from_account.entity?.name }} — {{ transaction.from_account.name }}</span>
                </div>
                <div v-if="transaction.to_account" class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wide text-muted-foreground">To</span>
                    <span>{{ transaction.to_account.entity?.name }} — {{ transaction.to_account.name }}</span>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="secondary">Cancel</Button>
                </DialogClose>
                <Button type="button" variant="destructive" :disabled="form.processing" @click="submit">
                    Delete transaction
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
