<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppHeader from '@/components/AppHeader.vue';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import { store } from '@/routes/vin';
import type { Election } from '@/types';

defineProps<{
    election: Election | null;
    turnstileSiteKey: string | null;
}>();

const PART_LENGTH = 4;
const VIN_CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

const page = usePage();
const isTestMode = computed(() => page.props.mode === 'test');

const form = useForm({ vin_first: '', vin_last: '', turnstile_token: '' });
const turnstile = ref<InstanceType<typeof TurnstileWidget> | null>(null);

function normalize(part: string): string {
    return part.replace(/[^a-z0-9]/gi, '').toUpperCase();
}

const vinError = computed(() => form.errors.vin_first ?? form.errors.vin_last);

// Raised by the server when no election is open; it has no matching form field.
const electionError = computed(
    () => (form.errors as Partial<Record<string, string>>).election,
);

const canSubmit = computed(
    () =>
        normalize(form.vin_first).length === PART_LENGTH &&
        normalize(form.vin_last).length === PART_LENGTH &&
        form.turnstile_token !== '' &&
        !form.processing,
);

// Testers use a made-up VIN so nobody has to type a real one into a practice site.
function generateTestVin(): void {
    const characters = Array.from(
        crypto.getRandomValues(new Uint32Array(PART_LENGTH * 2)),
        (value) => VIN_CHARACTERS[value % VIN_CHARACTERS.length],
    ).join('');

    form.vin_first = characters.slice(0, PART_LENGTH);
    form.vin_last = characters.slice(PART_LENGTH);
    form.clearErrors('vin_first', 'vin_last');
}

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    form.transform((data) => ({
        ...data,
        vin_first: normalize(data.vin_first),
        vin_last: normalize(data.vin_last),
    })).post(store.url(), { onError: () => turnstile.value?.reset() });
}
</script>

<template>
    <Head title="Enter your VIN" />
    <div
        class="flex min-h-dvh flex-col bg-gray-200 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
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
                    Tell us what happened at your polling unit. We only ask for
                    the first {{ PART_LENGTH }} and last {{ PART_LENGTH }}
                    characters of your VIN, never the full number. We use them
                    only to stop the same voter reporting a polling unit twice.
                </p>
            </div>

            <h1 class="text-2xl font-semibold">Enter your VIN</h1>

            <p
                v-if="electionError"
                class="rounded-xl bg-red-50 p-3 text-sm text-red-700"
            >
                {{ electionError }}
            </p>

            <fieldset class="flex flex-col gap-2">
                <legend class="mb-2 text-sm font-medium">
                    Voter Identification Number (VIN), printed on your PVC
                </legend>
                <div class="flex items-end gap-3">
                    <div class="flex flex-1 flex-col gap-1">
                        <label
                            for="vin_first"
                            class="text-sm text-gray-600 dark:text-gray-400"
                        >
                            First {{ PART_LENGTH }}
                        </label>
                        <input
                            id="vin_first"
                            v-model="form.vin_first"
                            v-focus
                            type="text"
                            :maxlength="PART_LENGTH"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            placeholder="90F5"
                            class="w-full rounded-xl border border-gray-300 bg-white p-3 text-center font-mono text-lg tracking-widest uppercase dark:border-gray-700 dark:bg-gray-900"
                        />
                    </div>
                    <span
                        class="pb-3 font-mono text-lg text-gray-400"
                        aria-hidden="true"
                        >…</span
                    >
                    <div class="flex flex-1 flex-col gap-1">
                        <label
                            for="vin_last"
                            class="text-sm text-gray-600 dark:text-gray-400"
                        >
                            Last {{ PART_LENGTH }}
                        </label>
                        <input
                            id="vin_last"
                            v-model="form.vin_last"
                            type="text"
                            :maxlength="PART_LENGTH"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            placeholder="3456"
                            class="w-full rounded-xl border border-gray-300 bg-white p-3 text-center font-mono text-lg tracking-widest uppercase dark:border-gray-700 dark:bg-gray-900"
                        />
                    </div>
                </div>
                <button
                    v-if="isTestMode"
                    type="button"
                    class="self-start rounded-lg border border-amber-500 px-3 py-2 text-sm font-medium text-amber-800 dark:text-amber-300"
                    @click="generateTestVin"
                >
                    Generate a test VIN
                </button>
                <p v-if="vinError" class="text-sm text-red-600">
                    {{ vinError }}
                </p>
            </fieldset>

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
            </div>
        </form>
    </div>
</template>
