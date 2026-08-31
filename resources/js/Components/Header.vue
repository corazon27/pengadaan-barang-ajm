<script setup>
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const { props } = usePage();

// GATE A: catalog (3C.4) and Cara Order page (3C.7) routes do not exist yet.
// These labels render as static, non-interactive placeholders and become real
// anchor/route links only when their destinations exist. No dead links.
const navItems = [
    { label: 'Produk' },
    { label: 'Cara Order' },
    { label: 'Kontak' },
];
</script>

<template>
    <header class="bg-surface">
        <!-- Structural top frame: navy index bar (navy = structure) -->
        <div class="bg-ink">
            <div
                class="mx-auto flex h-9 w-full max-w-7xl items-center justify-between px-6 text-inverse sm:px-8 lg:px-10"
            >
                <p class="text-caption font-medium uppercase tracking-[0.2em] text-inverse">
                    Katalog &amp; Platform Pengadaan
                </p>
                <p class="text-caption font-medium uppercase tracking-[0.2em] text-inverse/90">
                    B2B · B2G — {{ new Date().getFullYear() }}
                </p>
            </div>
        </div>

        <div
            class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between border-b border-line px-6 sm:px-8 lg:px-10"
        >
            <Link
                href="/"
                class="group inline-flex items-center gap-3 rounded-brand-sm text-label font-semibold tracking-tight text-ink transition-colors duration-fast hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
            >
                <span class="h-2.5 w-2.5 bg-brand" aria-hidden="true"></span>
                <span>{{ props.appName }}</span>
            </Link>

            <ul class="hidden items-center gap-8 md:flex" aria-label="Navigasi utama">
                <li v-for="item in navItems" :key="item.label">
                    <span
                        class="cursor-default text-caption font-medium uppercase tracking-[0.2em] text-ink-soft/60"
                        :title="`${item.label} — tersedia pada tahap berikutnya`"
                        tabindex="-1"
                    >
                        {{ item.label }}
                    </span>
                </li>
            </ul>
        </div>
    </header>
</template>
