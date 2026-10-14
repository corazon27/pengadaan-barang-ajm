<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../Layouts/AccountLayout.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { formatDate, formatPrice } from '../../utils/format';

const props = defineProps({
    account: { type: Object, required: true },
    summary: { type: Object, required: true },
    recentOrders: { type: Array, default: () => [] },
});

defineOptions({ layout: AccountLayout });

const roleLabels = {
    SUPERADMIN: 'Administrator',
    BUYER_B2B: 'Pembeli (B2B)',
    BUYER_B2G: 'Pembeli (B2G)',
};

const statusLabels = {
    PENDING: 'Menunggu Persetujuan',
    ACTIVE: 'Aktif',
    SUSPENDED: 'Ditangguhkan',
    REJECTED: 'Ditolak',
};

const roleLabel = computed(() => roleLabels[props.account.role] ?? props.account.role);
const statusLabel = computed(() => statusLabels[props.account.status] ?? props.account.status);

const tiles = computed(() => [
    {
        key: 'rfqs_open',
        label: 'RFQ Aktif',
        count: props.summary.rfqs_open,
        href: '/akun/rfq',
    },
    {
        key: 'orders_active',
        label: 'Pesanan Berjalan',
        count: props.summary.orders_active,
        href: '/akun/pesanan',
    },
    {
        key: 'invoices_unpaid',
        label: 'Tagihan Belum Lunas',
        count: props.summary.invoices_unpaid,
        href: '/akun/tagihan',
    },
    {
        key: 'notifications_unread',
        label: 'Notifikasi Baru',
        count: props.summary.notifications_unread,
        href: '/akun/notifikasi',
    },
]);
</script>

<template>
    <Head title="Akun Saya" />
    <h1 class="text-h3 font-semibold">Akun saya</h1>

    <dl class="mt-8 grid gap-4 sm:grid-cols-2">
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Nama</dt>
            <dd class="mt-1 text-label">{{ account.full_name }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Email</dt>
            <dd class="mt-1 text-label">{{ account.email }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Perusahaan</dt>
            <dd class="mt-1 text-label">{{ account.company_name }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Peran</dt>
            <dd class="mt-1 text-label">{{ roleLabel }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Status Akun</dt>
            <dd class="mt-1 text-label">{{ statusLabel }}</dd>
        </div>
    </dl>

    <section class="mt-10" aria-labelledby="ringkasan-heading">
        <h2 id="ringkasan-heading" class="text-label font-semibold">Ringkasan</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                v-for="tile in tiles"
                :key="tile.key"
                :href="tile.href"
                class="rounded-brand-sm border border-line p-4 transition-colors duration-fast hover:border-brand-secondary"
            >
                <span class="block text-h3 font-semibold text-ink">{{ tile.count }}</span>
                <span class="mt-1 block text-caption font-medium text-ink-soft">{{ tile.label }}</span>
            </Link>
        </div>
    </section>

    <section class="mt-10" aria-labelledby="pesanan-terbaru-heading">
        <div class="flex items-center justify-between">
            <h2 id="pesanan-terbaru-heading" class="text-label font-semibold">Pesanan terbaru</h2>
            <Link
                href="/akun/pesanan"
                class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
            >
                Lihat semua
            </Link>
        </div>

        <div v-if="recentOrders.length === 0" class="mt-4">
            <EmptyState
                title="Belum ada pesanan"
                message="Pesanan yang dibuat dari RFQ yang disetujui akan tampil di bagian ini."
            />
        </div>

        <ul v-else class="mt-4 divide-y divide-line rounded-brand-sm border border-line">
            <li v-for="order in recentOrders" :key="order.id">
                <Link
                    :href="`/akun/pesanan/${order.id}`"
                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 transition-colors duration-fast hover:bg-surface-muted"
                >
                    <span class="text-label font-medium text-ink">{{ order.order_number }}</span>
                    <span class="text-caption text-ink-soft">{{ formatDate(order.created_at) }}</span>
                    <span class="text-caption text-ink-soft">{{ order.status_label }}</span>
                    <span class="text-label font-medium text-ink">{{ formatPrice(order.total_amount) }}</span>
                </Link>
            </li>
        </ul>
    </section>

    <section class="mt-10" aria-labelledby="tautan-cepat-heading">
        <h2 id="tautan-cepat-heading" class="text-label font-semibold">Tautan cepat</h2>
        <div class="mt-4 flex flex-wrap gap-3">
            <Link
                href="/akun/pesanan"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm border border-line bg-surface px-5 py-2.5 text-label font-medium text-ink transition duration-normal hover:border-brand-secondary hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
            >
                Pesanan
            </Link>
            <Link
                href="/akun/rfq"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm border border-line bg-surface px-5 py-2.5 text-label font-medium text-ink transition duration-normal hover:border-brand-secondary hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
            >
                RFQ
            </Link>
            <Link
                href="/akun/tagihan"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm border border-line bg-surface px-5 py-2.5 text-label font-medium text-ink transition duration-normal hover:border-brand-secondary hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
            >
                Tagihan
            </Link>
            <Link
                href="/akun/notifikasi"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm border border-line bg-surface px-5 py-2.5 text-label font-medium text-ink transition duration-normal hover:border-brand-secondary hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
            >
                Notifikasi
            </Link>
        </div>
    </section>

    <p class="mt-10 rounded-brand-sm border border-line bg-surface-muted p-4 text-caption text-ink-soft">
        Status pesanan, RFQ, tagihan dan dokumen BAST yang ditampilkan di sini adalah
        pencatatan internal layanan AJM. Ini bukan status resmi pada sistem pengadaan
        pemerintah (INAPROC/SPSE).
    </p>
</template>
