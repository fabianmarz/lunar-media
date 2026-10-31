<script setup lang="ts">
import { ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Slideout, Button, TextInput, Textarea, FieldLabel, http, ValidationError, useToasts } from '@lunarphp/panel';

interface Detail {
    id: number;
    name: string;
    file_name: string;
    mime_type: string | null;
    size: string;
    collection_name: string;
    created_at: string | null;
    original_url: string;
    thumbnail: string | null;
    alt: string | null;
    caption: string | null;
    owner: { type_label: string; label: string; url: string | null };
}

const props = defineProps<{
    mediaId: number | null;
    urls: { show: string; update: string };
}>();

const emit = defineEmits<{ close: []; changed: [] }>();

const toasts = useToasts();
const detail = ref<Detail | null>(null);
const name = ref('');
const alt = ref('');
const caption = ref('');
const errors = ref<Record<string, string[]>>({});
const saving = ref(false);

const urlFor = (template: string, id: number): string => template.replace('__ID__', String(id));

const fill = (data: Detail): void => {
    detail.value = data;
    name.value = data.name;
    alt.value = data.alt ?? '';
    caption.value = data.caption ?? '';
};

watch(
    () => props.mediaId,
    async (id) => {
        detail.value = null;
        errors.value = {};

        if (id !== null) {
            fill(await http.get<Detail>(urlFor(props.urls.show, id)));
        }
    },
    { immediate: true },
);

const save = async (): Promise<void> => {
    if (!detail.value) {
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        fill(
            await http.put<Detail>(urlFor(props.urls.update, detail.value.id), {
                name: name.value,
                alt: alt.value || null,
                caption: caption.value || null,
            }),
        );
        toasts.success('Media updated.');
        emit('changed');
    } catch (error) {
        if (error instanceof ValidationError) {
            errors.value = error.errors;
        } else {
            throw error;
        }
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Slideout :open="mediaId !== null" title="Media details" size="md" @update:open="(open) => !open && emit('close')">
        <div v-if="detail" class="p-5 space-y-4">
            <img
                v-if="detail.thumbnail"
                :src="detail.original_url"
                :alt="detail.alt ?? detail.name"
                class="w-full max-h-64 object-contain rounded bg-surface-2"
            />

            <div class="text-[12.5px] text-ink-500">
                {{ detail.file_name }} · {{ detail.mime_type }} · {{ detail.size }}
            </div>

            <div class="text-[13px]">
                <span class="text-ink-500">Belongs to: </span>
                <Link v-if="detail.owner.url" :href="detail.owner.url" class="text-ink-900 hover:underline">
                    {{ detail.owner.label }}
                </Link>
                <span v-else class="text-ink-700">{{ detail.owner.label }}</span>
                <span class="text-ink-500"> ({{ detail.owner.type_label }})</span>
            </div>

            <div>
                <FieldLabel for="media-name" required>Name</FieldLabel>
                <TextInput id="media-name" v-model="name" :invalid="!!errors.name" />
                <p v-if="errors.name" class="text-[12px] text-danger mt-1">{{ errors.name[0] }}</p>
            </div>

            <div>
                <FieldLabel for="media-alt">Alt text</FieldLabel>
                <TextInput id="media-alt" v-model="alt" :invalid="!!errors.alt" />
                <p v-if="errors.alt" class="text-[12px] text-danger mt-1">{{ errors.alt[0] }}</p>
            </div>

            <div>
                <FieldLabel for="media-caption">Caption</FieldLabel>
                <Textarea v-model="caption" :rows="3" :invalid="!!errors.caption" />
                <p v-if="errors.caption" class="text-[12px] text-danger mt-1">{{ errors.caption[0] }}</p>
            </div>

            <Button variant="primary" :disabled="saving" @click="save">Save</Button>
        </div>
    </Slideout>
</template>
