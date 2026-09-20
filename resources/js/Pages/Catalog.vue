<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import Container from '../Components/Container.vue';
import ProductCard from '../Components/ProductCard.vue';
import Pagination from '../Components/Pagination.vue';
import EmptyState from '../Components/EmptyState.vue';

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
                    <p class="mt-5 text-label font-medium text-ink">
                        {{ total }} produk pada katalog publik
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

            <div class="mt-10 border-t border-line lg:mt-14"></div>

            <div
                v-if="products.length > 0"
                class="mt-10 grid grid-cols-1 gap-6 min-[520px]:grid-cols-2 lg:mt-14 lg:grid-cols-3"
            >
                <ProductCard
                    v-for="product in products"
                    :key="product.id"
                    :product="product"
                />
            </div>

            <EmptyState v-else />

            <Pagination :pagination="pagination" />
        </Container>
    </section>
</template>
