<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import Container from '../Components/Container.vue';
import ProductCard from '../Components/ProductCard.vue';
import Pagination from '../Components/Pagination.vue';
import EmptyState from '../Components/EmptyState.vue';
import DiscoveryToolbar from '../Components/Discovery/DiscoveryToolbar.vue';
import ActiveFilters from '../Components/Discovery/ActiveFilters.vue';

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: Object,
        default: () => ({}),
    },
    canonicalUrl: {
        type: String,
        default: null,
    },
    hasActiveDiscovery: {
        type: Boolean,
        default: false,
    },
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
    appName: {
        type: String,
        default: 'Pengadaan Barang AJM',
    },
    companyName: {
        type: String,
        default: 'AJM',
    },
});

const year = new Date().getFullYear();
const appNameLabel = props.appName || 'Pengadaan Barang AJM';
const total = computed(() => props.pagination.total ?? props.products.length);
const pageTitle = `${appNameLabel} — Katalog Produk & Platform Pengadaan B2B/B2G`;
const metaDescription =
    'Jelajahi katalog produk publik AJM untuk pengadaan B2B/B2G — spesifikasi teknis, informasi TKDN dan SNI, serta ketersediaan.';

// The single live region: one role="status" line that announces the result
// count and, when discovery is active, a compact summary of the active filters
// in the SAME announcement. ActiveFilters row is ordinary content, not a live
// region. One navigation → one announcement.
const resultStatus = computed(() => {
    const parts = [];
    if (props.discovery.q) parts.push(`pencarian "${props.discovery.q}"`);
    if (props.discovery.sni) parts.push('SNI');
    if (props.discovery.tkdn_min !== null && props.discovery.tkdn_min !== undefined) {
        parts.push(`TKDN ≥ ${props.discovery.tkdn_min}%`);
    }
    if (props.discovery.availability) parts.push('tersedia');

    if (parts.length === 0) {
        return `Menampilkan ${total.value} produk pada katalog publik.`;
    }

    return `Menampilkan ${total.value} produk dengan filter: ${parts.join(', ')}.`;
});

defineOptions({ layout: PublicLayout });
</script>

<template>
    <Head :title="pageTitle">
        <meta name="description" :content="metaDescription" />
        <meta property="og:type" content="website" />
        <meta property="og:title" :content="pageTitle" />
        <meta property="og:description" :content="metaDescription" />
    </Head>

    <section aria-labelledby="catalog-heading" class="bg-surface">
        <Container class="py-16 lg:py-24">
            <div class="grid lg:grid-cols-12 lg:gap-10">
                <div class="lg:col-span-8">
                    <p
                        class="flex items-center gap-3 text-caption font-medium uppercase tracking-[0.2em] text-ink-soft"
                    >
                        <span class="h-2 w-2 bg-brand" aria-hidden="true"></span>
                        Katalog Publik
                    </p>
                    <h1
                        id="catalog-heading"
                        class="mt-6 text-h2 font-semibold tracking-tight text-ink"
                    >
                        Katalog Produk
                    </h1>
                    <p class="mt-4 max-w-[52ch] text-lead text-ink-soft">
                        Daftar produk publik untuk kebutuhan pengadaan — spesifikasi teknis,
                        informasi TKDN dan SNI, serta ketersediaan.
                    </p>
                </div>

                <div
                    class="hidden lg:col-span-4 lg:flex lg:flex-col lg:items-end lg:justify-end lg:gap-4 lg:pb-1"
                    aria-hidden="true"
                >
                    <p class="text-caption uppercase tracking-[0.2em] text-ink-soft/60">Katalog Publik</p>
                    <span class="relative block h-5 w-5 border border-line-strong/40">
                        <span
                            class="absolute left-1/2 top-1/2 h-1.5 w-1.5 -translate-x-1/2 -translate-y-1/2 bg-brand"
                        ></span>
                    </span>
                    <p class="text-caption uppercase tracking-[0.2em] text-ink-soft/60">R-{{ year }}</p>
                </div>
            </div>

            <DiscoveryToolbar :discovery="discovery" />
            <ActiveFilters :discovery="discovery" />

            <p v-if="products.length > 0" role="status" class="mt-8 text-label font-medium text-ink">
                {{ resultStatus }}
            </p>

            <div class="mt-6 border-t border-line pt-6">
                <div
                    v-if="products.length > 0"
                    class="grid grid-cols-1 gap-6 min-[520px]:grid-cols-2 lg:grid-cols-3"
                >
                    <ProductCard
                        v-for="product in products"
                        :key="product.id"
                        :product="product"
                    />
                </div>

                <EmptyState
                    v-else
                    :title="hasActiveDiscovery ? 'Tidak ada produk yang cocok dengan filter' : undefined"
                    :message="
                        hasActiveDiscovery
                            ? 'Coba ubah kata kunci pencarian atau hapus salah satu filter untuk melihat lebih banyak produk.'
                            : undefined
                    "
                    :action-href="hasActiveDiscovery ? '/katalog' : null"
                    :action-label="hasActiveDiscovery ? 'Lihat semua produk' : ''"
                />

                <Pagination :pagination="pagination" :base-url="pagination.baseUrl" />
            </div>
        </Container>
    </section>
</template>