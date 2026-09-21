<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

// Rendered as ordinary (non-live) content — the single result-status line in
// Catalog.vue is the ONLY announcement. This row is the persistent visual
// register of which filters are applied, plus the clear-to-clean reset link.
// `page > 1` alone is pagination, not a filter, so it never shows this row.
const props = defineProps({
    discovery: {
        type: Object,
        default: () => ({
            q: null,
            sni: false,
            tkdn_min: null,
            availability: false,
            sort: 'default',
        }),
    },
});

const hasActiveFilters = computed(
    () =>
        props.discovery.q !== null
        || props.discovery.sni
        || (props.discovery.tkdn_min !== null && props.discovery.tkdn_min !== undefined)
        || props.discovery.availability
        || (props.discovery.sort && props.discovery.sort !== 'default'),
);

const sortLabels = {
    price_asc: 'Harga terendah',
    price_desc: 'Harga tertinggi',
    tkdn_desc: 'TKDN tertinggi',
    newest: 'Terbaru',
};

const sortLabel = computed(() => sortLabels[props.discovery.sort] ?? props.discovery.sort);
</script>

<template>
    <div
        v-if="hasActiveFilters"
        class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ink-soft"
    >
        <span class="text-caption font-medium uppercase tracking-[0.2em] text-ink-soft">
            Filter aktif
        </span>

        <ul class="flex flex-wrap items-center gap-2" aria-label="Filter yang sedang diterapkan">
            <li
                v-if="discovery.q"
                class="inline-flex items-center gap-1.5 rounded-brand-sm bg-surface-muted px-2.5 py-1 text-sm text-ink"
            >
                <span class="h-1 w-1 rounded-full bg-brand" aria-hidden="true"></span>
                Pencarian: “{{ discovery.q }}”
            </li>
            <li
                v-if="discovery.sni"
                class="inline-flex items-center gap-1.5 rounded-brand-sm bg-surface-muted px-2.5 py-1 text-sm text-ink"
            >
                <span class="h-1 w-1 rounded-full bg-brand" aria-hidden="true"></span>
                Dengan SNI
            </li>
            <li
                v-if="discovery.tkdn_min !== null"
                class="inline-flex items-center gap-1.5 rounded-brand-sm bg-surface-muted px-2.5 py-1 text-sm text-ink"
            >
                <span class="h-1 w-1 rounded-full bg-brand" aria-hidden="true"></span>
                TKDN ≥ {{ discovery.tkdn_min }}%
            </li>
            <li
                v-if="discovery.sort && discovery.sort !== 'default'"
                class="inline-flex items-center gap-1.5 rounded-brand-sm bg-surface-muted px-2.5 py-1 text-sm text-ink"
            >
                <span class="h-1 w-1 rounded-full bg-brand" aria-hidden="true"></span>
                Urutan: {{ sortLabel }}
            </li>
            <li
                v-if="discovery.availability"
                class="inline-flex items-center gap-1.5 rounded-brand-sm bg-surface-muted px-2.5 py-1 text-sm text-ink"
            >
                <span class="h-1 w-1 rounded-full bg-brand" aria-hidden="true"></span>
                Tersedia
            </li>
        </ul>

        <Link
            href="/katalog"
            class="inline-flex min-h-11 items-center rounded-brand-sm text-label font-medium text-brand transition-colors duration-fast hover:text-brand-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
        >
            Hapus filter
        </Link>
    </div>
</template>