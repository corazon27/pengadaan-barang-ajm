<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    actionUrl: { type: String, required: true },
    requestedRole: { type: String, default: null },
});

const form = useForm({
    evidence_type: '',
    institution_name: '',
    work_unit: '',
    position: '',
    reference: '',
    file: null,
});

function onFile(event) {
    form.file = event.target.files[0] ?? null;
}

function submit() {
    form.post(props.actionUrl, { forceFormData: true });
}
</script>

<template>
    <Head title="Unggah Bukti B2G" />
    <div class="mx-auto max-w-xl px-6 py-12 font-sans text-ink">
        <h1 class="text-h3 font-semibold">Unggah bukti B2G</h1>
        <p class="mt-2 text-caption text-ink-soft">
            Bukti akan ditinjau administrator sebelum peran B2G diberikan.
        </p>

        <form class="mt-8 grid gap-5" @submit.prevent="submit">
            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Jenis bukti</span>
                <input v-model="form.evidence_type" type="text" required maxlength="100"
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
                <span v-if="form.errors.evidence_type" class="text-caption text-red-600">{{ form.errors.evidence_type }}</span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Nama instansi (opsional)</span>
                <input v-model="form.institution_name" type="text" maxlength="200"
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Unit kerja (opsional)</span>
                <input v-model="form.work_unit" type="text" maxlength="200"
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Jabatan (opsional)</span>
                <input v-model="form.position" type="text" maxlength="200"
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Referensi (opsional)</span>
                <input v-model="form.reference" type="text" maxlength="500"
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink" />
            </label>

            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Berkas (PDF/JPG/PNG, maks 5 MB)</span>
                <input type="file" accept=".pdf,.jpg,.jpeg,.png" required @change="onFile" />
                <span v-if="form.errors.file" class="text-caption text-red-600">{{ form.errors.file }}</span>
            </label>

            <button type="submit" :disabled="form.processing"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse hover:bg-brand-secondary disabled:opacity-60">
                Unggah
            </button>
        </form>
    </div>
</template>