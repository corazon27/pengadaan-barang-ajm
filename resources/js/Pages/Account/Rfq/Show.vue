<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import { formatDate, formatPrice } from '../../../utils/format';

defineProps({
    rfq: { type: Object, required: true },
});

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head :title="`RFQ ${rfq.rfq_number}`" />

    <div class="flex items-center justify-between">
        <h1 class="text-h3 font-semibold">{{ rfq.rfq_number }}</h1>
        <Link
            href="/akun/rfq"
            class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
        >
            ← RFQ
        </Link>
    </div>

    <dl class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Status</dt>
            <dd class="mt-1 text-label">{{ rfq.status_label }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Tanggal</dt>
            <dd class="mt-1 text-label">{{ formatDate(rfq.created_at) }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Berlaku sampai</dt>
            <dd class="mt-1 text-label">{{ formatDate(rfq.valid_until) ?? '—' }}</dd>
        </div>
    </dl>

    <section v-if="rfq.notes" class="mt-8" aria-labelledby="catatan-heading">
        <h2 id="catatan-heading" class="text-label font-semibold">Catatan</h2>
        <p class="mt-2 text-label text-ink-soft">{{ rfq.notes }}</p>
    </section>

    <section class="mt-10" aria-labelledby="item-heading">
        <h2 id="item-heading" class="text-label font-semibold">Item</h2>
        <div class="mt-4 overflow-x-auto rounded-brand-sm border border-line">
            <table class="min-w-full divide-y divide-line text-left">
                <thead class="bg-surface-muted">
                    <tr>
                        <th class="px-4 py-3 text-caption font-medium text-ink-soft">SKU</th>
                        <th class="px-4 py-3 text-caption font-medium text-ink-soft">Produk</th>
                        <th class="px-4 py-3 text-caption font-medium text-ink-soft">Jumlah</th>
                        <th class="px-4 py-3 text-caption font-medium text-ink-soft">Harga Target</th>
                        <th class="px-4 py-3 text-caption font-medium text-ink-soft">Harga Negosiasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="item in rfq.items" :key="item.id">
                        <td class="px-4 py-3 text-caption text-ink-soft">{{ item.product_sku ?? '—' }}</td>
                        <td class="px-4 py-3 text-label text-ink">{{ item.product_title ?? '—' }}</td>
                        <td class="px-4 py-3 text-label text-ink">{{ item.quantity }}</td>
                        <td class="px-4 py-3 text-label text-ink">
                            {{ item.target_price === null ? '—' : formatPrice(item.target_price) }}
                        </td>
                        <td class="px-4 py-3 text-label text-ink">
                            {{ item.negotiated_price === null ? '—' : formatPrice(item.negotiated_price) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <p class="mt-10 rounded-brand-sm border border-line bg-surface-muted p-4 text-caption text-ink-soft">
        Status RFQ pada halaman ini adalah pencatatan internal layanan AJM, bukan status resmi
        pada sistem pengadaan pemerintah (INAPROC/SPSE).
    </p>
</template>
