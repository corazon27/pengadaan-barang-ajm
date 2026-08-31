<script setup>
defineProps({
    id: { type: String, default: null },
});
</script>

<template>
    <section :id="id || undefined" class="py-12 sm:py-14 lg:py-20">
        <slot />
    </section>
</template>