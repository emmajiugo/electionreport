<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { create, play } from '@/routes/reports';

defineProps<{
    current: 'form' | 'story';
}>();

const modes = [
    { value: 'form', label: 'Quick form', href: create() },
    { value: 'story', label: 'Story mode', href: play() },
] as const;
</script>

<template>
    <div class="flex flex-col gap-2">
        <nav
            aria-label="How to report"
            class="grid grid-cols-2 gap-1 rounded-xl bg-gray-200 p-1 text-sm font-medium dark:bg-gray-800"
        >
            <template v-for="mode in modes" :key="mode.value">
                <span
                    v-if="mode.value === current"
                    aria-current="page"
                    class="rounded-lg bg-white px-3 py-2 text-center shadow-sm dark:bg-gray-950"
                >
                    {{ mode.label }}
                </span>
                <Link
                    v-else
                    :href="mode.href"
                    class="rounded-lg px-3 py-2 text-center text-gray-600 dark:text-gray-400"
                >
                    {{ mode.label }}
                </Link>
            </template>
        </nav>
        <p class="text-center text-sm text-gray-600 dark:text-gray-400">
            Two ways to send the same report. Story mode asks the same questions
            as a day at your polling unit.
        </p>
    </div>
</template>
