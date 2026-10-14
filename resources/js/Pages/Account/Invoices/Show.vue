<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import { formatDate, formatPrice } from '../../../utils/format';

defineProps({
    invoice: { type: Object, required: true },
});

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head :title="`Tagihan ${invoice.invoice_number}`" />

    <div class="flex items-center justify-between">
        <h1 class="text-h3 font-semibold">{{ invoice.invoice_number }}</h1>
        <Link
            href="/akun/tagihan"
            class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
        >
            ← Tagihan
        </Link>
    </div>

    <dl class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Status</dt>
            <dd class="mt-1 text-label">{{ invoice.status_label }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Pesanan</dt>
            <dd class="mt-1 text-label">{{ invoice.order_number ?? '—' }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Termin</dt>
            <dd class="mt-1 text-label">{{ invoice.payment_term_label ?? '—' }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Tanggal Terbit</dt>
            <dd class="mt-1 text-label">{{ formatDate(invoice.issued_date) }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Jatuh Tempo</dt>
            <dd class="mt-1 text-label">{{ formatDate(invoice.due_date) }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Nomor Faktur Pajak</dt>
            <dd class="mt-1 text-label">{{ invoice.faktur_pajak_number ?? '—' }}</dd>
        </div>
    </dl>

    <section class="mt-10" aria-labelledby="rincian-heading">
        <h2 id="rincian-heading" class="text-label font-semibold">Rincian</h2>
        <dl class="mt-4 divide-y divide-line rounded-brand-sm border border-line">
            <div class="flex items-center justify-between px-4 py-3">
                <dt class="text-caption text-ink-soft">Subtotal</dt>
                <dd class="text-label text-ink">{{ formatPrice(invoice.subtotal) }}</dd>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <dt class="text-caption text-ink-soft">PPN</dt>
                <dd class="text-label text-ink">{{ formatPrice(invoice.ppn_amount) }}</dd>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <dt class="text-caption text-ink-soft">PPH</dt>
                <dd class="text-label text-ink">{{ formatPrice(invoice.pph_amount) }}</dd>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <dt class="text-label font-medium text-ink">Total</dt>
                <dd class="text-label font-medium text-ink">{{ formatPrice(invoice.grand_total) }}</dd>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <dt class="text-caption text-ink-soft">Sudah Dibayar</dt>
                <dd class="text-label text-ink">{{ formatPrice(invoice.paid_amount) }}</dd>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <dt class="text-label font-medium text-ink">Sisa Tagihan</dt>
                <dd class="text-label font-medium text-ink">{{ formatPrice(invoice.amount_due) }}</dd>
            </div>
        </dl>
    </section>

    <section class="mt-10" aria-labelledby="pembayaran-heading">
        <h2 id="pembayaran-heading" class="text-label font-semibold">Pembayaran</h2>
        <div v-if="invoice.payments.length === 0" class="mt-4 text-caption text-ink-soft">
            Belum ada pembayaran tercatat.
        </div>
        <ul v-else class="mt-4 divide-y divide-line rounded-brand-sm border border-line">
            <li
                v-for="payment in invoice.payments"
                :key="payment.id"
                class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
            >
                <span class="text-label text-ink">{{ formatPrice(payment.amount) }}</span>
                <span class="text-caption text-ink-soft">{{ payment.payment_method_label }}</span>
                <span class="text-caption text-ink-soft">{{ formatDate(payment.payment_date) }}</span>
                <span class="text-caption text-ink-soft">{{ payment.status_label }}</span>
                <span v-if="payment.rejection_reason" class="text-caption text-ink-soft">
                    {{ payment.rejection_reason }}
                </span>
            </li>
        </ul>
    </section>

    <p class="mt-10 rounded-brand-sm border border-line bg-surface-muted p-4 text-caption text-ink-soft">
        Tagihan dan pajak pada halaman ini adalah pencatatan internal layanan AJM, bukan dokumen
        resmi pada sistem pengadaan pemerintah (INAPROC/SPSE).
    </p>
</template>
