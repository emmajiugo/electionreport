<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import { shrinkPhoto } from '@/lib/photo';

defineProps<{
    error?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    chosen: [];
}>();

const photo = defineModel<File | null>('photo', { required: true });
const preparing = defineModel<boolean>('preparing', { default: false });

const practice = usePage().props.mode === 'test';

const previewUrl = ref<string | null>(null);
const photoError = ref<string | null>(null);

watch(
    photo,
    (file) => {
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
        }

        previewUrl.value = file ? URL.createObjectURL(file) : null;
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});

async function choosePhoto(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    preparing.value = true;
    photoError.value = null;
    emit('chosen');

    try {
        photo.value = await shrinkPhoto(file);
    } catch {
        photo.value = null;
        photoError.value =
            'We could not open this photo. Try taking it again, or choose a JPG or PNG.';
    } finally {
        preparing.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <img
            v-if="previewUrl"
            :src="previewUrl"
            alt="Your photo of the result sheet"
            class="w-full rounded-xl border border-gray-200 dark:border-gray-800"
        />

        <label
            class="cursor-pointer rounded-xl border-2 border-dashed border-gray-300 bg-white p-4 text-center font-medium text-green-700 dark:border-gray-700 dark:bg-gray-900 dark:text-green-400"
        >
            {{
                preparing
                    ? 'Preparing photo…'
                    : previewUrl
                      ? 'Choose a different photo'
                      : 'Take or choose a photo'
            }}
            <input
                type="file"
                accept="image/*"
                class="sr-only"
                :disabled="preparing || disabled"
                @change="choosePhoto"
            />
        </label>

        <p
            v-if="practice"
            class="rounded-xl bg-yellow-50 p-3 text-sm text-yellow-900 dark:bg-yellow-950 dark:text-yellow-100"
        >
            Practice mode: your photo stays on your phone and is not uploaded. A
            sample result sheet is saved in its place.
        </p>

        <p
            v-if="photoError || error"
            class="rounded-xl bg-red-50 p-3 text-sm text-red-700"
        >
            {{ photoError ?? error }}
        </p>
    </div>
</template>
