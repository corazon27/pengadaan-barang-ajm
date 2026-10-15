<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import GovNote from '../../../Components/Admin/GovNote.vue';
import { formatDate, formatPrice } from '../../../utils/format';

const props = defineProps({
    metrics: { type: Object, required: true },
    attention: { type: Object, required: true },
});

defineOptions({ layout: AccountLayout });

const tkdnLabel = computed(() => {
    const value = props.metrics.tkdn_compliance?.average_tkdn_percentage;

    return value === null || value === undefined ? '—' : `${Number(value).toFixed(2)}%`;
});

const orderBreakdown = computed(() =>
    Object.entries(props.metrics.orders?.by_status ?? {}).sort((a, b) =>
        a[0].localeCompare(b[0]),
    ),
);

const receivablesBreakdown = computed(() =>
    Object.entries(props.metrics.outstanding_receivables?.by_status ?? {}).sort((a, b) =>
        a[0].localeCompare(b[0]),
    ),
);

const queues = computed(() => [
    {
        key: 'pending_accounts',
        label: 'Akun menunggu persetujuan',
        count: props.attention.pending_accounts,
        href: '/admin/akun',
    },
    {
        key: 'evidence_awaiting_review',
        label: 'Bukti B2G menunggu peninjauan',
        count: props.attention.evidence_awaiting_review,
        href: '/admin/akun',
    },
    {
        key: 'rfqs_awaiting_conversion',
        label: 'RFQ menunggu konversi',
        count: props.attention.rfqs_awaiting_conversion,
        href: '/admin/rfq?status=APPROVED',
    },
    {
        key: 'payments_pending_verification',
        label: 'Pembayaran menunggu verifikasi',
        count: props.attention.payments_pending_verification,
        href: '/admin/pembayaran?status=PENDING_VERIFICATION',
    },
    {
        key: 'invoices_review_required',
        label: 'Tagihan menunggu perhitungan pajak',
        count: props.attention.invoices_review_required,
        href: '/admin/tagihan?status=REVIEW_REQUIRED',
    },
    {
        key: 'invoices_overdue',
        label: 'Tagihan terlambat',
        count: props.attention.invoices_overdue,
        href: '/admin/tagihan?status=OVERDUE',
    },
]);
</script>

<template>
    <Head title="Dasbor Admin" />

    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-h3 font-semibold">Dasbor Admin</h1>
        <span class="text-caption text-ink-soft">Diperbarui {{ formatDate(metrics.generated_at) }}</span>
    </div>

    <section class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-3" aria-label="Ringkasan metrik">
        <div class="rounded-brand-sm border border-line p-4">
            <p class="text-caption text-ink-soft">Total RFQ</p>
            <p class="mt-1 text-h3 font-semibold">{{ metrics.rfqs.total }}</p>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <p class="text-caption text-ink-soft">Total Pesanan</p>
            <p class="mt-1 text-h3 font-semibold">{{ metrics.orders.total_count }}</p>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <p class="text-caption text-ink-soft">Nilai Pesanan</p>
            <p class="mt-1 text-label font-semibold">{{ formatPrice(metrics.orders.total_value) }}</p>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <p class="text-caption text-ink-soft">Piutang Beredar</p>
            <p class="mt-1 text-label font-semibold">
                {{ formatPrice(metrics.outstanding_receivables.total) }}
            </p>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <p class="text-caption text-ink-soft">Pembayaran Terverifikasi</p>
            <p class="mt-1 text-label font-semibold">
                {{ formatPrice(metrics.verified_payments.total_amount) }}
            </p>
            <p class="text-caption text-ink-soft">{{ metrics.verified_payments.count }} pembayaran</p>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <p class="text-caption text-ink-soft">Rata-rata TKDN</p>
            <p class="mt-1 text-h3 font-semibold">{{ tkdnLabel }}</p>
        </div>
    </section>

    <section class="mt-10 grid gap-6 lg:grid-cols-2">
        <div>
            <h2 class="text-label font-semibold">Pesanan menurut status</h2>
            <ul v-if="orderBreakdown.length" class="mt-3 divide-y divide-line rounded-brand-sm border border-line">
                <li
                    v-for="[status, group] in orderBreakdown"
                    :key="status"
                    class="flex items-center justify-between px-4 py-2.5"
                >
                    <span class="text-caption text-ink-soft">{{ status }}</span>
                    <span class="text-caption text-ink">
                        {{ group.count }} · {{ formatPrice(group.total_value) }}
                    </span>
                </li>
            </ul>
            <p v-else class="mt-3 text-caption text-ink-soft">Belum ada data pesanan.</p>
        </div>

        <div>
            <h2 class="text-label font-semibold">Piutang menurut status</h2>
            <ul
                v-if="receivablesBreakdown.length"
                class="mt-3 divide-y divide-line rounded-brand-sm border border-line"
            >
                <li
                    v-for="[status, group] in receivablesBreakdown"
                    :key="status"
                    class="flex items-center justify-between px-4 py-2.5"
                >
                    <span class="text-caption text-ink-soft">{{ status }}</span>
                    <span class="text-caption text-ink">{{ group.count }} · {{ formatPrice(group.total) }}</span>
                </li>
            </ul>
            <p v-else class="mt-3 text-caption text-ink-soft">Tidak ada piutang beredar.</p>
        </div>
    </section>

    <section class="mt-10">
        <h2 class="text-label font-semibold">Perlu perhatian</h2>
        <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="queue in queues" :key="queue.key">
                <Link
                    :href="queue.href"
                    class="flex items-center justify-between rounded-brand-sm border border-line px-4 py-3 transition-colors duration-fast hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
                >
                    <span class="text-caption text-ink-soft">{{ queue.label }}</span>
                    <span
                        class="text-label font-semibold"
                        :class="queue.count > 0 ? 'text-brand' : 'text-ink-soft'"
                    >
                        {{ queue.count }}
                    </span>
                </Link>
            </li>
        </ul>
    </section>

    <GovNote />
</template>
