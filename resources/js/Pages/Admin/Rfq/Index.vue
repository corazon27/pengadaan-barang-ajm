<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import StatusFilter from '../../../Components/Admin/StatusFilter.vue';
import GovNote from '../../../Components/Admin/GovNote.vue';
import { formatDate } from '../../../utils/format';

defineProps({
    rfqs: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
});

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head title="Antrean RFQ" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-h3 font-semibold">Antrean RFQ</h1>
        <StatusFilter :model-value="filters.status" :options="statuses" base-url="/admin/rfq" />
    </div>

    <div v-if="rfqs.length === 0" class="mt-8">
        <EmptyState
            title="Tidak ada RFQ"
            message="Permintaan penawaran dari pembeli akan tampil di bagian ini."
        />
    </div>

    <template v-else>
        <ul class="mt-8 divide-y divide-line rounded-brand-sm border border-line">
            <li v-for="rfq in rfqs" :key="rfq.id">
                <Link
                    :href="`/admin/rfq/${rfq.id}`"
                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 transition-colors duration-fast hover:bg-surface-muted"
                >
                    <span class="text-label font-medium text-ink">{{ rfq.rfq_number }}</span>
                    <span class="text-caption text-ink-soft">{{ rfq.buyer_company ?? '—' }}</span>
                    <span class="text-caption text-ink-soft">{{ rfq.items_count }} item</span>
                    <span class="text-caption text-ink-soft">{{ rfq.status_label }}</span>
                    <span class="text-caption text-ink-soft">{{ formatDate(rfq.created_at) }}</span>
                </Link>
            </li>
        </ul>

        <Pagination :pagination="pagination" :base-url="pagination.baseUrl" />
    </template>

    <GovNote
        message="Status RFQ pada halaman ini adalah pencatatan internal layanan AJM, bukan status resmi pada sistem pengadaan pemerintah (INAPROC/SPSE)."
    />
</template>
