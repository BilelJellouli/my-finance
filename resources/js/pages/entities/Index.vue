<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
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
import * as entityRoutes from '@/routes/entities';
import { ENTITY_COLOR_SWATCH } from './colors';

type Entity = {
    id: number;
    name: string;
    type: 'personal' | 'llc';
    color: string;
};

defineProps<{
    entities: Entity[];
}>();

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
                class="flex items-center justify-between gap-3 rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
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
                    <Dialog v-if="entity.type !== 'personal'">
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
        </div>
    </div>
</template>
