<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppHeader from '@/components/AppHeader.vue';
import NotOfficialNotice from '@/components/NotOfficialNotice.vue';
import { results } from '@/routes';
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
                We've recorded your report. Share ElectionWatch with other
                voters so more polling units get monitored.
            </p>
            <NotOfficialNotice class="w-full max-w-md text-left" />
            <Link
                :href="results()"
                class="rounded-xl bg-green-700 px-6 py-3 font-semibold text-white"
            >
                See the results
            </Link>
        </main>
    </div>
</template>
