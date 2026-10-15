<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppHeader from '@/components/AppHeader.vue';
import PartyPicker from '@/components/PartyPicker.vue';
import PollingUnitPicker from '@/components/PollingUnitPicker.vue';
import ReasonPicker from '@/components/ReasonPicker.vue';
import ResultSheetPicker from '@/components/ResultSheetPicker.vue';
import { useReportFlow } from '@/lib/report-flow';
import { home } from '@/routes';
import { play } from '@/routes/reports';
import type { Election, Option, Party, ReasonOption } from '@/types';

const props = defineProps<{
    election: Election | null;
    maskedVin: string;
    parties: Party[];
    states: Option[];
    notHeldReasons: ReasonOption[];
    notVotedReasons: ReasonOption[];
}>();

const {
    form,
    step,
    history,
    preparingPhoto,
    canContinue,
    isFinalStep,
    formError,
    back,
    answerHeld,
    answerVoted,
    next,
} = useReportFlow();

const title = computed(() => {
    switch (step.value) {
        case 'location':
            return 'Where is your polling unit?';
        case 'held':
            return 'Did the election hold at your polling unit?';
        case 'notHeldReason':
            return 'Why did the election not hold?';
        case 'voted':
            return 'Did you vote?';
        case 'notVotedReason':
            return 'Why were you unable to vote?';
        case 'party':
            return 'Which party did you vote for?';
        case 'resultSheet':
            return 'Is the result sheet posted?';
        default:
            return '';
    }
});
</script>

<template>
    <Head title="Report your polling unit" />
    <div
        class="flex min-h-dvh flex-col bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <AppHeader
            :election-name="election?.name"
            :show-back="history.length > 0 && !form.processing"
            @back="back"
        />

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
            @submit.prevent="next"
        >
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

            <h1 class="text-2xl font-semibold">{{ title }}</h1>

            <p
                v-if="formError"
                class="rounded-xl bg-red-50 p-3 text-sm text-red-700"
            >
                {{ formError }}
            </p>

            <!-- v-show keeps the chosen state, LGA and ward when the voter comes back to this step. -->
            <div v-show="step === 'location'" class="flex flex-col gap-4">
                <PollingUnitPicker
                    v-model:polling-unit-id="form.polling_unit_id"
                    :states="props.states"
                    :error="form.errors.polling_unit_id"
                />
                <Link
                    :href="play()"
                    class="text-center text-sm font-medium text-green-700 dark:text-green-400"
                >
                    Prefer a story? Report as a day at your polling unit
                </Link>
            </div>

            <div
                v-if="step === 'held' || step === 'voted'"
                class="grid grid-cols-2 gap-3"
            >
                <button
                    type="button"
                    class="rounded-xl bg-green-700 p-6 text-lg font-semibold text-white active:bg-green-800"
                    @click="
                        step === 'held' ? answerHeld(true) : answerVoted(true)
                    "
                >
                    Yes
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-gray-300 bg-white p-6 text-lg font-semibold active:bg-gray-100 dark:border-gray-700 dark:bg-gray-900"
                    @click="
                        step === 'held' ? answerHeld(false) : answerVoted(false)
                    "
                >
                    No
                </button>
            </div>

            <ReasonPicker
                v-else-if="step === 'notHeldReason'"
                v-model:reason="form.not_held_reason"
                v-model:details="form.reason_details"
                name="not_held_reason"
                :reasons="props.notHeldReasons"
                :error="form.errors.not_held_reason"
                :details-error="form.errors.reason_details"
            />

            <ReasonPicker
                v-else-if="step === 'notVotedReason'"
                v-model:reason="form.not_voted_reason"
                v-model:details="form.reason_details"
                name="not_voted_reason"
                :reasons="props.notVotedReasons"
                :error="form.errors.not_voted_reason"
                :details-error="form.errors.reason_details"
            />

            <PartyPicker
                v-else-if="step === 'party'"
                v-model:party-id="form.party_id"
                :parties="props.parties"
                :error="form.errors.party_id"
            />

            <div v-else-if="step === 'resultSheet'" class="flex flex-col gap-4">
                <p class="text-gray-600 dark:text-gray-400">
                    If the officials have posted the result sheet (Form EC8A),
                    add a clear photo of the whole sheet. This is optional: you
                    can submit now and come back later with your VIN. Only take
                    the photo when it is safe to do so.
                </p>
                <ResultSheetPicker
                    v-model:photo="form.result_sheet"
                    v-model:preparing="preparingPhoto"
                    :error="form.errors.result_sheet"
                    :disabled="form.processing"
                    @chosen="form.clearErrors('result_sheet')"
                />
            </div>

            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="website">Website</label>
                <input
                    id="website"
                    v-model="form.website"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                />
            </div>

            <div
                v-if="step !== 'held' && step !== 'voted'"
                class="sticky bottom-0 mt-auto bg-gray-50 pt-2 pb-4 dark:bg-gray-950"
            >
                <button
                    type="submit"
                    :disabled="!canContinue || form.processing"
                    class="w-full rounded-xl bg-green-700 p-4 text-lg font-semibold text-white disabled:opacity-40"
                >
                    {{
                        form.processing
                            ? 'Submitting…'
                            : step === 'resultSheet' && !form.result_sheet
                              ? 'Submit without a photo'
                              : isFinalStep
                                ? 'Submit report'
                                : 'Continue'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>
