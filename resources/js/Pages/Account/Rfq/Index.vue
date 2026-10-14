<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import { formatDate } from '../../../utils/format';

defineProps({
    rfqs: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
});

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head title="RFQ" />

    <div class="flex items-center justify-between">
        <h1 class="text-h3 font-semibold">Permintaan Penawaran (RFQ)</h1>
        <Link
            href="/akun"
            class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
        >
            ← Akun
        </Link>
    </div>

    <div v-if="rfqs.length === 0" class="mt-8">
        <EmptyState
            title="Belum ada RFQ"
            message="Permintaan penawaran yang Anda ajukan akan tampil di bagian ini."
        />
    </div>

    <template v-else>
        <ul class="mt-8 divide-y divide-line rounded-brand-sm border border-line">
            <li v-for="rfq in rfqs" :key="rfq.id">
                <Link
                    :href="`/akun/rfq/${rfq.id}`"
                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 transition-colors duration-fast hover:bg-surface-muted"
                >
                    <span class="text-label font-medium text-ink">{{ rfq.rfq_number }}</span>
                    <span class="text-caption text-ink-soft">{{ rfq.items_count }} item</span>
                    <span class="text-caption text-ink-soft">{{ rfq.status_label }}</span>
                    <span class="text-caption text-ink-soft">{{ formatDate(rfq.created_at) }}</span>
                </Link>
            </li>
        </ul>

        <Pagination :pagination="pagination" :base-url="pagination.baseUrl" />
    </template>

    <p class="mt-10 rounded-brand-sm border border-line bg-surface-muted p-4 text-caption text-ink-soft">
        Status RFQ pada halaman ini adalah pencatatan internal layanan AJM, bukan
        status resmi pada sistem pengadaan pemerintah (INAPROC/SPSE).
    </p>
</template>
