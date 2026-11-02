<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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

// The panel's Images field has no extension point, so the button is teleported
// into that section's action row, next to its own Upload button. The mount
// node is re-created if the section re-renders; without an image section the
// button renders in place instead.
const target = ref<HTMLElement | null>(null);
let observer: MutationObserver | null = null;

const findImageActions = (): HTMLElement | null => {
    const input = document.querySelector<HTMLInputElement>('input[type="file"][multiple][accept="image/*"]');
    const section = input?.closest('section');

    return section?.querySelector<HTMLElement>(':scope > div.flex > div.shrink-0') ?? null;
};

const mount = (): void => {
    if (target.value?.isConnected) {
        return;
    }

    const actions = findImageActions();

    if (!actions) {
        target.value = null;

        return;
    }

    const node = document.createElement('span');
    node.className = 'contents';
    actions.prepend(node);
    target.value = node;
};

onMounted(() => {
    mount();
    observer = new MutationObserver(mount);
    observer.observe(document.body, { childList: true, subtree: true });
});

onBeforeUnmount(() => {
    observer?.disconnect();
    target.value?.remove();
});

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
    <Teleport v-if="target" :to="target">
        <Button icon="image" size="sm" :disabled="attaching || ownerId === null" @click="open = true">Choose from library</Button>
    </Teleport>
    <div v-else class="px-4 sm:px-5 lg:px-7 max-w-[1400px] w-full mx-auto pb-4">
        <Button icon="image" :disabled="attaching || ownerId === null" @click="open = true">Choose from library</Button>
    </div>

    <MediaPicker
        v-model:open="open"
        multiple
        :picker-url="`${mediaBase}/picker`"
        :upload-url="mediaBase"
        @select="attach"
    />
</template>
