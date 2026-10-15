<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import StatusFilter from '../../../Components/Admin/StatusFilter.vue';
import { formatDate } from '../../../utils/format';

const props = defineProps({
    logs: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    entityTypes: { type: Array, default: () => [] },
    actions: { type: Array, default: () => [] },
});

defineOptions({ layout: AccountLayout });

const entityOptions = computed(() =>
    props.entityTypes.map((value) => ({ value, label: value })),
);
const actionOptions = computed(() => props.actions.map((value) => ({ value, label: value })));

function changedFields(previous, next) {
    const keys = new Set([
        ...Object.keys(previous?.fields ?? {}),
        ...Object.keys(next?.fields ?? {}),
    ]);

    return [...keys]
        .sort()
        .filter((key) => (previous?.fields?.[key] ?? null) !== (next?.fields?.[key] ?? null))
        .map((key) => ({
            key,
            before: previous?.fields?.[key] ?? null,
            after: next?.fields?.[key] ?? null,
        }));
}
</script>

<template>
    <Head title="Log Audit" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-h3 font-semibold">Log Audit</h1>
        <div class="flex flex-wrap items-center gap-4">
            <StatusFilter
                :model-value="filters.entity_type"
                :options="entityOptions"
                base-url="/admin/audit"
                param="entity_type"
                label="Entitas"
                :params="{ action: filters.action }"
            />
            <StatusFilter
                :model-value="filters.action"
                :options="actionOptions"
                base-url="/admin/audit"
                param="action"
                label="Aksi"
                :params="{ entity_type: filters.entity_type }"
            />
        </div>
    </div>

    <p class="mt-2 text-caption text-ink-soft">
        Hanya perubahan yang relevan yang ditampilkan. Nilai di luar daftar bidang yang diizinkan
        disamarkan.
    </p>

    <div v-if="logs.length === 0" class="mt-8">
        <EmptyState
            title="Tidak ada catatan audit"
            message="Peristiwa penting akan tercatat dan tampil di bagian ini."
        />
    </div>

    <template v-else>
        <ul class="mt-8 flex flex-col gap-3">
            <li v-for="log in logs" :key="log.id" class="rounded-brand-sm border border-line p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-label font-medium text-ink">{{ log.action }}</span>
                    <span class="text-caption text-ink-soft">{{ formatDate(log.created_at) }}</span>
                </div>
                <p class="mt-1 text-caption text-ink-soft">
                    {{ log.actor_name ?? 'Sistem' }} · {{ log.entity_type ?? '—' }}
                    <span v-if="log.entity_id"> · {{ log.entity_id }}</span>
                </p>

                <template v-if="changedFields(log.previous_state, log.new_state).length">
                    <ul class="mt-3 divide-y divide-line rounded-brand-sm border border-line">
                        <li
                            v-for="change in changedFields(log.previous_state, log.new_state)"
                            :key="change.key"
                            class="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                        >
                            <span class="text-caption font-medium text-ink-soft">{{ change.key }}</span>
                            <span class="text-caption text-ink-soft">
                                {{ change.before ?? '—' }} → {{ change.after ?? '—' }}
                            </span>
                        </li>
                    </ul>
                </template>
                <p v-else class="mt-2 text-caption text-ink-soft">Tidak ada perubahan bidang yang ditampilkan.</p>

                <p
                    v-if="(log.previous_state?.redacted ?? 0) + (log.new_state?.redacted ?? 0) > 0"
                    class="mt-2 text-caption text-ink-soft"
                >
                    {{ (log.previous_state?.redacted ?? 0) + (log.new_state?.redacted ?? 0) }} bidang
                    lain disamarkan.
                </p>
            </li>
        </ul>

        <Pagination :pagination="pagination" :base-url="pagination.baseUrl" />
    </template>
</template>
