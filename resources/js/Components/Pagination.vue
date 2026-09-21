<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    pagination: {
        type: Object,
        required: true,
    },
    baseUrl: {
        type: String,
        default: '/katalog',
    },
});

const currentPage = computed(() => Number(props.pagination.currentPage) || 1);
const lastPage = computed(() => Number(props.pagination.lastPage) || 1);
const showPagination = computed(() => lastPage.value > 1);

// Windowed page list with collapsed ellipses, keeping current surrounded by neighbors.
const pages = computed(() => {
    const last = lastPage.value;
    const current = currentPage.value;

    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const candidates = [1, last, current - 1, current, current + 1];
    const sorted = [...new Set(candidates)]
        .filter((p) => p >= 1 && p <= last)
        .sort((a, b) => a - b);

    const result = [];
    let previous = 0;

    for (const page of sorted) {
        if (previous && page - previous > 1) {
            result.push('…');
        }

        result.push(page);
        previous = page;
    }

    return result;
});

// The server's baseUrl may already carry discovery params (`/katalog?q=meja&sni=1`);
// append `page` with the correct separator so discovery is preserved across pages.
const href = (page) => {
    const separator = props.baseUrl.includes('?') ? '&' : '?';

    return `${props.baseUrl}${separator}page=${page}`;
};
</script>

<template>
    <nav
        v-if="showPagination"
        aria-label="Paginasi katalog"
        class="mt-12 flex items-center justify-center gap-1.5 lg:mt-14 lg:justify-end"
    >
        <Link
            v-if="currentPage > 1"
            :href="href(currentPage - 1)"
            aria-label="Halaman sebelumnya"
            class="inline-flex h-11 w-11 items-center justify-center rounded-brand-sm border border-line text-label font-medium text-ink transition-colors duration-fast hover:border-line-strong/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
        >
            ←
        </Link>
        <span
            v-else
            aria-disabled="true"
            class="inline-flex h-11 w-11 cursor-not-allowed items-center justify-center rounded-brand-sm border border-line text-label font-medium text-ink/40"
        >
            ←
        </span>

        <template v-for="(page, index) in pages" :key="`${page}-${index}`">
            <span v-if="page === '…'" class="px-1 text-ink-soft/60" aria-hidden="true">…</span>
            <Link
                v-else
                :href="href(page)"
                :aria-current="page === currentPage ? 'page' : undefined"
                class="inline-flex h-11 min-w-11 items-center justify-center rounded-brand-sm border px-2 text-label font-medium transition-colors duration-fast focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
                :class="
                    page === currentPage
                        ? 'border-brand bg-brand text-inverse'
                        : 'border-line text-ink hover:border-line-strong/40'
                "
            >
                {{ page }}
            </Link>
        </template>

        <Link
            v-if="currentPage < lastPage"
            :href="href(currentPage + 1)"
            aria-label="Halaman berikutnya"
            class="inline-flex h-11 w-11 items-center justify-center rounded-brand-sm border border-line text-label font-medium text-ink transition-colors duration-fast hover:border-line-strong/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
        >
            →
        </Link>
        <span
            v-else
            aria-disabled="true"
            class="inline-flex h-11 w-11 cursor-not-allowed items-center justify-center rounded-brand-sm border border-line text-label font-medium text-ink/40"
        >
            →
        </span>
    </nav>
</template>
