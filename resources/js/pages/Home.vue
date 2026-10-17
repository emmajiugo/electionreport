<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppHeader from '@/components/AppHeader.vue';
import NotOfficialNotice from '@/components/NotOfficialNotice.vue';
import { results } from '@/routes';
import { create as enterVin } from '@/routes/vin';
import type { Election } from '@/types';

defineProps<{
    election: Election | null;
}>();
</script>

<template>
    <Head title="Report your polling unit" />
    <div
        class="flex min-h-dvh flex-col overflow-x-hidden bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <AppHeader :election-name="election?.name" />

        <main
            class="relative isolate mx-auto flex w-full max-w-md grow flex-col gap-6 p-4"
        >
            <img
                src="/favicon.svg"
                alt=""
                aria-hidden="true"
                class="pointer-events-none absolute top-1/2 left-1/2 -z-10 w-[130%] max-w-none -translate-x-1/2 -translate-y-1/2 opacity-10 dark:opacity-15"
            />
            <div class="flex flex-col items-center gap-3 pt-16 text-center">
                <h1 class="text-3xl font-semibold">
                    What happened at your polling unit?
                </h1>
                <p class="text-gray-700 dark:text-gray-300">
                    See what voters across Nigeria are reporting, then add what
                    you saw at yours.
                </p>
            </div>

            <div class="my-auto flex flex-col gap-3">
                <Link
                    :href="results()"
                    class="flex h-14 items-center justify-center rounded-xl border-2 border-green-700 bg-white text-lg font-semibold text-green-700 dark:border-green-400 dark:bg-gray-900 dark:text-green-400"
                >
                    See the results
                </Link>
                <Link
                    v-if="election"
                    :href="enterVin()"
                    class="flex h-14 items-center justify-center rounded-xl border-2 border-green-700 bg-green-700 text-lg font-semibold text-white"
                >
                    Report your polling unit
                </Link>
                <p
                    v-else
                    class="rounded-xl bg-white p-4 text-center text-gray-600 dark:bg-gray-900 dark:text-gray-400"
                >
                    Reporting opens on election day. Check back then.
                </p>
            </div>

            <NotOfficialNotice />
        </main>
    </div>
</template>
