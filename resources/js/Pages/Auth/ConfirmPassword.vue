<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ password: '' });

function submit() {
    form.post('/konfirmasi-kata-sandi', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Konfirmasi Kata Sandi" />
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12 font-sans text-ink">
        <h1 class="text-h3 font-semibold">Konfirmasi kata sandi</h1>
        <p class="mt-2 text-caption text-ink-soft">
            Demi keamanan, konfirmasikan kata sandi Anda untuk melanjutkan.
        </p>

        <form class="mt-8 flex flex-col gap-5" @submit.prevent="submit">
            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Kata sandi</span>
                <input v-model="form.password" type="password" autocomplete="current-password" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
                <span v-if="form.errors.password" class="text-caption text-red-600">{{ form.errors.password }}</span>
            </label>

            <button type="submit" :disabled="form.processing"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse hover:bg-brand-secondary disabled:opacity-60">
                Konfirmasi
            </button>
        </form>
    </div>
</template>