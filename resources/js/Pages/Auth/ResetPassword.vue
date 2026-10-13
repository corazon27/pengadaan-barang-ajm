<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/atur-ulang-kata-sandi', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Atur Ulang Kata Sandi" />
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12 font-sans text-ink">
        <h1 class="text-h3 font-semibold">Atur ulang kata sandi</h1>

        <form class="mt-8 flex flex-col gap-5" @submit.prevent="submit">
            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Email</span>
                <input v-model="form.email" type="email" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
                <span v-if="form.errors.email" class="text-caption text-red-600">{{ form.errors.email }}</span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Kata sandi baru</span>
                <input v-model="form.password" type="password" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
                <span v-if="form.errors.password" class="text-caption text-red-600">{{ form.errors.password }}</span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Ulangi kata sandi baru</span>
                <input v-model="form.password_confirmation" type="password" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
            </label>

            <button type="submit" :disabled="form.processing"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse hover:bg-brand-secondary disabled:opacity-60">
                Simpan kata sandi
            </button>
        </form>
    </div>
</template>