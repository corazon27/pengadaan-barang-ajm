<script setup>
defineProps({
    as: { type: String, default: 'div' },
});
</script>

<template>
    <component :is="as" class="mx-auto w-full max-w-7xl px-6 sm:px-8 lg:px-10">
        <slot />
    </component>
</template>