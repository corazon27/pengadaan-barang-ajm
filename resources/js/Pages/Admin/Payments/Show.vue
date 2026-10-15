<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import GovNote from '../../../Components/Admin/GovNote.vue';
import { formatDate, formatPrice } from '../../../utils/format';

defineProps({
    payment: { type: Object, required: true },
});

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head :title="`Pembayaran ${payment.invoice_number ?? ''}`" />

    <div class="flex items-center justify-between">
        <h1 class="text-h3 font-semibold">Pembayaran</h1>
        <Link
            href="/admin/pembayaran"
            class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
        >
            ← Antrean Pembayaran
        </Link>
    </div>

    <dl class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-3">
        <div>
            <dt class="text-caption text-ink-soft">Tagihan</dt>
            <dd class="text-label font-medium">
                <Link
                    v-if="payment.invoice_number"
                    :href="`/admin/tagihan`"
                    class="text-brand transition-colors duration-fast hover:text-brand-secondary"
                >
                    {{ payment.invoice_number }}
                </Link>
                <span v-else>—</span>
            </dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Pesanan</dt>
            <dd class="text-label font-medium">{{ payment.order_number ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Pembeli</dt>
            <dd class="text-label font-medium">{{ payment.buyer_company ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Jumlah</dt>
            <dd class="text-label font-semibold">{{ formatPrice(payment.amount) }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Metode</dt>
            <dd class="text-label font-medium">{{ payment.payment_method_label }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Tanggal Bayar</dt>
            <dd class="text-label font-medium">{{ formatDate(payment.payment_date) ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Status</dt>
            <dd class="text-label font-medium">{{ payment.status_label }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Bukti Terlampir</dt>
            <dd class="text-label font-medium">{{ payment.has_proof ? 'Ya' : 'Tidak' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Diverifikasi Oleh</dt>
            <dd class="text-label font-medium">{{ payment.verified_by_name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Diverifikasi Pada</dt>
            <dd class="text-label font-medium">{{ formatDate(payment.verified_at) ?? '—' }}</dd>
        </div>
    </dl>

    <section v-if="payment.notes" class="mt-8">
        <h2 class="text-label font-semibold">Catatan Pembeli</h2>
        <p class="mt-2 whitespace-pre-line text-caption text-ink-soft">{{ payment.notes }}</p>
    </section>

    <section v-if="payment.rejection_reason" class="mt-6">
        <h2 class="text-label font-semibold">Alasan Penolakan</h2>
        <p class="mt-2 whitespace-pre-line text-caption text-ink-soft">{{ payment.rejection_reason }}</p>
    </section>

    <p class="mt-8 rounded-brand-sm border border-dashed border-line p-4 text-caption text-ink-soft">
        Berkas bukti pembayaran tidak ditampilkan pada halaman ini karena belum tersedia
        jalur unduh yang terlindungi dan tercatat.
    </p>

    <GovNote
        message="Status pembayaran pada halaman ini adalah pencatatan internal layanan AJM dan menunggu verifikasi admin, bukan konfirmasi resmi dari instansi pemerintah."
    />
</template>
