<script setup>
import Header from '../Components/Header.vue';
import Footer from '../Components/Footer.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-surface font-sans text-ink antialiased">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-brand-sm focus:bg-brand focus:px-4 focus:py-2 focus:text-label focus:font-medium focus:text-inverse"
        >
            Langsung ke konten utama
        </a>

        <Header />

        <main id="main-content" :tabindex="-1" class="flex-1 focus:outline-none">
            <slot />
        </main>

        <Footer />
    </div>
</template>