<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { feedback, home } from '@/routes';

defineProps<{
    electionName?: string;
    showBack?: boolean;
}>();

defineEmits<{
    back: [];
}>();

const page = usePage();
const isTestMode = computed(() => page.props.mode === 'test');

// The root template sets the starting theme before paint; this only mirrors and changes it.
const isDark = ref(true);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
});

function toggleAppearance(): void {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);

    try {
        localStorage.setItem('appearance', isDark.value ? 'dark' : 'light');
    } catch {
        // Private browsing can block storage; the switch still works for this page.
    }
}
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
            <Link :href="home()" class="flex items-center gap-2 font-semibold">
                <img src="/favicon.svg" alt="" class="size-7" />
                <span
                    ><span class="text-gray-800 dark:text-gray-100"
                        >Election</span
                    ><span class="text-green-700 dark:text-green-400"
                        >Report</span
                    ></span
                >
            </Link>
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
            <button
                type="button"
                class="-mr-2 shrink-0 rounded-lg p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                :class="{ 'ml-auto': !electionName }"
                :aria-label="
                    isDark ? 'Switch to light mode' : 'Switch to dark mode'
                "
                :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                @click="toggleAppearance"
            >
                <svg
                    v-if="isDark"
                    viewBox="0 0 24 24"
                    class="size-5"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="12" r="4" />
                    <path
                        d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"
                    />
                </svg>
                <svg
                    v-else
                    viewBox="0 0 24 24"
                    class="size-5"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                </svg>
            </button>
        </header>
        <div
            v-if="isTestMode"
            role="note"
            class="border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100"
        >
            This is a test version. Reports and results here are practice data
            and do not count in the election. Live mode switches on for election
            day.
            <Link
                v-if="
                    page.props.feedbackUrl && page.component !== 'Feedback/Show'
                "
                :href="feedback()"
                class="font-semibold underline"
                >Send feedback</Link
            >
        </div>
    </div>
</template>
