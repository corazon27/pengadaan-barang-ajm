<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import GovNote from '../../../Components/Admin/GovNote.vue';
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
            href="/admin/tagihan"
            class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
        >
            ← Antrean Tagihan
        </Link>
    </div>

    <dl class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-3">
        <div>
            <dt class="text-caption text-ink-soft">Pesanan</dt>
            <dd class="text-label font-medium">{{ invoice.order_number ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Pembeli</dt>
            <dd class="text-label font-medium">{{ invoice.buyer_company ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Status</dt>
            <dd class="text-label font-medium">{{ invoice.status_label }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Nomor Faktur Pajak</dt>
            <dd class="text-label font-medium">{{ invoice.faktur_pajak_number ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Termin</dt>
            <dd class="text-label font-medium">{{ invoice.payment_term_label ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Tanggal Terbit</dt>
            <dd class="text-label font-medium">{{ formatDate(invoice.issued_date) ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Jatuh Tempo</dt>
            <dd class="text-label font-medium">{{ formatDate(invoice.due_date) ?? '—' }}</dd>
        </div>
    </dl>

    <section class="mt-8">
        <h2 class="text-label font-semibold">Rincian</h2>
        <dl class="mt-3 divide-y divide-line rounded-brand-sm border border-line">
            <div class="flex items-center justify-between px-4 py-2.5">
                <dt class="text-caption text-ink-soft">Subtotal</dt>
                <dd class="text-caption text-ink">{{ formatPrice(invoice.subtotal) }}</dd>
            </div>
            <div class="flex items-center justify-between px-4 py-2.5">
                <dt class="text-caption text-ink-soft">PPN</dt>
                <dd class="text-caption text-ink">{{ formatPrice(invoice.ppn_amount) }}</dd>
            </div>
            <div class="flex items-center justify-between px-4 py-2.5">
                <dt class="text-caption text-ink-soft">PPh</dt>
                <dd class="text-caption text-ink">{{ formatPrice(invoice.pph_amount) }}</dd>
            </div>
            <div class="flex items-center justify-between px-4 py-2.5">
                <dt class="text-caption text-ink-soft">Total</dt>
                <dd class="text-label font-semibold">{{ formatPrice(invoice.grand_total) }}</dd>
            </div>
            <div class="flex items-center justify-between px-4 py-2.5">
                <dt class="text-caption text-ink-soft">Sudah Dibayar (terverifikasi)</dt>
                <dd class="text-caption text-ink">{{ formatPrice(invoice.paid_amount) }}</dd>
            </div>
            <div class="flex items-center justify-between px-4 py-2.5">
                <dt class="text-caption text-ink-soft">Sisa</dt>
                <dd class="text-label font-semibold">{{ formatPrice(invoice.amount_due) }}</dd>
            </div>
        </dl>
    </section>

    <section class="mt-8">
        <h2 class="text-label font-semibold">Pembayaran</h2>
        <p v-if="invoice.payments.length === 0" class="mt-3 text-caption text-ink-soft">
            Belum ada pembayaran.
        </p>
        <ul v-else class="mt-3 divide-y divide-line rounded-brand-sm border border-line">
            <li
                v-for="payment in invoice.payments"
                :key="payment.id"
                class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
            >
                <Link
                    :href="`/admin/pembayaran/${payment.id}`"
                    class="text-label font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
                >
                    {{ formatPrice(payment.amount) }}
                </Link>
                <span class="text-caption text-ink-soft">{{ payment.payment_method_label }}</span>
                <span class="text-caption text-ink-soft">{{ payment.status_label }}</span>
                <span class="text-caption text-ink-soft">{{ formatDate(payment.payment_date) ?? '—' }}</span>
            </li>
        </ul>
    </section>

    <GovNote
        message="Status tagihan pada halaman ini adalah pencatatan internal layanan AJM, bukan status resmi pada sistem pengadaan pemerintah (INAPROC/SPSE)."
    />
</template>
