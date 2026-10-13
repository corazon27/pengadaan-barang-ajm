<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';

defineProps({
    applications: { type: Array, default: () => [] },
});

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head title="Tinjau Akun" />
    <h1 class="text-h3 font-semibold">Pengajuan akun</h1>
    <p class="mt-2 text-caption text-ink-soft">Daftar pengajuan berstatus menunggu peninjauan.</p>

    <p v-if="applications.length === 0" class="mt-8 rounded-brand-sm border border-line p-6 text-label text-ink-soft">
        Tidak ada pengajuan yang menunggu.
    </p>

    <ul v-else class="mt-8 flex flex-col gap-3">
        <li v-for="application in applications" :key="application.id"
            class="flex items-center justify-between rounded-brand-sm border border-line p-4">
            <div>
                <p class="text-label font-medium">{{ application.full_name }}</p>
                <p class="text-caption text-ink-soft">{{ application.email }} · {{ application.company_name }}</p>
                <p v-if="application.requested_role" class="text-caption text-brand">
                    Meminta: {{ application.requested_role }}
                </p>
            </div>
            <Link :href="`/admin/akun/${application.id}`"
                class="rounded-brand-sm border border-line px-4 py-2 text-caption font-medium hover:border-brand-secondary hover:text-brand">
                Tinjau
            </Link>
        </li>
    </ul>
</template>