<script setup>
import Container from './Container.vue';

// PLACEHOLDER COPY — derived from PRD §1/§6; pending explicit copy sign-off.
// NO claims (trust/compliance/official/validation/counts/metrics) are used.
//
// Direction: "The Registered Transaction" — the page is framed top and bottom by
// heavy navy structural rules (navy = structure). Blue is reserved exclusively for
// registration marks and the live-process anchor. The hero is a single editorial
// masthead whose "one memorable idea" is the full-width procurement-process
// register (RFQ → Surat Penawaran → BAST → Invoice), replacing any boxed artifact.
const processStages = [
    { code: '01', name: 'RFQ', detail: 'Permintaan penawaran', anchor: false },
    { code: '02', name: 'Penawaran', detail: 'Surat penawaran harga', anchor: false },
    { code: '03', name: 'BAST', detail: 'Berita acara serah terima', anchor: true },
    { code: '04', name: 'Invoice', detail: 'Tagihan & pembayaran', anchor: false },
];

const year = new Date().getFullYear();
</script>

<template>
    <section aria-labelledby="hero-heading" class="relative overflow-hidden bg-surface">
        <Container class="py-20 lg:py-28">
            <!-- Editorial masthead: one dominant column + quiet running index -->
            <div class="grid lg:grid-cols-12 lg:gap-10">
                <div class="lg:col-span-8">
                    <p class="flex items-center gap-3 text-caption font-medium uppercase tracking-[0.2em] text-ink-soft">
                        <span class="h-2 w-2 bg-brand" aria-hidden="true"></span>
                        Platform Pengadaan Barang &nbsp;·&nbsp; B2B / B2G
                    </p>

                    <h1
                        id="hero-heading"
                        class="mt-8 text-display font-semibold tracking-tight text-ink"
                    >
                        Katalog produk &amp; platform<br />
                        <span class="text-brand">pengadaan barang</span><br />
                        B2B &amp; B2G
                    </h1>

                    <p class="mt-7 max-w-[46ch] text-lead text-ink-soft">
                        Proses pengadaan berbasis dokumen — dari permintaan penawaran (RFQ), surat
                        penawaran harga, hingga BAST dan invoice.
                    </p>
                </div>

                <!-- Running index / registration crosshair (desktop, presentational) -->
                <div
                    class="hidden lg:col-span-4 lg:flex lg:flex-col lg:items-end lg:gap-6 lg:pt-2"
                    aria-hidden="true"
                >
                    <p class="text-caption uppercase tracking-[0.2em] text-ink-soft/60">Katalog Produk</p>
                    <span class="relative block h-5 w-5 border border-line-strong/40">
                        <span
                            class="absolute left-1/2 top-1/2 h-1.5 w-1.5 -translate-x-1/2 -translate-y-1/2 bg-brand"
                        ></span>
                    </span>
                    <p class="text-caption uppercase tracking-[0.2em] text-ink-soft/60">R-{{ year }}</p>
                </div>
            </div>

            <!-- Baseline rule — the measurement/alignment field -->
            <div class="mt-16 border-t border-line lg:mt-24"></div>

            <!-- Procurement process register (the memorable idea) -->
            <div class="mt-8 grid gap-10 lg:grid-cols-12 lg:items-end lg:gap-10">
                <div class="lg:col-span-3">
                    <p class="text-caption font-medium uppercase tracking-[0.2em] text-ink-soft">
                        Proses Pengadaan
                    </p>
                    <p class="mt-2 max-w-[24ch] text-sm leading-relaxed text-ink-soft/80">
                        Berbasis dokumen — setiap tahap tercatat dalam katalog.
                    </p>
                </div>

                <ol class="grid grid-cols-1 gap-x-8 gap-y-6 min-[480px]:grid-cols-2 lg:col-span-9 lg:grid-cols-4">
                    <li
                        v-for="stage in processStages"
                        :key="stage.code"
                        class="flex flex-col"
                    >
                        <span class="flex items-center gap-3">
                            <span
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center border text-caption font-semibold"
                                :class="stage.anchor ? 'border-brand text-ink' : 'border-line-strong/40 text-ink-soft'"
                            >
                                {{ stage.code }}
                            </span>
                            <span
                                class="text-label font-semibold tracking-tight"
                                :class="stage.anchor ? 'text-brand' : 'text-ink'"
                            >
                                {{ stage.name }}
                            </span>
                        </span>
                        <p class="mt-2 pl-11 text-sm text-ink-soft">{{ stage.detail }}</p>
                        <span
                            class="mt-4 block border-t"
                            :class="stage.anchor ? 'border-brand' : 'border-line'"
                            aria-hidden="true"
                        ></span>
                    </li>
                </ol>
            </div>
        </Container>
    </section>
</template>
