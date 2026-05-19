<script setup lang="ts">
import { Check, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { ENTITY_COLOR_SWATCH } from '@/pages/entities/colors';
import { Input } from '@/components/ui/input';

type AccountOption = {
    id: number;
    name: string;
    currency: string;
    current_balance?: string;
    entity: { id: number; name: string; color: string };
};

const props = defineProps<{
    modelValue: number | null;
    options: AccountOption[];
    placeholder?: string;
    invalid?: boolean;
    disabled?: boolean;
    allowClear?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const query = ref<string>(initialQuery());
const open = ref(false);
const activeIndex = ref(0);
const rootEl = ref<HTMLElement | null>(null);

function initialQuery(): string {
    if (props.modelValue) {
        const found = props.options.find((o) => o.id === props.modelValue);
        if (found) {
            return labelFor(found);
        }
    }
    return '';
}

function labelFor(option: AccountOption): string {
    return `${option.entity.name} — ${option.name} (${option.currency})`;
}

watch(
    () => props.modelValue,
    (val) => {
        if (!val) {
            query.value = '';
            return;
        }
        const found = props.options.find((o) => o.id === val);
        if (found) {
            query.value = labelFor(found);
        }
    },
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) {
        return props.options.slice(0, 50);
    }
    return props.options
        .filter((o) => labelFor(o).toLowerCase().includes(q))
        .slice(0, 50);
});

function pick(option: AccountOption): void {
    query.value = labelFor(option);
    emit('update:modelValue', option.id);
    open.value = false;
}

function clearSelection(): void {
    query.value = '';
    emit('update:modelValue', null);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        open.value = true;
        activeIndex.value = Math.min(activeIndex.value + 1, Math.max(0, filtered.value.length - 1));
        return;
    }
    if (event.key === 'ArrowUp') {
        event.preventDefault();
        open.value = true;
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
        return;
    }
    if (event.key === 'Enter') {
        if (open.value && filtered.value.length > 0) {
            event.preventDefault();
            pick(filtered.value[activeIndex.value]);
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
        // If the typed query no longer matches the selected option, revert it.
        if (props.modelValue) {
            const found = props.options.find((o) => o.id === props.modelValue);
            if (found && query.value !== labelFor(found)) {
                query.value = labelFor(found);
            }
        } else if (query.value !== '') {
            query.value = '';
        }
    }
}

document.addEventListener('mousedown', onDocumentMouseDown);

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentMouseDown);
});
</script>

<template>
    <div ref="rootEl" class="relative">
        <div class="relative">
            <Input
                v-model="query"
                :placeholder="placeholder ?? 'Pick an account…'"
                :disabled="disabled"
                autocomplete="off"
                spellcheck="false"
                :aria-invalid="invalid ? true : undefined"
                @focus="onFocus"
                @keydown="onKeydown"
            />
            <button
                v-if="allowClear && modelValue !== null && !disabled"
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                aria-label="Clear"
                @mousedown.prevent="clearSelection"
            >
                <X class="size-3.5" />
            </button>
        </div>

        <div
            v-if="open && filtered.length > 0"
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
                :class="index === activeIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/60'"
                @mousedown.prevent="pick(option)"
                @mouseenter="activeIndex = index"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <span
                        class="inline-block size-2.5 shrink-0 rounded-full"
                        :class="ENTITY_COLOR_SWATCH[option.entity.color] ?? 'bg-slate-300'"
                    />
                    <span class="truncate">
                        <span class="text-muted-foreground">{{ option.entity.name }} —</span>
                        <span class="font-medium"> {{ option.name }}</span>
                        <span class="text-muted-foreground"> ({{ option.currency }})</span>
                    </span>
                </span>
                <span class="flex shrink-0 items-center gap-2">
                    <span
                        v-if="option.current_balance !== undefined"
                        class="font-mono text-xs tabular-nums text-muted-foreground"
                    >
                        {{ option.current_balance }} {{ option.currency }}
                    </span>
                    <Check
                        v-if="modelValue === option.id"
                        class="size-3.5 shrink-0 text-muted-foreground"
                    />
                </span>
            </button>
        </div>
    </div>
</template>
