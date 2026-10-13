<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    requestedRole: { type: String, default: null },
    actionUrl: { type: String, required: true },
});

const form = useForm({ requested_role: props.requestedRole });

function submit() {
    form.post(props.actionUrl);
}
</script>

<template>
    <Head title="Kirim Ulang Pengajuan" />
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12 font-sans text-ink">
        <h1 class="text-h3 font-semibold">Kirim ulang pengajuan</h1>
        <p class="mt-2 text-caption text-ink-soft">
            Pengajuan Anda akan kembali berstatus menunggu peninjauan administrator.
        </p>

        <form class="mt-8 flex flex-col gap-5" @submit.prevent="submit">
            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Peran yang diminta</span>
                <select v-model="form.requested_role"
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink">
                    <option :value="null">B2B (default)</option>
                    <option value="BUYER_B2G">B2G (perlu bukti)</option>
                </select>
                <span v-if="form.errors.requested_role" class="text-caption text-red-600">{{ form.errors.requested_role }}</span>
            </label>

            <button type="submit" :disabled="form.processing"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse hover:bg-brand-secondary disabled:opacity-60">
                Kirim ulang
            </button>
        </form>
    </div>
</template>