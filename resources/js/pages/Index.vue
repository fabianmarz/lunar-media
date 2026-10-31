<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { PageHeader, PageZone, DataTable, Pagination, Icon, TextInput, FilterDropdown } from '@lunarphp/panel';

interface MediaRow {
    id: number;
    name: string;
    file_name: string;
    mime_type: string | null;
    size: string;
    collection_name: string;
    created_at: string | null;
    thumbnail: string | null;
    owner: { type_label: string; label: string; url: string | null };
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    media: Paginated<MediaRow>;
    columns: { key: string; label: string; width?: string }[];
    filters: { q?: string; owner?: string; collection?: string };
    filterOptions: { owners: Record<string, string>; collections: string[] };
    urls: { index: string };
}>();

const ownerOptions = computed(() => [
    { value: 'all', label: 'All owners' },
    ...Object.entries(props.filterOptions.owners).map(([value, label]) => ({ value, label })),
]);

const collectionOptions = computed(() => [
    { value: 'all', label: 'All collections' },
    ...props.filterOptions.collections.map((value) => ({ value, label: value })),
]);

const q = ref(props.filters.q ?? '');
const ownerFilter = ref(props.filters.owner ?? 'all');
const collectionFilter = ref(props.filters.collection ?? 'all');

const reload = (): void => {
    router.get(
        props.urls.index,
        {
            q: q.value || undefined,
            owner: ownerFilter.value === 'all' ? undefined : ownerFilter.value,
            collection: collectionFilter.value === 'all' ? undefined : collectionFilter.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch([ownerFilter, collectionFilter], reload);

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(reload, 300);
});

const formatDate = (value: string | null): string => (value ? value.slice(0, 16).replace('T', ' ') : '—');
</script>

<template>
    <div data-screen-label="Media" class="contents">
        <PageHeader title="Media" description="Every uploaded file and the entity it belongs to." icon="image" />

        <div class="px-4 sm:px-5 lg:px-7 max-w-[1400px] w-full mx-auto pt-5 pb-7">
            <PageZone region="main" position="before" />

            <div class="flex items-center gap-2 mb-3">
                <TextInput v-model="q" placeholder="Search file name…" clearable class="w-64" />
                <FilterDropdown v-model="ownerFilter" :options="ownerOptions" label="Owner" default-value="all" />
                <FilterDropdown v-model="collectionFilter" :options="collectionOptions" label="Collection" default-value="all" />
            </div>

            <DataTable :columns="columns" :rows="media.data" empty-text="No media found">
                <template #cell-file="{ row }">
                    <div class="flex items-center gap-3 min-w-0">
                        <img
                            v-if="(row as MediaRow).thumbnail"
                            :src="(row as MediaRow).thumbnail!"
                            :alt="(row as MediaRow).name"
                            class="w-10 h-10 object-cover rounded"
                        />
                        <span v-else class="w-10 h-10 flex items-center justify-center rounded bg-surface-2 text-ink-500">
                            <Icon name="folder" />
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-[13px] text-ink-900">{{ (row as MediaRow).name }}</div>
                            <div class="truncate text-[12px] text-ink-500">{{ (row as MediaRow).file_name }}</div>
                        </div>
                    </div>
                </template>
                <template #cell-owner="{ row }">
                    <div class="min-w-0">
                        <Link
                            v-if="(row as MediaRow).owner.url"
                            :href="(row as MediaRow).owner.url!"
                            class="truncate text-[13px] text-ink-900 hover:underline block"
                        >
                            {{ (row as MediaRow).owner.label }}
                        </Link>
                        <div v-else class="truncate text-[13px] text-ink-700">{{ (row as MediaRow).owner.label }}</div>
                        <div class="text-[12px] text-ink-500">{{ (row as MediaRow).owner.type_label }}</div>
                    </div>
                </template>
                <template #cell-created_at="{ value }">
                    <span class="text-ink-500">{{ formatDate(value as string | null) }}</span>
                </template>
            </DataTable>

            <Pagination :meta="media" class="mt-4" />

            <PageZone region="main" position="after" />
        </div>
    </div>
</template>
