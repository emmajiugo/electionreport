<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppHeader from '@/components/AppHeader.vue';
import NotOfficialNotice from '@/components/NotOfficialNotice.vue';
import { results } from '@/routes';
import { create as addResultSheet } from '@/routes/result-sheets';

defineProps<{
    canAddResultSheet: boolean;
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
                We've recorded your report. Share ElectionReport with other
                voters so more polling units get monitored.
            </p>
            <NotOfficialNotice class="w-full max-w-md text-left" />
            <div
                v-if="canAddResultSheet"
                class="flex w-full max-w-md flex-col gap-2 rounded-xl bg-white p-4 dark:bg-gray-900"
            >
                <p class="font-medium">Is the result sheet posted?</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Add a photo of it now, or come back later and enter your VIN
                    again.
                </p>
                <Link
                    :href="addResultSheet()"
                    class="rounded-xl bg-green-700 px-6 py-3 font-semibold text-white"
                >
                    Add the result sheet
                </Link>
            </div>
            <Link
                :href="results()"
                :class="
                    canAddResultSheet
                        ? 'font-medium text-green-700 dark:text-green-400'
                        : 'rounded-xl bg-green-700 px-6 py-3 font-semibold text-white'
                "
            >
                See the results
            </Link>
        </main>
    </div>
</template>
