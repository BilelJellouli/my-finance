<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ref } from 'vue';
import EntityController from '@/actions/App/Http/Controllers/EntityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as entities from '@/routes/entities';
import AccountEditCard from './AccountEditCard.vue';
import AddAccountDialog from './AddAccountDialog.vue';
import { ENTITY_COLOR_RING, ENTITY_COLOR_SWATCH } from './colors';

type Account = {
    id: number;
    name: string;
    currency: string;
    amount: string | number;
    is_main: boolean;
};

type Entity = {
    id: number;
    name: string;
    type: 'personal' | 'llc';
    color: string;
    accounts: Account[];
};

type ColorOption = { value: string; label: string };
type CurrencyOption = { value: string; label: string; symbol: string };

const props = defineProps<{
    entity: Entity;
    colors: ColorOption[];
    currencies: CurrencyOption[];
}>();

const selectedColor = ref<string>(props.entity.color);
const openAccountId = ref<number | null>(null);

setLayoutProps({
    breadcrumbs: [
        { title: 'Entities', href: entities.index() },
        { title: 'Edit entity', href: entities.edit(props.entity.id) },
    ],
});
</script>

<template>
    <Head :title="`Edit ${entity.name}`" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            :title="`Edit ${entity.name}`"
            :description="entity.type === 'personal' ? 'Your personal entity. Type cannot be changed.' : 'Update this LLC.'"
        />

        <Form
            v-bind="EntityController.update.form(entity.id)"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    autocomplete="off"
                    :default-value="entity.name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label>Color</Label>
                <input type="hidden" name="color" :value="selectedColor" />
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="color in colors"
                        :key="color.value"
                        type="button"
                        @click="selectedColor = color.value"
                        class="size-8 rounded-full ring-offset-2 ring-offset-background transition focus:outline-none focus-visible:ring-2"
                        :class="[
                            ENTITY_COLOR_SWATCH[color.value],
                            selectedColor === color.value ? `ring-2 ${ENTITY_COLOR_RING[color.value]}` : '',
                        ]"
                        :aria-label="color.label"
                        :aria-pressed="selectedColor === color.value"
                    />
                </div>
                <InputError :message="errors.color" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">Save</Button>
                <Button variant="ghost" as-child>
                    <Link :href="entities.index().url">Cancel</Link>
                </Button>
            </div>
        </Form>

        <section class="max-w-xl space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <h2 class="text-base font-semibold">Accounts</h2>
                    <p class="text-sm text-muted-foreground">
                        Click an account to edit its amount and currency.
                    </p>
                </div>
                <AddAccountDialog :entity-id="entity.id" :currencies="currencies" />
            </div>

            <ul class="flex flex-col gap-2">
                <li v-for="account in entity.accounts" :key="account.id">
                    <AccountEditCard
                        :account="account"
                        :currencies="currencies"
                        :open="openAccountId === account.id"
                        @update:open="(open) => (openAccountId = open ? account.id : null)"
                    />
                </li>
            </ul>
        </section>
    </div>
</template>
