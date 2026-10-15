<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { drawShareCard, publicSite } from '@/lib/share-card';
import type { ShareCardDetails } from '@/lib/share-card';

const props = defineProps<{
    card: ShareCardDetails;
}>();

const page = usePage();

const file = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const canShareFiles = ref(false);
const failed = ref(false);

onMounted(async () => {
    try {
        const blob = await drawShareCard(props.card, {
            practice: page.props.mode === 'test',
        });
        file.value = new File([blob], 'i-reported.png', { type: 'image/png' });
        previewUrl.value = URL.createObjectURL(blob);
        canShareFiles.value =
            navigator.canShare?.({ files: [file.value] }) ?? false;
    } catch {
        failed.value = true;
    }
});

onBeforeUnmount(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});

async function share(): Promise<void> {
    if (!file.value) {
        return;
    }

    try {
        await navigator.share({
            files: [file.value],
            text: `I reported from my polling unit. Report yours at https://${publicSite}`,
        });
    } catch {
        // The voter closed the share sheet; nothing to do.
    }
}
</script>

<template>
    <div
        v-if="!failed"
        class="flex w-full max-w-md flex-col gap-3 rounded-xl bg-white p-4 dark:bg-gray-900"
    >
        <p class="font-medium">Share that you reported</p>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Your card shows your LGA and state only, never how you voted.
        </p>
        <img
            v-if="previewUrl"
            :src="previewUrl"
            alt="Card saying you reported from your polling unit"
            class="aspect-square w-full rounded-lg"
        />
        <div
            v-else
            class="aspect-square w-full animate-pulse rounded-lg bg-gray-100 dark:bg-gray-800"
        />
        <button
            v-if="canShareFiles"
            type="button"
            class="rounded-xl bg-green-700 px-6 py-3 font-semibold text-white"
            @click="share"
        >
            Share your card
        </button>
        <a
            v-else-if="previewUrl"
            :href="previewUrl"
            download="i-reported.png"
            class="rounded-xl bg-green-700 px-6 py-3 text-center font-semibold text-white"
        >
            Download your card
        </a>
    </div>
</template>
