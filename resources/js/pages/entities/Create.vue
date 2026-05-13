<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import EntityController from '@/actions/App/Http/Controllers/EntityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as entities from '@/routes/entities';
import { ENTITY_COLOR_RING, ENTITY_COLOR_SWATCH } from './colors';

type ColorOption = { value: string; label: string };

const props = defineProps<{
    colors: ColorOption[];
}>();

const selectedColor = ref<string>(props.colors[0]?.value ?? 'blue');

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Entities', href: entities.index() },
            { title: 'New entity', href: entities.create() },
        ],
    },
});
</script>

<template>
    <Head title="New entity" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="New entity"
            description="Create a new LLC. (The Personal entity is set up automatically.)"
        />

        <Form
            v-bind="EntityController.store.form()"
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
                    placeholder="e.g. Acme LLC"
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
                <Button type="submit" :disabled="processing">Create entity</Button>
                <Button variant="ghost" as-child>
                    <Link :href="entities.index().url">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
