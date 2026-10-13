<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: { type: String, required: true },
    alreadyVerified: { type: Boolean, default: false },
    actionUrl: { type: String, required: true },
});

const form = useForm({});

function confirm() {
    form.post(props.actionUrl);
}
</script>

<template>
    <Head title="Verifikasi Email" />
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12 font-sans text-ink">
        <h1 class="text-h3 font-semibold">Verifikasi email</h1>

        <template v-if="alreadyVerified">
            <p class="mt-4 text-label text-ink-soft">
                Email <strong>{{ email }}</strong> sudah diverifikasi sebelumnya.
            </p>
        </template>
        <template v-else>
            <p class="mt-4 text-label text-ink-soft">
                Konfirmasi bahwa email <strong>{{ email }}</strong> benar milik Anda.
            </p>
            <form class="mt-8" @submit.prevent="confirm">
                <button type="submit" :disabled="form.processing"
                    class="inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse hover:bg-brand-secondary disabled:opacity-60">
                    Verifikasi email saya
                </button>
            </form>
        </template>
    </div>
</template>