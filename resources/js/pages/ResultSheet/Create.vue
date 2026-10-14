<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppHeader from '@/components/AppHeader.vue';
import ResultSheetFiguresForm from '@/components/ResultSheetFiguresForm.vue';
import ResultSheetPicker from '@/components/ResultSheetPicker.vue';
import { home, results } from '@/routes';
import { store } from '@/routes/result-sheets';
import { store as storeReview } from '@/routes/result-sheets/reviews';
import type {
    BallotParty,
    Election,
    FigureField,
    PollingUnitDetails,
    ResultSheet,
    ResultSheetReview,
    ReviewVerdict,
} from '@/types';

defineProps<{
    election: Election;
    maskedVin: string;
    pollingUnit: PollingUnitDetails;
    sheets: ResultSheet[];
    hasUploaded: boolean;
    yourReview: ResultSheetReview | null;
    canAddFigures: boolean;
    figureFields: FigureField[];
    parties: BallotParty[];
}>();

const form = useForm({
    result_sheet: null as File | null,
});

const preparing = ref(false);

const reviewForm = useForm({
    verdict: null as ReviewVerdict | null,
});

function review(sheet: ResultSheet, verdict: ReviewVerdict): void {
    reviewForm.verdict = verdict;
    reviewForm.post(storeReview.url(sheet.id), { preserveScroll: true });
}

const statusClass: Record<ResultSheet['status'], string> = {
    single_source:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    partly_confirmed:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    corroborated:
        'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200',
    contested:
        'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
};

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

            <section v-if="sheets.length" class="flex flex-col gap-3">
                <h2 class="font-semibold">
                    {{
                        yourReview || sheets.every((sheet) => sheet.is_yours)
                            ? `Uploaded for this polling unit (${sheets.length})`
                            : 'Does this match the sheet posted at your polling unit?'
                    }}
                </h2>

                <p
                    v-if="reviewForm.errors.verdict"
                    class="rounded-xl bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ reviewForm.errors.verdict }}
                </p>

                <article
                    v-for="sheet in sheets"
                    :key="sheet.id"
                    class="overflow-hidden rounded-xl bg-white dark:bg-gray-900"
                >
                    <a :href="sheet.url" target="_blank" rel="noopener">
                        <img
                            :src="sheet.url"
                            alt="Result sheet uploaded by a voter"
                            loading="lazy"
                            class="aspect-[3/4] w-full object-cover object-top"
                        />
                    </a>
                    <div class="flex flex-col gap-3 p-4">
                        <p
                            class="flex justify-between text-sm text-gray-600 dark:text-gray-400"
                        >
                            <span>{{
                                sheet.is_yours
                                    ? 'Your upload'
                                    : 'Uploaded by a voter'
                            }}</span>
                            <span>{{ uploadedAt(sheet) }}</span>
                        </p>
                        <p
                            class="rounded-lg px-3 py-2 text-sm"
                            :class="statusClass[sheet.status]"
                        >
                            {{ sheet.status_label }} ·
                            {{ sheet.confirmations }} confirmed,
                            {{ sheet.disputes }} disputed
                        </p>

                        <details v-if="sheet.figures" class="text-sm">
                            <summary class="cursor-pointer font-medium">
                                Figures typed in by the uploader
                            </summary>
                            <dl class="mt-2 flex flex-col gap-1">
                                <div
                                    v-for="field in figureFields"
                                    :key="field.key"
                                    class="flex justify-between gap-3"
                                >
                                    <dt
                                        class="text-gray-600 dark:text-gray-400"
                                    >
                                        {{ field.label }}
                                    </dt>
                                    <dd class="font-mono">
                                        {{ sheet.figures[field.key] }}
                                    </dd>
                                </div>
                                <div
                                    v-for="party in parties"
                                    :key="party.id"
                                    class="flex justify-between gap-3"
                                >
                                    <dt class="font-semibold">
                                        {{ party.acronym }}
                                    </dt>
                                    <dd class="font-mono">
                                        {{
                                            sheet.figures.party_votes[party.id]
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </details>

                        <p
                            v-if="yourReview?.result_sheet_id === sheet.id"
                            class="text-sm font-medium"
                        >
                            {{
                                yourReview.verdict === 'confirm'
                                    ? 'You said this matches what you saw.'
                                    : 'You said this does not match what you saw.'
                            }}
                        </p>
                        <div
                            v-else-if="!yourReview && !sheet.is_yours"
                            class="grid grid-cols-2 gap-3"
                        >
                            <button
                                type="button"
                                class="rounded-xl bg-green-700 px-3 py-3 font-semibold text-white disabled:opacity-50"
                                :disabled="reviewForm.processing"
                                @click="review(sheet, 'confirm')"
                            >
                                It matches
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-gray-300 bg-white px-3 py-3 font-semibold disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900"
                                :disabled="reviewForm.processing"
                                @click="review(sheet, 'dispute')"
                            >
                                It does not match
                            </button>
                        </div>
                    </div>
                </article>
            </section>

            <p
                v-if="hasUploaded"
                class="rounded-xl bg-green-50 p-4 text-green-800 dark:bg-green-950 dark:text-green-200"
            >
                Thank you. Your photo of the result sheet is uploaded.
            </p>

            <ResultSheetFiguresForm
                v-if="canAddFigures"
                :fields="figureFields"
                :parties="parties"
            />

            <form v-else class="flex flex-col gap-4" @submit.prevent="upload">
                <h2 v-if="sheets.length" class="font-semibold">
                    {{
                        yourReview?.verdict === 'dispute'
                            ? 'Have a photo of the correct sheet? Upload it.'
                            : 'Upload your own photo'
                    }}
                </h2>
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

            <Link
                :href="results()"
                class="mt-auto text-center font-medium text-green-700 dark:text-green-400"
            >
                See the results
            </Link>
        </main>
    </div>
</template>
