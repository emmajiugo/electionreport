<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { results } from '@/routes';
</script>

<template>
    <Link
        :href="results()"
        class="flex h-12 w-full items-center justify-center gap-2 rounded-xl border-2 border-green-700 bg-white font-semibold text-green-700 dark:border-green-400 dark:bg-gray-900 dark:text-green-400"
    >
        <svg
            viewBox="0 0 24 24"
            class="size-5"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
        >
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
        </svg>
        See the results
    </Link>
</template>
