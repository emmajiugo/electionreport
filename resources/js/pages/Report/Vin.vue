<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppHeader from '@/components/AppHeader.vue';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import { results } from '@/routes';
import { store } from '@/routes/vin';
import type { Election } from '@/types';

defineProps<{
    election: Election | null;
    turnstileSiteKey: string | null;
}>();

const VIN_LENGTH = 19;

const form = useForm({ vin: '', turnstile_token: '' });
const turnstile = ref<InstanceType<typeof TurnstileWidget> | null>(null);

const normalizedVin = computed(() =>
    form.vin.replace(/[^a-z0-9]/gi, '').toUpperCase(),
);

// Raised by the server when no election is open; it has no matching form field.
const electionError = computed(
    () => (form.errors as Partial<Record<string, string>>).election,
);

const canSubmit = computed(
    () =>
        normalizedVin.value.length === VIN_LENGTH &&
        form.turnstile_token !== '' &&
        !form.processing,
);

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    form.transform((data) => ({ ...data, vin: normalizedVin.value })).post(
        store.url(),
        { onError: () => turnstile.value?.reset() },
    );
}
</script>

<template>
    <Head title="Report your polling unit" />
    <div
        class="flex min-h-dvh flex-col bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <AppHeader :election-name="election?.name" />

        <main
            v-if="!election"
            class="mx-auto flex w-full max-w-md grow flex-col items-center justify-center gap-2 p-6 text-center"
        >
            <h1 class="text-xl font-semibold">No election is open</h1>
            <p class="text-gray-600 dark:text-gray-400">
                Reporting opens on election day. Check back then.
            </p>
        </main>

        <form
            v-else
            class="mx-auto flex w-full max-w-md grow flex-col gap-6 p-4"
            @submit.prevent="submit"
        >
            <div class="flex flex-col items-center gap-3 pt-6 text-center">
                <div
                    class="flex size-24 items-center justify-center rounded-full bg-green-700 text-white"
                >
                    <svg
                        viewBox="0 0 24 24"
                        class="size-12"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.75"
                    >
                        <path
                            d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"
                        />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                </div>
                <p class="text-gray-600 dark:text-gray-400">
                    Tell us what happened at your polling unit. We store your
                    VIN only as a one-way hash, and we use it to make sure each
                    voter reports once.
                </p>
            </div>

            <h1 class="text-2xl font-semibold">Enter your VIN</h1>

            <p
                v-if="electionError"
                class="rounded-xl bg-red-50 p-3 text-sm text-red-700"
            >
                {{ electionError }}
            </p>

            <div class="flex flex-col gap-2">
                <label for="vin" class="text-sm font-medium">
                    Voter Identification Number (VIN)
                </label>
                <input
                    id="vin"
                    v-model="form.vin"
                    v-focus
                    type="text"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    placeholder="e.g. 90F5 B1C3 E295 0123 456"
                    class="rounded-xl border border-gray-300 bg-white p-3 font-mono text-lg tracking-wider uppercase placeholder:normal-case dark:border-gray-700 dark:bg-gray-900"
                />
                <p class="text-sm text-gray-500">
                    {{ VIN_LENGTH }} characters, printed on your PVC.
                    {{ normalizedVin.length }}/{{ VIN_LENGTH }}
                </p>
                <p v-if="form.errors.vin" class="text-sm text-red-600">
                    {{ form.errors.vin }}
                </p>
            </div>

            <div v-if="turnstileSiteKey" class="flex flex-col gap-2">
                <TurnstileWidget
                    ref="turnstile"
                    v-model:token="form.turnstile_token"
                    :site-key="turnstileSiteKey"
                />
                <p
                    v-if="form.errors.turnstile_token"
                    class="text-sm text-red-600"
                >
                    {{ form.errors.turnstile_token }}
                </p>
            </div>

            <div class="mt-auto pb-4">
                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="w-full rounded-xl bg-green-700 p-4 text-lg font-semibold text-white disabled:opacity-40"
                >
                    {{ form.processing ? 'Checking…' : 'Continue' }}
                </button>
                <Link
                    :href="results()"
                    class="mt-3 block text-center text-sm font-medium text-green-700 dark:text-green-400"
                >
                    See the results
                </Link>
            </div>
        </form>
    </div>
</template>
