<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import StatusFilter from '../../../Components/Admin/StatusFilter.vue';
import GovNote from '../../../Components/Admin/GovNote.vue';
import { formatDate, formatPrice } from '../../../utils/format';

defineProps({
    invoices: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
});

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head title="Antrean Tagihan" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-h3 font-semibold">Antrean Tagihan</h1>
        <StatusFilter :model-value="filters.status" :options="statuses" base-url="/admin/tagihan" />
    </div>

    <div v-if="invoices.length === 0" class="mt-8">
        <EmptyState
            title="Tidak ada tagihan"
            message="Tagihan yang diterbitkan dari BAST akan tampil di bagian ini."
        />
    </div>

    <template v-else>
        <ul class="mt-8 divide-y divide-line rounded-brand-sm border border-line">
            <li v-for="invoice in invoices" :key="invoice.id">
                <Link
                    :href="`/admin/tagihan/${invoice.id}`"
                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 transition-colors duration-fast hover:bg-surface-muted"
                >
                    <span class="text-label font-medium text-ink">{{ invoice.invoice_number }}</span>
                    <span class="text-caption text-ink-soft">{{ invoice.buyer_company ?? '—' }}</span>
                    <span class="text-caption text-ink-soft">{{ invoice.status_label }}</span>
                    <span class="text-caption text-ink-soft">Sisa: {{ formatPrice(invoice.amount_due) }}</span>
                    <span class="text-caption text-ink-soft">Jatuh tempo: {{ formatDate(invoice.due_date) ?? '—' }}</span>
                </Link>
            </li>
        </ul>

        <Pagination :pagination="pagination" :base-url="pagination.baseUrl" />
    </template>

    <GovNote
        message="Status tagihan pada halaman ini adalah pencatatan internal layanan AJM, bukan status resmi pada sistem pengadaan pemerintah (INAPROC/SPSE)."
    />
</template>
