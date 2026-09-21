<script setup>
import { Link } from '@inertiajs/vue3';

// Calm, factual empty state — no fabricated catalog/metrics/CTAs.
// Default copy covers a genuinely empty catalog; when discovery filters are
// active, Catalog.vue passes the discovery-aware copy + a reset action link.
defineProps({
    title: { type: String, default: 'Katalog publik sedang kosong' },
    message: {
        type: String,
        default: 'Produk yang tersedia untuk kebutuhan pengadaan akan tampil di bagian ini.',
    },
    actionHref: { type: [String, null], default: null },
    actionLabel: { type: String, default: '' },
});
</script>

<template>
    <div
        role="status"
        class="mt-12 flex flex-col items-center justify-center rounded-brand-md border border-dashed border-line px-6 py-16 text-center lg:mt-14"
    >
        <span class="relative block h-6 w-6 border border-ink/20">
            <span
                class="absolute left-1/2 top-1/2 h-1.5 w-1.5 -translate-x-1/2 -translate-y-1/2 bg-brand"
            ></span>
        </span>
        <h2 class="mt-5 text-h3 font-semibold tracking-tight text-ink">{{ title }}</h2>
        <p class="mt-2 max-w-[42ch] text-sm text-ink-soft">{{ message }}</p>
        <Link
            v-if="actionHref"
            :href="actionHref"
            class="mt-6 inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse transition duration-normal hover:bg-brand-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2 active:scale-[0.98]"
        >
            {{ actionLabel }}
        </Link>
    </div>
</template>