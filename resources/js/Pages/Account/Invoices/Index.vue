<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import { formatDate, formatPrice } from '../../../utils/format';

defineProps({
    invoices: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
});

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head title="Tagihan" />

    <div class="flex items-center justify-between">
        <h1 class="text-h3 font-semibold">Tagihan</h1>
        <Link
            href="/akun"
            class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
        >
            ← Akun
        </Link>
    </div>

    <div v-if="invoices.length === 0" class="mt-8">
        <EmptyState
            title="Belum ada tagihan"
            message="Tagihan yang diterbitkan atas pesanan Anda akan tampil di bagian ini."
        />
    </div>

    <template v-else>
        <ul class="mt-8 divide-y divide-line rounded-brand-sm border border-line">
            <li v-for="invoice in invoices" :key="invoice.id">
                <Link
                    :href="`/akun/tagihan/${invoice.id}`"
                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 transition-colors duration-fast hover:bg-surface-muted"
                >
                    <span class="text-label font-medium text-ink">{{ invoice.invoice_number }}</span>
                    <span class="text-caption text-ink-soft">{{ invoice.status_label }}</span>
                    <span class="text-caption text-ink-soft">{{ formatPrice(invoice.grand_total) }}</span>
                    <span class="text-caption text-ink-soft">Jatuh tempo {{ formatDate(invoice.due_date) }}</span>
                </Link>
            </li>
        </ul>

        <Pagination :pagination="pagination" :base-url="pagination.baseUrl" />
    </template>

    <p class="mt-10 rounded-brand-sm border border-line bg-surface-muted p-4 text-caption text-ink-soft">
        Status tagihan pada halaman ini adalah pencatatan internal layanan AJM, bukan
        dokumen resmi pada sistem pengadaan pemerintah (INAPROC/SPSE).
    </p>
</template>
