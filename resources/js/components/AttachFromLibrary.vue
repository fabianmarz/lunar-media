<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button, http, useToasts } from '@lunarphp/panel';
import MediaPicker, { type PickerItem } from './MediaPicker.vue';

// Injected by the panel's PageZone: the page passes its model under the
// entity's own name, so any one of these three is present.
const props = defineProps<{
    owner: 'product' | 'brand' | 'collection';
    mediaBase: string;
    product?: { id: number };
    brand?: { id: number };
    collection?: { id: number };
}>();

const toasts = useToasts();
const open = ref(false);
const attaching = ref(false);

const ownerId = computed(() => (props[props.owner] as { id: number } | undefined)?.id ?? null);

const attach = async (items: PickerItem[]): Promise<void> => {
    if (ownerId.value === null || items.length === 0) {
        return;
    }

    attaching.value = true;

    try {
        for (const item of items) {
            await http.post(`${props.mediaBase}/${item.id}/attach`, { owner: props.owner, owner_id: ownerId.value });
        }

        toasts.success(items.length === 1 ? 'Image added from library.' : `${items.length} images added from library.`);
        router.reload();
    } catch {
        toasts.error('Could not add the selected images.');
    } finally {
        attaching.value = false;
    }
};
</script>

<template>
    <div class="px-4 sm:px-5 lg:px-7 max-w-[1400px] w-full mx-auto pb-4">
        <div class="flex items-center justify-between gap-3 rounded-md border border-line bg-surface px-4 py-3">
            <div class="text-[13px] text-ink-700">Reuse a file from the media library. A copy is added to this {{ owner }}.</div>
            <Button icon="image" :disabled="attaching || ownerId === null" @click="open = true">Choose from library</Button>
        </div>

        <MediaPicker
            v-model:open="open"
            multiple
            :picker-url="`${mediaBase}/picker`"
            :upload-url="mediaBase"
            @select="attach"
        />
    </div>
</template>
