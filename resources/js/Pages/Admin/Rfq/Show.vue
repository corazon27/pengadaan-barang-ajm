<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import GovNote from '../../../Components/Admin/GovNote.vue';
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
            href="/admin/rfq"
            class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
        >
            ← Antrean RFQ
        </Link>
    </div>

    <dl class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-3">
        <div>
            <dt class="text-caption text-ink-soft">Pembeli</dt>
            <dd class="text-label font-medium">{{ rfq.buyer_company ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Nama</dt>
            <dd class="text-label font-medium">{{ rfq.buyer_name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Status</dt>
            <dd class="text-label font-medium">{{ rfq.status_label }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Berlaku Sampai</dt>
            <dd class="text-label font-medium">{{ formatDate(rfq.valid_until) ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-caption text-ink-soft">Diajukan</dt>
            <dd class="text-label font-medium">{{ formatDate(rfq.created_at) }}</dd>
        </div>
    </dl>

    <section v-if="rfq.notes" class="mt-8">
        <h2 class="text-label font-semibold">Catatan Pembeli</h2>
        <p class="mt-2 whitespace-pre-line text-caption text-ink-soft">{{ rfq.notes }}</p>
    </section>

    <section v-if="rfq.admin_notes" class="mt-6">
        <h2 class="text-label font-semibold">Catatan Admin</h2>
        <p class="mt-2 whitespace-pre-line text-caption text-ink-soft">{{ rfq.admin_notes }}</p>
    </section>

    <section class="mt-8">
        <h2 class="text-label font-semibold">Item</h2>
        <ul class="mt-3 divide-y divide-line rounded-brand-sm border border-line">
            <li
                v-for="item in rfq.items"
                :key="item.id"
                class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
            >
                <div>
                    <p class="text-label font-medium">{{ item.product_title ?? item.product_sku ?? '—' }}</p>
                    <p class="text-caption text-ink-soft">{{ item.product_sku ?? '—' }}</p>
                </div>
                <span class="text-caption text-ink-soft">{{ item.quantity }} unit</span>
                <span class="text-caption text-ink-soft">
                    Target: {{ item.target_price === null ? '—' : formatPrice(item.target_price) }}
                </span>
                <span class="text-caption text-ink-soft">
                    Negosiasi: {{ item.negotiated_price === null ? '—' : formatPrice(item.negotiated_price) }}
                </span>
            </li>
        </ul>
    </section>

    <GovNote
        message="Status RFQ pada halaman ini adalah pencatatan internal layanan AJM, bukan status resmi pada sistem pengadaan pemerintah (INAPROC/SPSE)."
    />
</template>
