<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppHeader from '@/components/AppHeader.vue';
import NotOfficialNotice from '@/components/NotOfficialNotice.vue';
import SeeResultsButton from '@/components/SeeResultsButton.vue';
import ShareCard from '@/components/ShareCard.vue';
import type { ShareCardDetails } from '@/lib/share-card';
import { create as addResultSheet } from '@/routes/result-sheets';

defineProps<{
    canAddResultSheet: boolean;
    shareCard: ShareCardDetails | null;
}>();
</script>

<template>
    <Head title="Report received" />
    <div
        class="flex min-h-dvh flex-col bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <AppHeader />
        <main
            class="flex grow flex-col items-center justify-center gap-4 p-6 text-center"
        >
            <SeeResultsButton class="max-w-md" />
            <div
                class="flex size-20 items-center justify-center rounded-full bg-green-700 text-white"
            >
                <svg
                    viewBox="0 0 24 24"
                    class="size-10"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                >
                    <path d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1 class="text-2xl font-semibold">Thank you for reporting</h1>
            <p class="max-w-sm text-gray-600 dark:text-gray-400">
                We've recorded your report. It can take up to a minute to show
                in the results. Share ElectionReport with other voters so more
                polling units get monitored.
            </p>
            <ShareCard v-if="shareCard" :card="shareCard" class="text-left" />
            <NotOfficialNotice class="w-full max-w-md text-left" />
            <div
                v-if="canAddResultSheet"
                class="flex w-full max-w-md flex-col gap-2 rounded-xl bg-white p-4 dark:bg-gray-900"
            >
                <p class="font-medium">Has counting finished?</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Once the result sheet (Form EC8A) is filled in and pasted up
                    at your polling unit, take a photo and add it. Not finished
                    yet? Come back later and enter your VIN again.
                </p>
                <Link
                    :href="addResultSheet()"
                    class="rounded-xl bg-green-700 px-6 py-3 font-semibold text-white"
                >
                    Add the result sheet
                </Link>
            </div>
        </main>
    </div>
</template>
