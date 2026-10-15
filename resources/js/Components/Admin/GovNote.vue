<script setup>
defineProps({
    message: {
        type: String,
        default:
            'Status pada halaman ini adalah pencatatan internal layanan AJM, bukan status resmi pada sistem pengadaan pemerintah (INAPROC/SPSE).',
    },
});
</script>

<template>
    <p class="mt-10 rounded-brand-sm border border-line bg-surface-muted p-4 text-caption text-ink-soft">
        {{ message }}
    </p>
</template>
