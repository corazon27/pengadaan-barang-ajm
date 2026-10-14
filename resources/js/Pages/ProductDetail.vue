<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import Container from '../Components/Container.vue';
import Section from '../Components/Section.vue';
import Button from '../Components/Button.vue';
import ProductImage from '../Components/ProductImage.vue';
import { formatPrice } from '../utils/format';

// Phase 3C.6 — public product detail. The server sends exactly six public fields:
// { sku, title, description, price, warrantyInfo, available }. Everything else —
// slug, margin, tax, PPH, shipping, datasheet_url, TKDN/SNI, timestamps, raw stock —
// is never selected server-side, so it cannot be rendered here.
//
// Deliberately absent from this surface: TKDN/SNI markers, datasheet link, product
// photography, share / add-to-cart / quantity / transaction UI. Identity, price,
// availability and public specification only.

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    appName: {
        type: String,
        default: 'Pengadaan Barang AJM',
    },
});

// Price rendering is delegated to the shared formatter so it stays byte-identical
// across catalog and detail surfaces.
const pageTitle = computed(() => `${props.product.title} — ${props.appName}`);

// Description is free text of unknown length, so it is bounded before use as a
// meta description. Falsy/absent description simply yields no description meta.
const metaDescription = computed(() => {
    const text = (props.product.description || '').trim();

    if (text === '') {
        return null;
    }

    return text.length > 160 ? `${text.slice(0, 157).trimEnd()}…` : text;
});

defineOptions({ layout: PublicLayout });
</script>

<template>
    <Head :title="pageTitle">
        <meta v-if="metaDescription" name="description" :content="metaDescription" />
        <meta property="og:type" content="product" />
        <meta property="og:title" :content="pageTitle" />
        <meta v-if="metaDescription" property="og:description" :content="metaDescription" />
    </Head>

    <Section>
        <Container>
            <!-- Breadcrumb: the only way back into the discovery surface. -->
            <nav aria-label="Remah roti">
                <Link
                    href="/katalog"
                    class="inline-flex min-h-11 items-center gap-2 rounded-brand-sm text-caption font-medium uppercase tracking-[0.2em] text-ink-soft transition-colors duration-fast hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
                >
                    <span aria-hidden="true">←</span>
                    Katalog Publik
                </Link>
            </nav>

            <div class="mt-6 border-t border-line pt-10 lg:mt-8 lg:pt-14">
                <div class="grid gap-10 lg:grid-cols-12 lg:gap-12">
                    <!-- Identity column -->
                    <div class="lg:col-span-7">
                        <div class="overflow-hidden rounded-brand-md border border-line bg-surface">
                            <ProductImage />
                        </div>

                        <p class="mt-8 flex items-center gap-3 text-caption font-medium uppercase tracking-[0.2em] text-ink-soft">
                            <span class="h-2 w-2 bg-brand" aria-hidden="true"></span>
                            {{ product.sku }}
                        </p>

                        <h1 class="mt-4 text-h2 font-semibold tracking-tight text-ink">
                            {{ product.title }}
                        </h1>

                        <div
                            v-if="product.description"
                            class="mt-6 max-w-[60ch] border-l-2 border-brand-soft pl-5"
                        >
                            <p class="text-lead text-ink-soft">{{ product.description }}</p>
                        </div>
                        <p v-else class="mt-6 text-lead text-ink-soft/70">
                            Deskripsi produk belum tersedia.
                        </p>
                    </div>

                    <!-- Commercial + specification register column -->
                    <div class="lg:col-span-5">
                        <div class="rounded-brand-md border border-line bg-surface p-6 shadow-brand-subtle">
                            <p class="text-caption font-medium uppercase tracking-[0.2em] text-ink-soft">
                                Harga Pengadaan
                            </p>
                            <p class="mt-2 text-display font-semibold tracking-tight text-ink">
                                {{ formatPrice(product.price) }}
                            </p>

                            <p
                                class="mt-4 flex items-center gap-2 text-label font-medium"
                                :class="product.available ? 'text-ink-soft' : 'text-ink-soft/70'"
                            >
                                <span aria-hidden="true">{{ product.available ? '✓' : '—' }}</span>
                                {{ product.available ? 'Tersedia' : 'Tidak tersedia' }}
                            </p>
                        </div>

                        <dl class="mt-8">
                            <div
                                class="flex items-baseline justify-between gap-6 border-b border-line py-4 first:border-t"
                            >
                                <dt
                                    class="text-caption font-medium uppercase tracking-[0.2em] text-ink-soft"
                                >
                                    Kode Produk
                                </dt>
                                <dd class="text-label font-medium text-ink">{{ product.sku }}</dd>
                            </div>

                            <div class="flex items-baseline justify-between gap-6 border-b border-line py-4">
                                <dt
                                    class="text-caption font-medium uppercase tracking-[0.2em] text-ink-soft"
                                >
                                    Ketersediaan
                                </dt>
                                <dd class="text-label font-medium text-ink">
                                    {{ product.available ? 'Tersedia' : 'Tidak tersedia' }}
                                </dd>
                            </div>

                            <div
                                v-if="product.warrantyInfo"
                                class="flex items-baseline justify-between gap-6 border-b border-line py-4"
                            >
                                <dt
                                    class="text-caption font-medium uppercase tracking-[0.2em] text-ink-soft"
                                >
                                    Garansi
                                </dt>
                                <dd class="text-label font-medium text-ink">{{ product.warrantyInfo }}</dd>
                            </div>
                        </dl>

                        <div class="mt-8">
                            <Button href="/katalog" variant="secondary">
                                Kembali ke katalog
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Container>
    </Section>
</template>
