<script setup lang="ts">
import { Check, Plus } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';

type ExternalCp = { id: number; name: string };

const props = defineProps<{
    counterpartyId: number | null;
    externalName: string;
    options: ExternalCp[];
    placeholder?: string;
    invalid?: boolean;
}>();

const emit = defineEmits<{
    'update:counterpartyId': [value: number | null];
    'update:externalName': [value: string];
}>();

const query = ref<string>(initialQuery());
const open = ref(false);
const activeIndex = ref(0);
const rootEl = ref<HTMLElement | null>(null);
const inputEl = ref<InstanceType<typeof Input> | null>(null);

function initialQuery(): string {
    if (props.counterpartyId) {
        const found = props.options.find((o) => o.id === props.counterpartyId);

        if (found) {
            return found.name;
        }
    }

    return props.externalName ?? '';
}

const trimmed = computed(() => query.value.trim());

const filtered = computed(() => {
    const q = trimmed.value.toLowerCase();

    if (!q) {
        return props.options.slice(0, 50);
    }

    return props.options.filter((o) => o.name.toLowerCase().includes(q)).slice(0, 50);
});

const exactMatch = computed(() =>
    props.options.find((o) => o.name.toLowerCase() === trimmed.value.toLowerCase()) ?? null,
);

const showCreateOption = computed(() => trimmed.value.length > 0 && !exactMatch.value);

const totalRows = computed(() => filtered.value.length + (showCreateOption.value ? 1 : 0));

watch(query, (next) => {
    activeIndex.value = 0;

    const match = props.options.find((o) => o.name === next);

    if (match) {
        if (props.counterpartyId !== match.id) {
            emit('update:counterpartyId', match.id);
        }

        if (props.externalName !== '') {
            emit('update:externalName', '');
        }

        return;
    }

    if (props.counterpartyId !== null) {
        emit('update:counterpartyId', null);
    }

    if (props.externalName !== next) {
        emit('update:externalName', next);
    }
});

function pickExisting(option: ExternalCp): void {
    query.value = option.name;
    emit('update:counterpartyId', option.id);
    emit('update:externalName', '');
    open.value = false;
}

function pickNew(): void {
    const value = trimmed.value;

    if (!value) {
        return;
    }

    emit('update:counterpartyId', null);
    emit('update:externalName', value);
    open.value = false;
}

function onSelectActive(): void {
    if (activeIndex.value < filtered.value.length) {
        pickExisting(filtered.value[activeIndex.value]);

        return;
    }

    if (showCreateOption.value) {
        pickNew();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        open.value = true;
        activeIndex.value = Math.min(activeIndex.value + 1, Math.max(0, totalRows.value - 1));

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        open.value = true;
        activeIndex.value = Math.max(activeIndex.value - 1, 0);

        return;
    }

    if (event.key === 'Enter') {
        if (open.value && totalRows.value > 0) {
            event.preventDefault();
            onSelectActive();
        }

        return;
    }

    if (event.key === 'Escape') {
        open.value = false;
    }
}

function onFocus(): void {
    open.value = true;

    nextTick(() => {
        activeIndex.value = 0;
    });
}

function onDocumentMouseDown(event: MouseEvent): void {
    if (!rootEl.value) {
        return;
    }

    if (!rootEl.value.contains(event.target as Node)) {
        open.value = false;
    }
}

document.addEventListener('mousedown', onDocumentMouseDown);

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentMouseDown);
});
</script>

<template>
    <div ref="rootEl" class="relative">
        <Input
            ref="inputEl"
            v-model="query"
            :placeholder="placeholder ?? 'Search or add an external…'"
            autocomplete="off"
            spellcheck="false"
            :aria-invalid="invalid ? true : undefined"
            @focus="onFocus"
            @keydown="onKeydown"
        />

        <div
            v-if="open && (filtered.length > 0 || showCreateOption)"
            class="absolute left-0 right-0 top-full z-50 mt-1 max-h-64 overflow-y-auto rounded-md border border-sidebar-border/70 bg-popover p-1 text-sm shadow-md dark:border-sidebar-border"
            role="listbox"
        >
            <button
                v-for="(option, index) in filtered"
                :key="option.id"
                type="button"
                role="option"
                :aria-selected="index === activeIndex"
                class="flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left transition"
                :class="
                    index === activeIndex
                        ? 'bg-accent text-accent-foreground'
                        : 'hover:bg-accent/60'
                "
                @mousedown.prevent="pickExisting(option)"
                @mouseenter="activeIndex = index"
            >
                <span class="truncate">{{ option.name }}</span>
                <Check
                    v-if="counterpartyId === option.id"
                    class="size-3.5 shrink-0 text-muted-foreground"
                />
            </button>

            <button
                v-if="showCreateOption"
                type="button"
                role="option"
                :aria-selected="activeIndex === filtered.length"
                class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left transition"
                :class="
                    activeIndex === filtered.length
                        ? 'bg-accent text-accent-foreground'
                        : 'hover:bg-accent/60'
                "
                @mousedown.prevent="pickNew"
                @mouseenter="activeIndex = filtered.length"
            >
                <Plus class="size-3.5 shrink-0" />
                <span class="truncate">Add new: <span class="font-medium">{{ trimmed }}</span></span>
            </button>
        </div>
    </div>
</template>
