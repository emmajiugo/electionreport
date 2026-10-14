<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppHeader from '@/components/AppHeader.vue';
import ResultSheetPicker from '@/components/ResultSheetPicker.vue';
import { home, results } from '@/routes';
import { store } from '@/routes/result-sheets';
import type { Election, PollingUnitDetails, ResultSheet } from '@/types';

defineProps<{
    election: Election;
    maskedVin: string;
    pollingUnit: PollingUnitDetails;
    sheets: ResultSheet[];
    hasUploaded: boolean;
}>();

const form = useForm({
    result_sheet: null as File | null,
});

const preparing = ref(false);

function upload(): void {
    form.post(store.url(), {
        onSuccess: () => form.reset(),
    });
}

const timeFormat = new Intl.DateTimeFormat('en-NG', {
    hour: 'numeric',
    minute: '2-digit',
});

function uploadedAt(sheet: ResultSheet): string {
    return timeFormat.format(new Date(sheet.uploaded_at));
}
</script>

<template>
    <Head title="Add the result sheet" />
    <div
        class="flex min-h-dvh flex-col bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <AppHeader :election-name="election.name" />

        <main class="mx-auto flex w-full max-w-md grow flex-col gap-6 p-4">
            <div
                class="flex items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 text-sm dark:bg-gray-900"
            >
                <span>
                    VIN
                    <span class="font-mono tracking-wider">{{
                        maskedVin
                    }}</span>
                </span>
                <Link
                    :href="home()"
                    class="font-medium text-green-700 dark:text-green-400"
                >
                    Change
                </Link>
            </div>

            <h1 class="text-2xl font-semibold">
                Result sheet for your polling unit
            </h1>

            <div class="rounded-xl bg-white p-4 dark:bg-gray-900">
                <p class="font-medium">{{ pollingUnit.name }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ pollingUnit.code }} · {{ pollingUnit.ward }},
                    {{ pollingUnit.lga }}, {{ pollingUnit.state }}
                </p>
            </div>

            <p
                v-if="hasUploaded"
                class="rounded-xl bg-green-50 p-4 text-green-800 dark:bg-green-950 dark:text-green-200"
            >
                Thank you. Your photo of the result sheet is uploaded.
            </p>

            <form v-else class="flex flex-col gap-4" @submit.prevent="upload">
                <p class="text-gray-600 dark:text-gray-400">
                    When counting ends, the officials post the result sheet
                    (Form EC8A) at the polling unit. Take a clear photo of the
                    whole sheet, with every figure readable. Only take it when
                    it is safe to do so.
                </p>

                <ResultSheetPicker
                    v-model:photo="form.result_sheet"
                    v-model:preparing="preparing"
                    :error="form.errors.result_sheet"
                    :disabled="form.processing"
                    @chosen="form.clearErrors()"
                />

                <progress
                    v-if="form.progress"
                    :value="form.progress.percentage"
                    max="100"
                    class="w-full"
                />

                <button
                    type="submit"
                    class="rounded-xl bg-green-700 px-6 py-3 font-semibold text-white disabled:opacity-50"
                    :disabled="
                        !form.result_sheet || preparing || form.processing
                    "
                >
                    {{ form.processing ? 'Uploading…' : 'Upload photo' }}
                </button>
            </form>

            <section v-if="sheets.length" class="flex flex-col gap-3">
                <h2 class="font-semibold">
                    Uploaded for this polling unit ({{ sheets.length }})
                </h2>
                <a
                    v-for="sheet in sheets"
                    :key="sheet.id"
                    :href="sheet.url"
                    target="_blank"
                    rel="noopener"
                    class="overflow-hidden rounded-xl bg-white dark:bg-gray-900"
                >
                    <img
                        :src="sheet.url"
                        alt="Result sheet uploaded by a voter"
                        loading="lazy"
                        class="aspect-[3/4] w-full object-cover object-top"
                    />
                    <p
                        class="flex justify-between px-4 py-2 text-sm text-gray-600 dark:text-gray-400"
                    >
                        <span>{{
                            sheet.is_yours
                                ? 'Your upload'
                                : 'Uploaded by a voter'
                        }}</span>
                        <span>{{ uploadedAt(sheet) }}</span>
                    </p>
                </a>
            </section>

            <Link
                :href="results()"
                class="mt-auto text-center font-medium text-green-700 dark:text-green-400"
            >
                See the results
            </Link>
        </main>
    </div>
</template>
