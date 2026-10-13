<script setup>
import { reactive } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';

const props = defineProps({
    account: { type: Object, required: true },
    evidence: { type: Array, default: () => [] },
});

defineOptions({ layout: AccountLayout });

const approveForm = useForm({ role: 'BUYER_B2B', reason: '' });
const reasonForm = useForm({ reason: '' });
const reviewState = reactive({});

function reviewField(id) {
    if (!reviewState[id]) {
        reviewState[id] = { reason: '', form: useForm({ decision: '', reason: '' }) };
    }

    return reviewState[id];
}

function approve() {
    approveForm.post(`/admin/akun/${props.account.id}/setujui`, { preserveScroll: true });
}

function reject() {
    reasonForm.post(`/admin/akun/${props.account.id}/tolak`, { preserveScroll: true });
}

function suspend() {
    reasonForm.post(`/admin/akun/${props.account.id}/tangguhkan`, { preserveScroll: true });
}

function reactivate() {
    reasonForm.post(`/admin/akun/${props.account.id}/aktifkan`, { preserveScroll: true });
}

function review(submission, decision) {
    const state = reviewField(submission.id);
    state.form.decision = decision;
    state.form.reason = state.reason;
    state.form.post(`/admin/bukti/${submission.id}/tinjau`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Tinjau ${account.full_name}`" />

    <h1 class="text-h3 font-semibold">{{ account.full_name }}</h1>
    <p class="mt-1 text-caption text-ink-soft">{{ account.email }} · {{ account.company_name }}</p>

    <dl class="mt-6 grid gap-3 sm:grid-cols-3">
        <div class="rounded-brand-sm border border-line p-3">
            <dt class="text-caption text-ink-soft">Status</dt>
            <dd class="text-label">{{ account.status }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-3">
            <dt class="text-caption text-ink-soft">Peran</dt>
            <dd class="text-label">{{ account.role }}</dd>
        </div>
        <div class="rounded-brand-sm border border-line p-3">
            <dt class="text-caption text-ink-soft">Email terverifikasi</dt>
            <dd class="text-label">{{ account.email_verified ? 'Ya' : 'Belum' }}</dd>
        </div>
    </dl>

    <section class="mt-10">
        <h2 class="text-h4 font-semibold">Bukti B2G</h2>
        <p v-if="evidence.length === 0" class="mt-4 text-caption text-ink-soft">Belum ada bukti.</p>
        <ul v-else class="mt-4 flex flex-col gap-3">
            <li v-for="submission in evidence" :key="submission.id"
                class="rounded-brand-sm border border-line p-4">
                <p class="text-label font-medium">Versi {{ submission.version }} · {{ submission.evidence_type }}</p>
                <p class="text-caption text-ink-soft">
                    {{ submission.original_filename }} · {{ submission.status }}
                    <span v-if="submission.review_reason"> · {{ submission.review_reason }}</span>
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <a :href="`/admin/bukti/${submission.id}/unduh`"
                        class="text-caption font-medium text-brand hover:underline">Unduh</a>
                    <input v-model="reviewField(submission.id).reason" type="text"
                        placeholder="Alasan tinjauan"
                        class="min-h-9 rounded-brand-sm border border-line bg-surface px-2 text-caption" />
                    <button type="button"
                        class="rounded-brand-sm bg-brand px-3 py-1.5 text-caption font-medium text-inverse hover:bg-brand-secondary"
                        @click="review(submission, 'ACCEPTED')">Terima</button>
                    <button type="button"
                        class="rounded-brand-sm border border-line px-3 py-1.5 text-caption font-medium hover:border-brand-secondary"
                        @click="review(submission, 'REJECTED')">Tolak</button>
                </div>
            </li>
        </ul>
    </section>

    <section class="mt-10 grid gap-8 sm:grid-cols-2">
        <form v-if="account.status === 'PENDING'" class="flex flex-col gap-4" @submit.prevent="approve">
            <h2 class="text-h4 font-semibold">Setujui</h2>
            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Peran yang diberikan</span>
                <select v-model="approveForm.role"
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label">
                    <option value="BUYER_B2B">BUYER_B2B</option>
                    <option value="BUYER_B2G">BUYER_B2G</option>
                </select>
                <span v-if="approveForm.errors.role" class="text-caption text-red-600">{{ approveForm.errors.role }}</span>
            </label>
            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Alasan</span>
                <input v-model="approveForm.reason" type="text" required
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label" />
                <span v-if="approveForm.errors.reason" class="text-caption text-red-600">{{ approveForm.errors.reason }}</span>
            </label>
            <button type="submit" :disabled="approveForm.processing"
                class="inline-flex min-h-11 items-center justify-center rounded-brand-sm bg-brand px-5 py-2.5 text-label font-medium text-inverse hover:bg-brand-secondary disabled:opacity-60">
                Setujui akun
            </button>
        </form>

        <div class="flex flex-col gap-4">
            <h2 class="text-h4 font-semibold">Tindakan lain</h2>
            <label class="flex flex-col gap-1.5">
                <span class="text-caption font-medium">Alasan</span>
                <input v-model="reasonForm.reason" type="text"
                    class="min-h-11 rounded-brand-sm border border-line bg-surface px-3 text-label" />
                <span v-if="reasonForm.errors.reason" class="text-caption text-red-600">{{ reasonForm.errors.reason }}</span>
            </label>
            <div class="flex flex-wrap gap-3">
                <button v-if="account.status === 'PENDING'" type="button" @click="reject"
                    class="rounded-brand-sm border border-line px-4 py-2 text-label font-medium hover:border-brand-secondary">
                    Tolak
                </button>
                <button v-if="account.status === 'ACTIVE'" type="button" @click="suspend"
                    class="rounded-brand-sm border border-line px-4 py-2 text-label font-medium hover:border-brand-secondary">
                    Tangguhkan
                </button>
                <button v-if="account.status === 'SUSPENDED'" type="button" @click="reactivate"
                    class="rounded-brand-sm bg-brand px-4 py-2 text-label font-medium text-inverse hover:bg-brand-secondary">
                    Aktifkan kembali
                </button>
            </div>
        </div>
    </section>
</template>