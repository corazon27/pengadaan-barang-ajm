<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const { props } = usePage();
const form = useForm({ email: '' });

function submit() {
    form.post('/lupa-kata-sandi');
}
</script>

<template>
    <Head title="Lupa Kata Sandi" />
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12 font-sans text-ink">
        <h1 class="text-h3 font-semibold">Lupa kata sandi</h1>
        <p class="mt-2 text-caption text-ink-soft">
            Masukkan email Anda. Jika terdaftar, kami akan mengirim tautan pengaturan ulang.
        </p>

        <p v-if="props.flash?.status" class="mt-6 rounded-brand-sm border border-line px-4 py-3 text-caption">
            {{ props.flash.status }}
        </p>

        <form class="mt-8 flex flex-col gap-5" @submit.prevent="submit">
            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Email</span>
                <input v-model="form.email" type="email" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
                <span v-if="form.errors.email" class="text-caption text-red-600">{{ form.errors.email }}</span>
            </label>
            <button type="submit" :disabled="form.processing"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse hover:bg-brand-secondary disabled:opacity-60">
                Kirim tautan
            </button>
        </form>

        <p class="mt-6 text-caption text-ink-soft">
            <Link href="/masuk" class="font-medium text-brand hover:underline">Kembali ke halaman masuk</Link>
        </p>
    </div>
</template>