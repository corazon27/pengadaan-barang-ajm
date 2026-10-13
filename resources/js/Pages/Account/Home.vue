<script setup>
import { Head } from '@inertiajs/vue3';
import AccountLayout from '../../Layouts/AccountLayout.vue';

defineProps({
    account: { type: Object, required: true },
});

defineOptions({ layout: AccountLayout });
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
            <dd class="mt-1 text-label">{{ account.role }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-4">
            <dt class="text-caption font-medium text-ink-soft">Status</dt>
            <dd class="mt-1 text-label">{{ account.status }}</dd>
        </div>
    </dl>
</template>