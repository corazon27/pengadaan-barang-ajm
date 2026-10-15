<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    modelValue: { type: [String, null], default: null },
    options: { type: Array, default: () => [] },
    baseUrl: { type: String, required: true },
    label: { type: String, default: 'Status' },
    param: { type: String, default: 'status' },
    params: { type: Object, default: () => ({}) },
});

const value = computed(() => props.modelValue ?? '');

function apply(event) {
    const selected = event.target.value;
    const query = { ...props.params, [props.param]: selected === '' ? undefined : selected };

    router.get(props.baseUrl, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <label class="flex items-center gap-2 text-caption text-ink-soft">
        <span>{{ label }}</span>
        <select
            :value="value"
            class="rounded-brand-sm border border-line bg-surface px-3 py-1.5 text-caption text-ink transition-colors duration-fast hover:border-line-strong/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
            @change="apply"
        >
            <option value="">Semua</option>
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
    </label>
</template>
