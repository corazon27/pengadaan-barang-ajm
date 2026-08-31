<template>
    <!--
        Branded "no image" field. The product schema has no image column, so this
        region is intentionally non-product: a calm soft field with a single small
        registration mark (the one structural accent shared with the 3C.3 system).
        The hero grid/crosshair composition is NOT repeated here to avoid reading
        as a miniature hero panel. Purely decorative — product identity is already
        present as real text below, so it is hidden from assistive tech.
    -->
    <div
        aria-hidden="true"
        class="flex h-44 shrink-0 items-center justify-center overflow-hidden border-b border-line bg-brand-soft/40"
    >
        <span class="relative block h-4 w-4 border border-ink/20">
            <span
                class="absolute left-1/2 top-1/2 h-1 w-1 -translate-x-1/2 -translate-y-1/2 bg-brand"
            ></span>
        </span>
    </div>
</template>
