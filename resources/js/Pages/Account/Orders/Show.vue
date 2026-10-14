<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import { formatDate, formatPrice } from '../../../utils/format';

defineProps({
    order: { type: Object, required: true },
});

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head :title="`Pesanan ${order.order_number}`" />

    <div class="flex items-center justify-between">
        <h1 class="text-h3 font-semibold">{{ order.order_number }}</h1>
        <Link
            href="/akun/pesanan"
            class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
        >
            ← Pesanan
        </Link>
    </div>

    <dl class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Status</dt>
            <dd class="mt-1 text-label">{{ order.status_label }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Total</dt>
            <dd class="mt-1 text-label">{{ formatPrice(order.total_amount) }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Tanggal</dt>
            <dd class="mt-1 text-label">{{ formatDate(order.created_at) }}</dd>
        </div>
    </dl>

    <section class="mt-10" aria-labelledby="item-heading">
        <h2 id="item-heading" class="text-label font-semibold">Item</h2>
        <div class="mt-4 overflow-x-auto rounded-brand-sm border border-line">
            <table class="min-w-full divide-y divide-line text-left">
                <thead class="bg-surface-muted">
                    <tr>
                        <th class="px-4 py-3 text-caption font-medium text-ink-soft">SKU</th>
                        <th class="px-4 py-3 text-caption font-medium text-ink-soft">Produk</th>
                        <th class="px-4 py-3 text-caption font-medium text-ink-soft">Jumlah</th>
                        <th class="px-4 py-3 text-caption font-medium text-ink-soft">Harga Satuan</th>
                        <th class="px-4 py-3 text-caption font-medium text-ink-soft">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="item in order.items" :key="item.id">
                        <td class="px-4 py-3 text-caption text-ink-soft">{{ item.product_sku }}</td>
                        <td class="px-4 py-3 text-label text-ink">{{ item.product_title }}</td>
                        <td class="px-4 py-3 text-label text-ink">{{ item.quantity }}</td>
                        <td class="px-4 py-3 text-label text-ink">{{ formatPrice(item.unit_price) }}</td>
                        <td class="px-4 py-3 text-label text-ink">{{ formatPrice(item.subtotal) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-10" aria-labelledby="bast-heading">
        <h2 id="bast-heading" class="text-label font-semibold">BAST</h2>
        <div v-if="order.bast" class="mt-4 rounded-brand-sm border border-line p-4">
            <p class="text-label text-ink">{{ order.bast.bast_number }}</p>
            <p class="mt-1 text-caption text-ink-soft">
                {{ order.bast.status_label }}
                <span v-if="order.bast.signed_date">· {{ formatDate(order.bast.signed_date) }}</span>
            </p>
        </div>
        <p v-else class="mt-4 text-caption text-ink-soft">Belum ada dokumen BAST.</p>
    </section>

    <section class="mt-10" aria-labelledby="tagihan-heading">
        <h2 id="tagihan-heading" class="text-label font-semibold">Tagihan</h2>
        <div v-if="order.invoices.length === 0" class="mt-4 text-caption text-ink-soft">
            Belum ada tagihan.
        </div>
        <ul v-else class="mt-4 divide-y divide-line rounded-brand-sm border border-line">
            <li v-for="invoice in order.invoices" :key="invoice.id">
                <Link
                    :href="`/akun/tagihan/${invoice.id}`"
                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 transition-colors duration-fast hover:bg-surface-muted"
                >
                    <span class="text-label font-medium text-ink">{{ invoice.invoice_number }}</span>
                    <span class="text-caption text-ink-soft">{{ invoice.status_label }}</span>
                    <span class="text-caption text-ink-soft">
                        Dibayar {{ formatPrice(invoice.paid_amount) }} / {{ formatPrice(invoice.grand_total) }}
                    </span>
                    <span class="text-caption text-ink-soft">Jatuh tempo {{ formatDate(invoice.due_date) }}</span>
                </Link>
            </li>
        </ul>
    </section>

    <p class="mt-10 rounded-brand-sm border border-line bg-surface-muted p-4 text-caption text-ink-soft">
        Status pada halaman ini adalah pencatatan internal layanan AJM, bukan status resmi
        pada sistem pengadaan pemerintah (INAPROC/SPSE).
    </p>
</template>
