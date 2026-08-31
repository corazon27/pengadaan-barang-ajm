<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import Hero from '../Components/Hero.vue';

const props = defineProps({
    appName: String,
    companyName: String,
    canonicalUrl: { type: String, default: null },
});

// PLACEHOLDER SEO copy — pending approval; no claims used.
const pageTitle = `${props.appName} — Katalog Produk & Platform Pengadaan B2B/B2G`;
const pageDescription =
    'Katalog produk dan platform pengadaan barang untuk B2B dan B2G dengan proses berbasis dokumen (RFQ, surat penawaran harga, BAST, invoice).';

defineOptions({ layout: PublicLayout });
</script>

<template>
    <Head :title="pageTitle">
        <meta name="description" :content="pageDescription" />
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
        <meta property="og:type" content="website" />
        <meta property="og:title" :content="pageTitle" />
        <meta property="og:description" :content="pageDescription" />
        <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl" />
    </Head>

    <Hero />
</template>