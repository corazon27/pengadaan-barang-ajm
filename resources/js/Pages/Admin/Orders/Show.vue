<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import GovNote from '../../../Components/Admin/GovNote.vue';
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
            href="/admin/pesanan"
            class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
        >
            ← Antrean Pesanan
        </Link>
    </div>

    <dl class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-3">
        <div>
            <dt class="text-caption text-ink-soft">Pembeli</dt>
            <dd class="text-label font-medium">{{ order.buyer_company ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Nama</dt>
            <dd class="text-label font-medium">{{ order.buyer_name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Status</dt>
            <dd class="text-label font-medium">{{ order.status_label }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Total</dt>
            <dd class="text-label font-medium">{{ formatPrice(order.total_amount) }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">TOP (hari)</dt>
            <dd class="text-label font-medium">{{ order.top_days ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Dibuat</dt>
            <dd class="text-label font-medium">{{ formatDate(order.created_at) }}</dd>
        </div>
    </dl>

    <section class="mt-8">
        <h2 class="text-label font-semibold">Item</h2>
        <ul class="mt-3 divide-y divide-line rounded-brand-sm border border-line">
            <li
                v-for="item in order.items"
                :key="item.id"
                class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
            >
                <div>
                    <p class="text-label font-medium">{{ item.product_title ?? '—' }}</p>
                    <p class="text-caption text-ink-soft">{{ item.product_sku ?? '—' }}</p>
                </div>
                <span class="text-caption text-ink-soft">{{ item.quantity }} × {{ formatPrice(item.unit_price) }}</span>
                <span class="text-label font-medium">{{ formatPrice(item.subtotal) }}</span>
            </li>
        </ul>
    </section>

    <section v-if="order.bast" class="mt-8">
        <h2 class="text-label font-semibold">BAST</h2>
        <dl class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-3">
            <div>
                <dt class="text-caption text-ink-soft">Nomor</dt>
                <dd class="text-label font-medium">{{ order.bast.bast_number }}</dd>
            </div>
            <div>
                <dt class="text-caption text-ink-soft">Status</dt>
                <dd class="text-label font-medium">{{ order.bast.status_label }}</dd>
            </div>
            <div>
                <dt class="text-caption text-ink-soft">Tanggal Tanda Tangan</dt>
                <dd class="text-label font-medium">{{ formatDate(order.bast.signed_date) ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    <section class="mt-8">
        <h2 class="text-label font-semibold">Tagihan</h2>
        <p v-if="order.invoices.length === 0" class="mt-3 text-caption text-ink-soft">
            Belum ada tagihan.
        </p>
        <ul v-else class="mt-3 divide-y divide-line rounded-brand-sm border border-line">
            <li
                v-for="invoice in order.invoices"
                :key="invoice.id"
                class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
            >
                <Link
                    :href="`/admin/tagihan/${invoice.id}`"
                    class="text-label font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
                >
                    {{ invoice.invoice_number }}
                </Link>
                <span class="text-caption text-ink-soft">{{ invoice.status_label }}</span>
                <span class="text-caption text-ink-soft">
                    Sisa: {{ formatPrice(invoice.amount_due) }}
                </span>
                <span class="text-caption text-ink-soft">Jatuh tempo: {{ formatDate(invoice.due_date) ?? '—' }}</span>
            </li>
        </ul>
    </section>

    <GovNote
        message="Status pesanan pada halaman ini adalah pencatatan internal layanan AJM, bukan status resmi pada sistem pengadaan pemerintah (INAPROC/SPSE)."
    />
</template>
