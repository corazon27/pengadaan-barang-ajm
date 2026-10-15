<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import StatusFilter from '../../../Components/Admin/StatusFilter.vue';
import GovNote from '../../../Components/Admin/GovNote.vue';
import { formatDate, formatPrice } from '../../../utils/format';

defineProps({
    payments: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
});

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head title="Antrean Pembayaran" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-h3 font-semibold">Antrean Pembayaran</h1>
        <StatusFilter :model-value="filters.status" :options="statuses" base-url="/admin/pembayaran" />
    </div>

    <div v-if="payments.length === 0" class="mt-8">
        <EmptyState
            title="Tidak ada pembayaran"
            message="Pembayaran yang dilaporkan pembeli akan tampil di bagian ini."
        />
    </div>

    <template v-else>
        <ul class="mt-8 divide-y divide-line rounded-brand-sm border border-line">
            <li v-for="payment in payments" :key="payment.id">
                <Link
                    :href="`/admin/pembayaran/${payment.id}`"
                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 transition-colors duration-fast hover:bg-surface-muted"
                >
                    <span class="text-label font-medium text-ink">{{ formatPrice(payment.amount) }}</span>
                    <span class="text-caption text-ink-soft">{{ payment.invoice_number ?? '—' }}</span>
                    <span class="text-caption text-ink-soft">{{ payment.buyer_company ?? '—' }}</span>
                    <span class="text-caption text-ink-soft">{{ payment.payment_method_label }}</span>
                    <span class="text-caption text-ink-soft">{{ payment.status_label }}</span>
                    <span class="text-caption text-ink-soft">{{ formatDate(payment.payment_date) ?? '—' }}</span>
                </Link>
            </li>
        </ul>

        <Pagination :pagination="pagination" :base-url="pagination.baseUrl" />
    </template>

    <GovNote
        message="Status pembayaran pada halaman ini adalah pencatatan internal layanan AJM dan menunggu verifikasi admin, bukan konfirmasi resmi dari instansi pemerintah."
    />
</template>
