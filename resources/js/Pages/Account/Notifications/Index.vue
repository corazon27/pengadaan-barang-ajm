<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import { formatDate } from '../../../utils/format';

defineProps({
    notifications: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
});

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head title="Notifikasi" />

    <div class="flex items-center justify-between">
        <h1 class="text-h3 font-semibold">Notifikasi</h1>
        <Link
            href="/akun"
            class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
        >
            ← Akun
        </Link>
    </div>

    <p class="mt-2 text-caption text-ink-soft">Hanya baca.</p>

    <div v-if="notifications.length === 0" class="mt-8">
        <EmptyState
            title="Belum ada notifikasi"
            message="Pemberitahuan terkait aktivitas akun Anda akan tampil di bagian ini."
        />
    </div>

    <template v-else>
        <ul class="mt-8 divide-y divide-line rounded-brand-sm border border-line">
            <li
                v-for="notification in notifications"
                :key="notification.id"
                class="px-4 py-3"
                :class="notification.is_read ? '' : 'bg-surface-muted'"
            >
                <div class="flex items-start justify-between gap-3">
                    <p class="text-label font-medium text-ink">
                        {{ notification.title ?? notification.type }}
                    </p>
                    <span
                        v-if="!notification.is_read"
                        class="shrink-0 text-caption font-medium text-brand"
                    >
                        Baru
                    </span>
                </div>
                <p v-if="notification.message" class="mt-1 text-caption text-ink-soft">
                    {{ notification.message }}
                </p>
                <p class="mt-1 text-caption text-ink-soft">{{ formatDate(notification.created_at) }}</p>
            </li>
        </ul>

        <Pagination :pagination="pagination" :base-url="pagination.baseUrl" />
    </template>

    <p class="mt-10 rounded-brand-sm border border-line bg-surface-muted p-4 text-caption text-ink-soft">
        Informasi pada halaman ini adalah pemberitahuan internal layanan AJM, bukan
        dokumen resmi pada sistem pengadaan pemerintah (INAPROC/SPSE).
    </p>
</template>
