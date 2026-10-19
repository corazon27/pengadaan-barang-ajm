<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import { formatPrice } from '../../../utils/format';

const props = defineProps({
    products: { type: Array, default: () => [] },
});

const form = useForm({
    notes: '',
    items: [{ product_id: '', quantity: 1, target_price: '', notes: '' }],
});

const productById = computed(() =>
    Object.fromEntries(props.products.map((product) => [product.id, product])),
);

function addItem() {
    form.items.push({ product_id: '', quantity: 1, target_price: '', notes: '' });
}

function removeItem(index) {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
}

function submit() {
    form.post('/akun/rfq');
}

defineOptions({ layout: AccountLayout });
</script>

<template>
    <Head title="Buat RFQ" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-h3 font-semibold">Buat Permintaan Penawaran (RFQ)</h1>
        <Link
            href="/akun/rfq"
            class="text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
        >
            ← RFQ
        </Link>
    </div>

    <p class="mt-3 max-w-2xl text-caption text-ink-soft">
        Pilih produk dan jumlah yang Anda butuhkan. Tim AJM akan menindaklanjuti
        dengan penawaran harga.
    </p>

    <form class="mt-8 grid max-w-3xl gap-5" @submit.prevent="submit">
        <label class="flex flex-col gap-1.5">
            <span class="text-caption font-medium">Catatan (opsional)</span>
            <textarea
                v-model="form.notes"
                rows="2"
                class="rounded-brand-sm border border-line bg-surface px-3 py-2 text-label text-ink"
            ></textarea>
            <span v-if="form.errors.notes" class="text-caption text-red-600">{{ form.errors.notes }}</span>
        </label>

        <div class="grid gap-4">
            <div
                v-for="(item, index) in form.items"
                :key="index"
                class="rounded-brand-sm border border-line p-4"
            >
                <div class="flex items-center justify-between">
                    <span class="text-label font-medium text-ink">Item {{ index + 1 }}</span>
                    <button
                        type="button"
                        class="text-caption font-medium text-red-600 transition-colors duration-fast disabled:cursor-not-allowed disabled:text-ink-soft"
                        :disabled="form.items.length === 1"
                        @click="removeItem(index)"
                    >
                        Hapus
                    </button>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="flex flex-col gap-1.5 sm:col-span-2">
                        <span class="text-caption font-medium">Produk</span>
                        <select
                            v-model="item.product_id"
                            class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink"
                        >
                            <option value="" disabled>Pilih produk</option>
                            <option v-for="product in products" :key="product.id" :value="product.id">
                                {{ product.title }} ({{ product.sku }})
                            </option>
                        </select>
                        <span
                            v-if="item.product_id && productById[item.product_id]"
                            class="text-caption text-ink-soft"
                        >
                            {{ formatPrice(productById[item.product_id].price) }} ·
                            {{ productById[item.product_id].available ? 'Stok tersedia' : 'Stok kosong' }}
                        </span>
                        <span v-if="form.errors[`items.${index}.product_id`]" class="text-caption text-red-600">
                            {{ form.errors[`items.${index}.product_id`] }}
                        </span>
                    </label>

                    <label class="flex flex-col gap-1.5">
                        <span class="text-caption font-medium">Jumlah</span>
                        <input
                            v-model.number="item.quantity"
                            type="number"
                            min="1"
                            class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink"
                        >
                        <span v-if="form.errors[`items.${index}.quantity`]" class="text-caption text-red-600">
                            {{ form.errors[`items.${index}.quantity`] }}
                        </span>
                    </label>

                    <label class="flex flex-col gap-1.5">
                        <span class="text-caption font-medium">Harga target (opsional)</span>
                        <input
                            v-model="item.target_price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink"
                        >
                        <span v-if="form.errors[`items.${index}.target_price`]" class="text-caption text-red-600">
                            {{ form.errors[`items.${index}.target_price`] }}
                        </span>
                    </label>

                    <label class="flex flex-col gap-1.5 sm:col-span-2">
                        <span class="text-caption font-medium">Catatan item (opsional)</span>
                        <input
                            v-model="item.notes"
                            type="text"
                            class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label text-ink"
                        >
                        <span v-if="form.errors[`items.${index}.notes`]" class="text-caption text-red-600">
                            {{ form.errors[`items.${index}.notes`] }}
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <span v-if="form.errors.items" class="text-caption text-red-600">{{ form.errors.items }}</span>

        <button
            type="button"
            class="justify-self-start text-caption font-medium text-brand transition-colors duration-fast hover:text-brand-secondary"
            @click="addItem"
        >
            + Tambah item
        </button>

        <div class="flex items-center gap-3">
            <button
                type="submit"
                :disabled="form.processing"
                class="min-h-11 rounded-brand-sm bg-brand px-5 text-label font-medium text-white transition-colors duration-fast hover:bg-brand-secondary disabled:opacity-60"
            >
                Kirim RFQ
            </button>
            <Link
                href="/akun/rfq"
                class="text-caption font-medium text-ink-soft transition-colors duration-fast hover:text-ink"
            >
                Batal
            </Link>
        </div>
    </form>

    <p class="mt-10 rounded-brand-sm border border-line bg-surface-muted p-4 text-caption text-ink-soft">
        Status RFQ pada halaman ini adalah pencatatan internal layanan AJM, bukan
        status resmi pada sistem pengadaan pemerintah (INAPROC/SPSE).
    </p>
</template>
