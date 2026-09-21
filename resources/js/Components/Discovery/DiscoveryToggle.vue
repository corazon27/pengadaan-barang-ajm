<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

// Discovery filter toggle — a REAL navigation link (each state is a real URL).
// The accessible name carries the filter action/state. These are independent
// filters (several can be active at once), so we MUST NOT use aria-current
// (that is for current-item navigation) nor aria-pressed/role="button"
// (simulated-button semantics). Native anchor behavior: untouched.
const props = defineProps({
    name: { type: String, required: true },
    href: { type: String, required: true },
    active: { type: Boolean, default: false },
    visibleLabel: { type: String, required: true },
});

const accessibleLabel = computed(() =>
    props.active ? `Hapus filter ${props.name}` : `Filter ${props.name}`,
);
</script>

<template>
    <Link
        :href="href"
        :aria-label="accessibleLabel"
        class="inline-flex min-h-11 items-center justify-center rounded-brand-sm border px-3.5 text-label font-medium transition-colors duration-fast focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
        :class="
            active
                ? 'border-brand bg-brand text-inverse'
                : 'border-line bg-surface text-ink hover:border-line-strong/40'
        "
    >
        {{ visibleLabel }}
    </Link>
</template>