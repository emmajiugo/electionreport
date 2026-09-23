<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import type { ReasonOption } from '@/types';

defineProps<{
    name: string;
    reasons: ReasonOption[];
    error?: string;
    detailsError?: string;
}>();

const reason = defineModel<string | null>('reason', { required: true });
const details = defineModel<string>('details', { required: true });

const detailsInput = ref<HTMLTextAreaElement | null>(null);

// The box opens below the reason list, often under the sticky submit bar on phones, so bring it into view.
watch(reason, async (value) => {
    if (value !== 'other') {
        return;
    }

    await nextTick();
    detailsInput.value?.scrollIntoView({ block: 'center' });
    detailsInput.value?.focus({ preventScroll: true });
});
</script>

<template>
    <fieldset class="flex flex-col gap-3">
        <label
            v-for="option in reasons"
            :key="option.value"
            class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 text-base has-checked:border-green-700 has-checked:bg-green-50 dark:border-gray-800 dark:bg-gray-900 dark:has-checked:border-green-500 dark:has-checked:bg-green-950"
        >
            <input
                v-model="reason"
                type="radio"
                :name="name"
                :value="option.value"
                class="size-5 accent-green-700"
            />
            <span>{{ option.label }}</span>
        </label>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <div v-if="reason === 'other'" class="flex flex-col gap-2">
            <label :for="`${name}-details`" class="text-sm font-medium">
                What happened?
            </label>
            <textarea
                :id="`${name}-details`"
                ref="detailsInput"
                v-model="details"
                rows="4"
                maxlength="500"
                class="rounded-xl border border-gray-300 bg-white p-3 text-base dark:border-gray-700 dark:bg-gray-900"
            />
            <p v-if="detailsError" class="text-sm text-red-600">
                {{ detailsError }}
            </p>
        </div>
    </fieldset>
</template>
