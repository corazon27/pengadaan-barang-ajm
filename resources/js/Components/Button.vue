<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, default: null },
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'button' },
});

const variantClasses = {
    primary:
        'inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse transition duration-normal hover:bg-brand-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2 active:scale-[0.98]',
    secondary:
        'inline-flex min-h-11 items-center justify-center rounded-brand-sm border border-line bg-surface px-5 py-2.5 text-label font-medium text-ink transition duration-normal hover:border-brand-secondary hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2 active:scale-[0.98]',
};
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :class="variantClasses[variant]"
    >
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :class="variantClasses[variant]"
    >
        <slot />
    </button>
</template>