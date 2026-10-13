<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    email: { type: String, required: true },
    status: { type: String, required: true },
    resubmitUrl: { type: String, default: null },
    evidenceUrl: { type: String, default: null },
});

const labels = {
    PENDING: 'Menunggu peninjauan administrator',
    REJECTED: 'Pengajuan ditolak',
    SUSPENDED: 'Akun ditangguhkan',
    ACTIVE: 'Akun aktif',
};
</script>

<template>
    <Head title="Status Akun" />
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12 font-sans text-ink">
        <h1 class="text-h3 font-semibold">Status akun</h1>
        <p class="mt-2 text-caption text-ink-soft">{{ email }}</p>

        <p class="mt-6 rounded-brand-sm border border-line px-4 py-3 text-label">
            {{ labels[status] ?? status }}
        </p>

        <div class="mt-8 flex flex-col gap-3">
            <Link v-if="evidenceUrl" :href="evidenceUrl"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm border border-line px-5 py-2.5 text-label font-medium text-ink hover:border-brand-secondary hover:text-brand">
                Unggah bukti B2G
            </Link>
            <Link v-if="resubmitUrl" :href="resubmitUrl"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse hover:bg-brand-secondary">
                Kirim ulang pengajuan
            </Link>
        </div>
    </div>
</template>