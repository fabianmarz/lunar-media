<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Dialog, Button, TextInput, FilterDropdown, Icon, http } from '@lunarphp/panel';

export interface PickerItem {
    id: number;
    name: string;
    file_name: string;
    mime_type: string | null;
    url: string | null;
    original_url: string;
    owner: { type_label: string; label: string; url: string | null };
}

interface PickerPage {
    data: PickerItem[];
    current_page: number;
    last_page: number;
    total: number;
    filterOptions: { owners: Record<string, string>; collections: string[] };
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        multiple?: boolean;
        pickerUrl: string;
        uploadUrl: string;
    }>(),
    { multiple: false },
);

const emit = defineEmits<{ 'update:open': [value: boolean]; select: [items: PickerItem[]] }>();

const items = ref<PickerItem[]>([]);
const page = ref(1);
const lastPage = ref(1);
const q = ref('');
const owner = ref('all');
const collection = ref('all');
const filterOptions = ref<PickerPage['filterOptions']>({ owners: {}, collections: [] });
const selected = ref<PickerItem[]>([]);
const loading = ref(false);
const uploading = ref(false);
const error = ref('');
const fileInput = ref<HTMLInputElement | null>(null);

const ownerOptions = computed(() => [
    { value: 'all', label: 'All owners' },
    ...Object.entries(filterOptions.value.owners).map(([value, label]) => ({ value, label })),
]);
const collectionOptions = computed(() => [
    { value: 'all', label: 'All collections' },
    ...filterOptions.value.collections.map((value) => ({ value, label: value })),
]);

const load = async (): Promise<void> => {
    loading.value = true;
    const params = new URLSearchParams({ page: String(page.value) });

    if (q.value) {
        params.set('q', q.value);
    }

    if (owner.value !== 'all') {
        params.set('owner', owner.value);
    }

    if (collection.value !== 'all') {
        params.set('collection', collection.value);
    }

    try {
        const result = await http.get<PickerPage>(`${props.pickerUrl}?${params.toString()}`);
        items.value = result.data;
        lastPage.value = result.last_page;
        filterOptions.value = result.filterOptions;
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.open,
    (open) => {
        if (open) {
            selected.value = [];
            page.value = 1;
            void load();
        }
    },
);

watch([owner, collection], () => {
    page.value = 1;
    void load();
});

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        void load();
    }, 300);
});

const isSelected = (item: PickerItem): boolean => selected.value.some((s) => s.id === item.id);

const toggle = (item: PickerItem): void => {
    if (isSelected(item)) {
        selected.value = selected.value.filter((s) => s.id !== item.id);
    } else {
        selected.value = props.multiple ? [...selected.value, item] : [item];
    }
};

const goTo = (target: number): void => {
    page.value = target;
    void load();
};

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

// Multipart can't go through the panel's JSON http helper, and Inertia would
// reject the JSON response, so this talks to fetch directly (same XSRF pattern).
const upload = async (event: Event): Promise<void> => {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);

    if (files.length === 0) {
        return;
    }

    uploading.value = true;
    error.value = '';

    const body = new FormData();
    files.forEach((file) => body.append('files[]', file));

    try {
        const response = await fetch(props.uploadUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });
        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            error.value = (Object.values(payload?.errors ?? {})[0] as string[] | undefined)?.[0] ?? `Upload failed (${response.status})`;

            return;
        }

        await load();
        selected.value = props.multiple ? [...selected.value, ...payload.data] : [payload.data[0]];
    } finally {
        uploading.value = false;
        input.value = '';
    }
};

const confirm = (): void => {
    emit('select', selected.value);
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" title="Choose from library" size="xl" @update:open="(value) => emit('update:open', value)">
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <TextInput v-model="q" placeholder="Search file name…" clearable class="w-64" />
                <FilterDropdown v-model="owner" :options="ownerOptions" label="Owner" default-value="all" />
                <FilterDropdown v-model="collection" :options="collectionOptions" label="Collection" default-value="all" />
                <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="upload" />
                <Button class="ml-auto" icon="upload" :disabled="uploading" @click="fileInput?.click()">
                    {{ uploading ? 'Uploading…' : 'Upload' }}
                </Button>
            </div>

            <p v-if="error" class="text-[12.5px] text-danger">{{ error }}</p>

            <p v-if="!loading && items.length === 0" class="text-[13px] text-ink-500 py-6 text-center">No media found.</p>

            <ul class="grid grid-cols-4 gap-2 max-h-[420px] overflow-y-auto">
                <li v-for="item in items" :key="item.id">
                    <button
                        type="button"
                        class="w-full text-left rounded-md border p-1.5 hover:bg-surface-2"
                        :class="isSelected(item) ? 'border-ink-900' : 'border-line'"
                        :aria-pressed="isSelected(item)"
                        @click="toggle(item)"
                    >
                        <img v-if="item.url" :src="item.url" :alt="item.name" class="w-full h-24 object-cover rounded" />
                        <span v-else class="w-full h-24 flex items-center justify-center rounded bg-surface-2 text-ink-500">
                            <Icon name="folder" />
                        </span>
                        <span class="block truncate text-[12px] text-ink-900 mt-1">{{ item.name }}</span>
                        <span class="block truncate text-[11px] text-ink-500">{{ item.owner.label }}</span>
                    </button>
                </li>
            </ul>

            <div v-if="lastPage > 1" class="flex items-center justify-end gap-2 text-[12px] text-ink-500">
                <Button size="sm" :disabled="page <= 1" @click="goTo(page - 1)">Previous</Button>
                <span>{{ page }} / {{ lastPage }}</span>
                <Button size="sm" :disabled="page >= lastPage" @click="goTo(page + 1)">Next</Button>
            </div>
        </div>

        <template #footer>
            <Button @click="emit('update:open', false)">Cancel</Button>
            <Button variant="primary" :disabled="selected.length === 0" @click="confirm">
                {{ multiple ? `Choose ${selected.length || ''}`.trim() : 'Choose' }}
            </Button>
        </template>
    </Dialog>
</template>
