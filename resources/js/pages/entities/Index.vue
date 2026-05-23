<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CircleDollarSign, Pencil, Plus, Star, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EntityController from '@/actions/App/Http/Controllers/EntityController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
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
import TransactionDialog from '@/pages/transactions/TransactionDialog.vue';
import * as accountRoutes from '@/routes/accounts';
import * as entityRoutes from '@/routes/entities';
import type { EntityWithAccounts } from '@/types';
import { ENTITY_COLOR_SWATCH } from './colors';

type Account = {
    id: number;
    name: string;
    currency: string;
    amount: string | number;
    current_balance: string;
    is_main: boolean;
};

function formatAmount(amount: string | number): string {
    const n = typeof amount === 'string' ? parseFloat(amount) : amount;

    if (Number.isNaN(n)) {
return '0.00';
}

    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

type Entity = {
    id: number;
    name: string;
    type: 'personal' | 'llc';
    color: string;
    accounts: Account[];
};

type Option = { value: string; label: string };

const props = defineProps<{
    entities: Entity[];
    transaction_options: {
        kinds: Option[];
        currencies: { value: string; label: string; symbol: string }[];
        external_counterparties: { id: number; name: string }[];
    };
}>();

const deletingEntityId = ref<number | null>(null);
const recordOpen = ref(false);
const recordFromAccountId = ref<number | null>(null);

const dialogEntities = computed<EntityWithAccounts[]>(() =>
    props.entities.map((entity) => ({
        id: entity.id,
        name: entity.name,
        type: entity.type,
        color: entity.color,
        accounts: entity.accounts.map((account) => ({
            id: account.id,
            name: account.name,
            currency: account.currency,
            current_balance: account.current_balance,
        })),
    })),
);

function openRecord(accountId: number): void {
    recordFromAccountId.value = accountId;
    recordOpen.value = true;
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Entities',
                href: entityRoutes.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Entities" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Entities"
                description="Your personal finances and LLCs."
            />
            <Button as-child>
                <Link :href="entityRoutes.create().url">
                    <Plus class="size-4" />
                    New entity
                </Link>
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="entity in entities"
                :key="entity.id"
                class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="inline-block size-3 shrink-0 rounded-full"
                            :class="ENTITY_COLOR_SWATCH[entity.color]"
                            :title="entity.color"
                        />
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ entity.name }}</div>
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">
                                {{ entity.type === 'personal' ? 'Personal' : 'LLC' }}
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button variant="ghost" size="icon" as-child>
                            <Link :href="entityRoutes.edit(entity.id).url" :aria-label="`Edit ${entity.name}`">
                                <Pencil class="size-4" />
                            </Link>
                        </Button>
                        <Dialog
                            v-if="entity.type !== 'personal'"
                            :open="deletingEntityId === entity.id"
                            @update:open="(value) => (deletingEntityId = value ? entity.id : null)"
                        >
                            <DialogTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Delete ${entity.name}`"
                                >
                                    <Trash2 class="size-4 text-destructive" />
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    v-bind="EntityController.destroy.form(entity.id)"
                                    :options="{ preserveScroll: true }"
                                    @success="deletingEntityId = null"
                                    class="space-y-6"
                                    v-slot="{ processing }"
                                >
                                    <DialogHeader>
                                        <DialogTitle>Delete {{ entity.name }}?</DialogTitle>
                                        <DialogDescription>
                                            This entity and any data attached to it will be permanently removed.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button type="button" variant="secondary">Cancel</Button>
                                        </DialogClose>
                                        <Button type="submit" variant="destructive" :disabled="processing">
                                            Delete entity
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                <ul
                    v-if="entity.accounts.length > 0"
                    class="flex flex-col gap-1.5 border-t border-sidebar-border/60 pt-3 dark:border-sidebar-border"
                >
                    <li
                        v-for="account in entity.accounts"
                        :key="account.id"
                        class="flex items-center justify-between gap-2 text-sm"
                    >
                        <Link
                            :href="accountRoutes.show(account.id).url"
                            class="flex min-w-0 items-center gap-2 hover:underline"
                        >
                            <Star
                                v-if="account.is_main"
                                class="size-3.5 shrink-0 fill-amber-400 text-amber-400"
                                :aria-label="`Main account`"
                            />
                            <span v-else class="inline-block size-3.5 shrink-0" aria-hidden="true" />
                            <span class="truncate">{{ account.name }}</span>
                        </Link>
                        <div class="flex items-center gap-2">
                            <span class="font-mono tabular-nums">{{ formatAmount(account.current_balance) }}</span>
                            <span class="rounded-md bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground">
                                {{ account.currency }}
                            </span>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-7"
                                :aria-label="`Record transaction on ${account.name}`"
                                @click="openRecord(account.id)"
                            >
                                <CircleDollarSign class="size-4" />
                            </Button>
                        </div>
                    </li>
                </ul>
                <p
                    v-else
                    class="border-t border-sidebar-border/60 pt-3 text-xs text-muted-foreground dark:border-sidebar-border"
                >
                    No accounts yet.
                </p>
            </div>
        </div>

        <TransactionDialog
            v-if="recordFromAccountId !== null"
            :key="`record-${recordFromAccountId}`"
            v-model:open="recordOpen"
            :entities="dialogEntities"
            :kinds="transaction_options.kinds"
            :currencies="transaction_options.currencies"
            :external-counterparties="transaction_options.external_counterparties"
            :default-from-account-id="recordFromAccountId"
        />
    </div>
</template>
