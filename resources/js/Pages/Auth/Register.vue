<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    password_confirmation: '',
    full_name: '',
    company_name: '',
    address: '',
    phone_number: '',
    requested_role: null,
});

function submit() {
    form.post('/daftar', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Daftar" />
    <div class="mx-auto max-w-xl px-6 py-12 font-sans text-ink">
        <h1 class="text-h3 font-semibold">Pendaftaran Akun</h1>
        <p class="mt-2 text-caption text-ink-soft">
            Akun akan ditinjau administrator setelah email diverifikasi.
        </p>

        <form class="mt-8 grid gap-5" @submit.prevent="submit">
            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Nama lengkap</span>
                <input v-model="form.full_name" type="text" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
                <span v-if="form.errors.full_name" class="text-caption text-red-600">{{ form.errors.full_name }}</span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Email</span>
                <input v-model="form.email" type="email" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
                <span v-if="form.errors.email" class="text-caption text-red-600">{{ form.errors.email }}</span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Nama perusahaan</span>
                <input v-model="form.company_name" type="text" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
                <span v-if="form.errors.company_name" class="text-caption text-red-600">{{ form.errors.company_name }}</span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Alamat</span>
                <textarea v-model="form.address" rows="3" required
                    class="rounded-brand-sm border border-line bg-surface px-3 py-2 text-label text-ink"></textarea>
                <span v-if="form.errors.address" class="text-caption text-red-600">{{ form.errors.address }}</span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Nomor telepon</span>
                <input v-model="form.phone_number" type="text" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
                <span v-if="form.errors.phone_number" class="text-caption text-red-600">{{ form.errors.phone_number }}</span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Kata sandi</span>
                <input v-model="form.password" type="password" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
                <span v-if="form.errors.password" class="text-caption text-red-600">{{ form.errors.password }}</span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Ulangi kata sandi</span>
                <input v-model="form.password_confirmation" type="password" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Peran yang diminta (opsional)</span>
                <select v-model="form.requested_role"
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink">
                    <option :value="null">B2B (default)</option>
                    <option value="BUYER_B2G">B2G (perlu bukti)</option>
                </select>
                <span v-if="form.errors.requested_role" class="text-caption text-red-600">{{ form.errors.requested_role }}</span>
            </label>

            <button type="submit" :disabled="form.processing"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse transition duration-normal hover:bg-brand-secondary disabled:opacity-60">
                Daftar
            </button>
        </form>

        <p class="mt-6 text-caption text-ink-soft">
            Sudah punya akun? <Link href="/masuk" class="font-medium text-brand hover:underline">Masuk</Link>
        </p>
    </div>
</template>