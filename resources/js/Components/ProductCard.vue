<script setup>
import { computed } from 'vue';
import ProductImage from './ProductImage.vue';

// Procurement information object — intentionally NOT navigational in 3C.4 (no
// product-detail route yet). Information hierarchy (per GATE A reconciliation):
//   1. identity  (image, SKU, title)
//   2. price     (monetary anchor)
//   3. availability + public spec markers (TKDN/SNI presence flags)
//   4. limited supporting metadata (description, warranty)
// TKDN/SNI are display-only presence flags, NOT certifications.
const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const formatPrice = (raw) => {
    const amount = Number(raw);

    if (Number.isNaN(amount)) {
        return raw;
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
};

// TKDN shown only when a meaningful positive local-content value exists — avoids
// noise like "TKDN 0.00%" while staying a pure presence display (no certification claim).
const showTkdn = computed(() => {
    const value = props.product.tkdn;

    if (value === null || value === undefined) {
        return false;
    }

    return Number(value) > 0;
});

const tkdnLabel = computed(() =>
    props.product.tkdn === null || props.product.tkdn === undefined
        ? ''
        : String(Number(props.product.tkdn)),
);
</script>

<template>
    <article class="flex flex-col overflow-hidden rounded-brand-md border border-line bg-surface">
        <ProductImage />

        <div class="flex flex-1 flex-col p-4">
            <p class="text-caption font-medium uppercase tracking-[0.2em] text-ink-soft">
                {{ product.sku }}
            </p>

            <h3 class="mt-1.5 line-clamp-2 text-lg font-semibold tracking-tight text-ink">
                {{ product.title }}
            </h3>

            <p v-if="product.description" class="mt-1.5 line-clamp-2 text-sm text-ink-soft">
                {{ product.description }}
            </p>

            <div class="mt-auto pt-4">
                <p class="text-xl font-semibold tracking-tight text-ink">
                    {{ formatPrice(product.price) }}
                </p>

                <p
                    class="mt-1.5 flex items-center gap-1.5 text-sm"
                    :class="product.available ? 'text-ink-soft' : 'text-ink-soft/70'"
                >
                    <span aria-hidden="true">{{ product.available ? '✓' : '—' }}</span>
                    {{ product.available ? 'Tersedia' : 'Tidak tersedia' }}
                </p>

                <div class="mt-3 flex min-h-6 flex-wrap items-center gap-2">
                    <span
                        v-if="showTkdn"
                        title="TKDN — nilai komponen dalam negeri"
                        class="inline-flex items-center gap-1.5 rounded-brand-sm bg-brand-soft px-2 py-0.5 text-caption font-medium text-ink"
                    >
                        <span class="h-1 w-1 rounded-full bg-brand" aria-hidden="true"></span>
                        TKDN {{ tkdnLabel }}%
                    </span>
                    <span
                        v-if="product.hasSni"
                        title="SNI — informasi tersedia"
                        class="inline-flex items-center gap-1.5 rounded-brand-sm bg-brand-soft px-2 py-0.5 text-caption font-medium text-ink"
                    >
                        <span class="h-1 w-1 rounded-full bg-brand" aria-hidden="true"></span>
                        SNI
                    </span>
                </div>

                <p v-if="product.warrantyInfo" class="mt-2 text-caption text-ink-soft/70">
                    <span>Garansi&nbsp;</span><span>{{ product.warrantyInfo }}</span>
                </p>
            </div>
        </div>

        <div class="border-t border-line" aria-hidden="true"></div>
    </article>
</template>
