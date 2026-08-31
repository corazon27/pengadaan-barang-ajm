<script setup>
import { usePage } from '@inertiajs/vue3';
import Container from './Container.vue';

const { props } = usePage();

const year = new Date().getFullYear();

// GATE A: only the verified brand wordmark and descriptive (non-claim) copy are
// rendered. Company identity fields (legal entity, address, phone, email, website,
// NIB, NPWP, bank) remain UNVERIFIED placeholders and are therefore omitted from
// public output. The "index register" below is presentational brand composition —
// ruled ledger lines, labels only, no invented metrics, links, or references.
const footerIndex = [
    { label: 'Naskah', value: `${props.appName} · ${year}` },
    { label: 'Basis', value: 'B2B · B2G' },
    { label: 'Bentuk', value: 'Katalog Produk' },
];
</script>

<template>
    <footer class="bg-surface-muted">
        <!-- Closing frame: heavy navy rule mirrors the header (navy = structure) -->
        <div class="h-1 bg-ink" aria-hidden="true"></div>

        <Container class="py-14 sm:py-16">
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-10">
                <div class="lg:col-span-7">
                    <div class="flex items-center gap-3">
                        <span class="h-2.5 w-2.5 bg-brand" aria-hidden="true"></span>
                        <p class="text-label font-semibold tracking-tight text-ink">{{ props.appName }}</p>
                    </div>
                    <p class="mt-5 max-w-[52ch] text-body text-ink-soft">
                        Katalog produk &amp; platform pengadaan barang B2B dan B2G — proses berbasis
                        dokumen dari permintaan penawaran (RFQ), surat penawaran harga, hingga BAST dan
                        invoice.
                    </p>
                </div>

                <div class="lg:col-span-5">
                    <!-- Index register — ruled ledger lines (presentational signature) -->
                    <dl class="ml-auto max-w-xs">
                        <div
                            v-for="row in footerIndex"
                            :key="row.label"
                            class="flex items-center justify-between border-t border-line py-2.5 first:border-t-0"
                        >
                            <dt class="text-caption uppercase tracking-[0.2em] text-ink-soft">
                                {{ row.label }}
                            </dt>
                            <dd class="text-sm font-medium text-ink">{{ row.value }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="mt-14 flex flex-wrap items-center justify-between gap-4 border-t border-line pt-6">
                <p class="text-caption text-ink-soft">© {{ year }} {{ props.appName }}.</p>
                <p class="text-caption text-ink-soft/70">RFQ · Penawaran · BAST · Invoice</p>
            </div>
        </Container>
    </footer>
</template>
