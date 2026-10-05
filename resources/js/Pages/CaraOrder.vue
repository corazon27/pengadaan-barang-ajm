<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import Container from '../Components/Container.vue';
import Section from '../Components/Section.vue';
import Button from '../Components/Button.vue';

const props = defineProps({
    appName: { type: String, default: 'Pengadaan Barang AJM' },
    companyName: { type: String, default: 'AJM' },
    canonicalUrl: { type: String, default: null },
});

const pageTitle = props.appName + ' — Cara Order & Pengadaan B2B/B2G';
const pageDescription =
    'Pelajari cara memanfaatkan katalog publik AJM untuk pengadaan B2B/B2G. Informasi proses pengadaan yang transparan dan terstruktur.';

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

    <Section>
        <Container>
            <div class="mx-auto max-w-4xl space-y-12">
                <header class="space-y-6 text-center">
                    <h1 class="text-display font-semibold tracking-tight text-ink sm:text-display-lg lg:text-display-xl">
                        Cara Order
                    </h1>
                    <p class="mx-auto max-w-3xl text-body-lg text-ink-soft">
                        Panduan untuk memanfaatkan katalog publik {{ props.appName }} dalam proses pengadaan B2B/B2G secara informatif dan transparan.
                    </p>
                </header>

                <div class="space-y-10">
                    <Section id="overview">
                        <h2 class="text-heading-lg font-semibold tracking-tight text-ink">
                            Gambaran Umum
                        </h2>
                        <p class="mt-4 text-body text-ink-soft leading-relaxed">
                            {{ props.appName }} merupakan platform pendukung berbasis katalog untuk kebutuhan pengadaan barang. Halaman ini menjelaskan proses keterlibatan secara umum, tanpa mengubah alur transaksi publik yang ada.
                        </p>
                    </Section>

                    <Section id="cara-memanfaatkan">
                        <h2 class="text-heading-lg font-semibold tracking-tight text-ink">
                            Cara Memanfaatkan Katalog
                        </h2>
                        <ol class="mt-4 space-y-4 text-body text-ink-soft">
                            <li class="flex gap-4">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-brand/10 text-label font-semibold text-brand">1</span>
                                <div>
                                    <h3 class="font-medium text-ink">Jelajahi Katalog</h3>
                                    <p class="mt-1 leading-relaxed">
                                        Temukan produk yang sesuai melalui halaman <a href="/katalog" class="text-brand underline-offset-4 hover:underline">Katalog</a> berdasarkan kebutuhan spesifikasi teknis.
                                    </p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-brand/10 text-label font-semibold text-brand">2</span>
                                <div>
                                    <h3 class="font-medium text-ink">Tinjau Detail Produk</h3>
                                    <p class="mt-1 leading-relaxed">
                                        Periksa informasi teknis, spesifikasi, dan ketersediaan pada halaman detail produk untuk mendukung evaluasi teknis.
                                    </p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-brand/10 text-label font-semibold text-brand">3</span>
                                <div>
                                    <h3 class="font-medium text-ink">Lanjutkan Sesuai Proses Pengadaan</h3>
                                    <p class="mt-1 leading-relaxed">
                                        Berdasarkan hasil evaluasi, lanjutkan sesuai proses pengadaan resmi yang berlaku, sesuai ketentuan dan mekanisme yang relevan.
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </Section>

                    <Section id="b2b">
                        <h2 class="text-heading-lg font-semibold tracking-tight text-ink">
                            Untuk Kebutuhan B2B
                        </h2>
                        <p class="mt-4 text-body text-ink-soft leading-relaxed">
                            Untuk mitra bisnis, katalog ini dapat dimanfaatkan sebagai referensi dalam proses evaluasi teknis dan kebutuhan pengadaan berbasis dokumen, sesuai mekanisme kerja sama yang disepakati.
                        </p>
                    </Section>

                    <Section id="b2g">
                        <h2 class="text-heading-lg font-semibold tracking-tight text-ink">
                            Untuk Kebutuhan B2G
                        </h2>
                        <p class="mt-4 text-body text-ink-soft leading-relaxed">
                            {{ props.appName }} berperan sebagai sarana pendukung sisi penyedia (supplier-side) untuk kebutuhan pengadaan pemerintah. Situs ini bukanlah sistem pengadaan resmi pemerintah atau sistem pencatatan resmi pengadaan. Pengguna disarankan untuk mengikuti proses pengadaan resmi yang berlaku sesuai ketentuan perundang-undangan.
                        </p>
                    </Section>

                    <Section id="catatan">
                        <h2 class="text-heading-lg font-semibold tracking-tight text-ink">
                            Catatan Penting
                        </h2>
                        <p class="mt-4 text-body text-ink-soft leading-relaxed">
                            Informasi yang disajikan bersifat informatif dan tidak dimaksudkan untuk menggantikan ketentuan resmi dalam proses pengadaan. Seluruh proses administratif dan hukum tetap mengacu pada ketentuan yang berlaku.
                        </p>
                    </Section>

                    <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                        <Button href="/katalog" variant="primary">Lihat Katalog</Button>
                    </div>
                </div>
            </div>
        </Container>
    </Section>
</template>