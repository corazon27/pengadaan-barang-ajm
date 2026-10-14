// Shared presentation formatters. Extracted from the duplicated logic that
// previously lived in ProductCard.vue and ProductDetail.vue so catalog, detail
// and account surfaces render monetary values byte-identically.

export function formatPrice(raw) {
    const amount = Number(raw);

    if (Number.isNaN(amount)) {
        return raw;
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}

export function formatDate(raw) {
    if (!raw) {
        return null;
    }

    const date = new Date(raw);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
}
