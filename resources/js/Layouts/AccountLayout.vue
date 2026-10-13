<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const { props } = usePage();

const user = computed(() => props.auth?.user ?? null);
const isSuperadmin = computed(() => user.value?.role === 'SUPERADMIN');

function logout() {
    router.post('/keluar');
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-surface font-sans text-ink antialiased">
        <header class="border-b border-line">
            <div
                class="mx-auto flex h-16 w-full max-w-5xl items-center justify-between px-6 sm:px-8"
            >
                <Link
                    href="/"
                    class="inline-flex items-center gap-3 rounded-brand-sm text-label font-semibold text-ink transition-colors duration-fast hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
                >
                    <span class="h-2.5 w-2.5 bg-brand" aria-hidden="true"></span>
                    <span>{{ props.appName }}</span>
                </Link>

                <nav class="flex items-center gap-6" aria-label="Navigasi akun">
                    <Link
                        href="/akun"
                        class="text-caption font-medium uppercase tracking-[0.2em] text-ink transition-colors duration-fast hover:text-brand"
                    >
                        Akun
                    </Link>
                    <Link
                        v-if="isSuperadmin"
                        href="/admin/akun"
                        class="text-caption font-medium uppercase tracking-[0.2em] text-ink transition-colors duration-fast hover:text-brand"
                    >
                        Admin
                    </Link>
                    <button
                        type="button"
                        class="rounded-brand-sm border border-line px-3 py-1.5 text-caption font-medium uppercase tracking-[0.2em] text-ink transition-colors duration-fast hover:border-brand-secondary hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-6 py-10 sm:px-8">
            <slot />
        </main>
    </div>
</template>