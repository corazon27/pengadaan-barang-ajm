<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '../Button.vue';
import DiscoveryToggle from './DiscoveryToggle.vue';

// Thin URL controller. `discovery` is the server-normalized truth (D1); we build
// every target URL from it plus the one dimension being changed. `page` is never
// added here, so any discovery change naturally resets to page 1. The server
// remains the single authority on discovery — nothing is validated or
// re-normalized client-side.
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

const TKDN_PRESETS = [
    { label: '≥ 30%', value: 30 },
    { label: '≥ 40%', value: 40 },
    { label: '≥ 50%', value: 50 },
];

const sortOptions = [
    { value: 'default', label: 'Terbaru' },
    { value: 'price_asc', label: 'Harga terendah' },
    { value: 'price_desc', label: 'Harga tertinggi' },
    { value: 'tkdn_desc', label: 'TKDN tertinggi' },
];

const searchTerm = ref(props.discovery.q ?? '');

watch(
    () => props.discovery.q,
    (value) => {
        searchTerm.value = value ?? '';
    },
);

// Build a `/katalog` URL from the current discovery state with `overrides`
// applied (null/false/'' values drop the key). `page` is intentionally absent.
const buildUrl = (overrides) => {
    const params = {};

    if (props.discovery.q) params.q = props.discovery.q;
    if (props.discovery.sni) params.sni = '1';
    if (props.discovery.tkdn_min !== null && props.discovery.tkdn_min !== undefined) {
        params.tkdn_min = String(props.discovery.tkdn_min);
    }
    if (props.discovery.availability) params.availability = '1';
    if (props.discovery.sort && props.discovery.sort !== 'default') {
        params.sort = props.discovery.sort;
    }

    for (const [key, value] of Object.entries(overrides)) {
        if (value === null || value === false || value === '') {
            delete params[key];
        } else {
            params[key] = String(value);
        }
    }

    const query = new URLSearchParams(params).toString();

    return query ? `/katalog?${query}` : '/katalog';
};

const submitSearch = () => {
    const term = searchTerm.value.trim();

    router.get(buildUrl({ q: term === '' ? null : term }));
};

const changeSort = (event) => {
    router.get(buildUrl({ sort: event.target.value === 'default' ? null : event.target.value }));
};

const toggleSniHref = computed(() =>
    buildUrl({ sni: props.discovery.sni ? null : '1' }),
);

const toggleAvailabilityHref = computed(() =>
    buildUrl({ availability: props.discovery.availability ? null : '1' }),
);

const presetHref = (value) =>
    buildUrl({ tkdn_min: props.discovery.tkdn_min === value ? null : String(value) });
</script>

<template>
    <div class="mt-8 border-t border-line pt-8 lg:mt-12 lg:pt-10">
        <p class="text-caption font-medium uppercase tracking-[0.2em] text-ink-soft">
            Cari &amp; Filter Katalog
        </p>

        <div
            class="mt-4 flex flex-col gap-5 border-b border-line pb-8 lg:mt-6 lg:flex-row lg:items-end lg:justify-between lg:gap-8"
        >
            <form
                class="flex w-full flex-col gap-2 sm:flex-row sm:items-end"
                role="search"
                @submit.prevent="submitSearch"
            >
                <label for="catalog-search" class="flex flex-col gap-1.5 sm:flex-1">
                    <span class="text-label font-medium text-ink">Cari produk (nama/SKU)</span>
                    <input
                        id="catalog-search"
                        v-model="searchTerm"
                        type="search"
                        autocomplete="off"
                        placeholder="Contoh: laptop, CCTV, meja"
                        class="h-11 w-full rounded-brand-sm border border-line bg-surface px-3.5 text-body text-ink placeholder:text-ink-soft/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
                    />
                </label>
                <Button type="submit" class="shrink-0">Cari</Button>
            </form>

            <div class="flex flex-col gap-1.5">
                <label for="catalog-sort" class="text-label font-medium text-ink">Urutkan</label>
                <select
                    id="catalog-sort"
                    :value="discovery.sort"
                    class="h-11 w-full rounded-brand-sm border border-line bg-surface px-3.5 text-body text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2 sm:w-56"
                    @change="changeSort"
                >
                    <option v-for="option in sortOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-1.5 lg:mt-8">
            <span class="text-label font-medium text-ink">Filter</span>
            <div class="mt-2 flex flex-wrap items-center gap-2.5">
                <DiscoveryToggle
                    name="SNI"
                    :href="toggleSniHref"
                    :active="discovery.sni"
                    visible-label="Dengan SNI"
                />
                <DiscoveryToggle
                    name="tersedia"
                    :href="toggleAvailabilityHref"
                    :active="discovery.availability"
                    visible-label="Tersedia"
                />
                <span class="mx-1 h-5 w-px bg-line" aria-hidden="true"></span>
                <DiscoveryToggle
                    v-for="preset in TKDN_PRESETS"
                    :key="preset.value"
                    :name="`TKDN ${preset.label}`"
                    :href="presetHref(preset.value)"
                    :active="discovery.tkdn_min === preset.value"
                    :visible-label="preset.label"
                />
            </div>
        </div>
    </div>
</template>