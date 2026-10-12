<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    electionName?: string;
    showBack?: boolean;
}>();

defineEmits<{
    back: [];
}>();

const page = usePage();
const isTestMode = computed(() => page.props.mode === 'test');
</script>

<template>
    <div>
        <header
            class="flex items-center gap-3 border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900"
        >
            <button
                v-if="showBack"
                type="button"
                class="-ml-2 rounded-lg p-2 text-gray-600 dark:text-gray-400"
                aria-label="Go back"
                @click="$emit('back')"
            >
                <svg
                    viewBox="0 0 24 24"
                    class="size-6"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </button>
            <span class="flex items-center gap-2 font-semibold">
                <img src="/favicon.svg" alt="" class="size-7" />
                <span
                    ><span class="text-gray-800 dark:text-gray-100"
                        >Election</span
                    ><span class="text-green-700 dark:text-green-400"
                        >Report</span
                    ></span
                >
            </span>
            <span
                v-if="isTestMode"
                class="rounded bg-amber-400 px-1.5 py-0.5 text-xs font-bold tracking-wide text-amber-950"
            >
                TEST
            </span>
            <span
                v-if="electionName"
                class="ml-auto truncate text-sm text-gray-500"
            >
                {{ electionName }}
            </span>
        </header>
        <div
            v-if="isTestMode"
            role="note"
            class="border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100"
        >
            This is a test version. Reports and results here are practice data
            and do not count in the election.
            <a
                v-if="page.props.feedbackUrl"
                :href="page.props.feedbackUrl"
                target="_blank"
                rel="noopener"
                class="font-semibold underline"
                >Send feedback</a
            >
        </div>
    </div>
</template>
