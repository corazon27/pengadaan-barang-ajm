<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const { props } = usePage();
const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post('/masuk', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Masuk" />
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12 font-sans text-ink">
        <h1 class="text-h3 font-semibold">Masuk</h1>
        <p class="mt-2 text-caption text-ink-soft">Platform pengadaan barang B2B/B2G.</p>

        <p
            v-if="props.flash?.status"
            class="mt-6 rounded-brand-sm border border-line bg-surface px-4 py-3 text-caption text-ink"
        >
            {{ props.flash.status }}
        </p>

        <form class="mt-8 flex flex-col gap-5" @submit.prevent="submit">
            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Email</span>
                <input
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong"
                />
                <span v-if="form.errors.email" class="text-caption text-red-600">{{ form.errors.email }}</span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Kata sandi</span>
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong"
                />
                <span v-if="form.errors.password" class="text-caption text-red-600">{{ form.errors.password }}</span>
            </label>

            <label class="flex items-center gap-2 text-caption">
                <input v-model="form.remember" type="checkbox" />
                <span>Ingat saya</span>
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse transition duration-normal hover:bg-brand-secondary disabled:opacity-60"
            >
                Masuk
            </button>
        </form>

        <div class="mt-6 flex flex-col gap-2 text-caption text-ink-soft">
            <Link href="/lupa-kata-sandi" class="hover:text-brand">Lupa kata sandi?</Link>
            <span>Belum punya akun? <Link href="/daftar" class="font-medium text-brand hover:underline">Daftar</Link></span>
        </div>
    </div>
</template>