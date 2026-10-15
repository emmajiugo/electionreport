<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppHeader from '@/components/AppHeader.vue';
import PartyPicker from '@/components/PartyPicker.vue';
import PollingUnitPicker from '@/components/PollingUnitPicker.vue';
import ReasonPicker from '@/components/ReasonPicker.vue';
import ResultSheetPicker from '@/components/ResultSheetPicker.vue';
import { scenes } from '@/game/script';
import { useReportFlow } from '@/lib/report-flow';
import { create as reportForm } from '@/routes/reports';
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

const scene = computed(() => scenes[step.value]);

const sunrise = 6 * 60;
const sunset = 19 * 60;

// The sun climbs from the left in the morning and sets on the right in the evening.
const sunStyle = computed(() => {
    const progress = (scene.value.minutes - sunrise) / (sunset - sunrise);
    const height = Math.sin(progress * Math.PI);

    return {
        left: `${8 + progress * 80}%`,
        bottom: `${10 + height * 60}%`,
    };
});

const skyClass = computed(() => {
    const minutes = scene.value.minutes;

    if (minutes < 7 * 60) {
        return 'from-orange-200 via-rose-100 to-sky-200';
    }

    if (minutes < 15 * 60) {
        return 'from-sky-400 via-sky-200 to-sky-100';
    }

    return 'from-indigo-400 via-orange-300 to-amber-200';
});
</script>

<template>
    <Head title="Your day at the polling unit" />
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
            class="mx-auto flex w-full max-w-md grow flex-col gap-5 p-4"
            @submit.prevent="next"
        >
            <section
                class="relative h-36 overflow-hidden rounded-2xl bg-linear-to-b transition-colors duration-700"
                :class="skyClass"
                aria-hidden="true"
            >
                <span
                    class="absolute size-10 -translate-x-1/2 rounded-full bg-yellow-300 shadow-[0_0_40px_10px] shadow-yellow-200/70 transition-all duration-700 ease-out"
                    :style="sunStyle"
                />
                <span class="absolute inset-x-0 bottom-0 h-8 bg-amber-800/80" />
                <span
                    class="absolute right-6 bottom-8 h-10 w-24 rounded-t-md bg-white/90"
                />
                <span
                    class="absolute right-4 bottom-18 h-3 w-28 bg-green-700/90"
                />
                <span
                    class="absolute top-3 left-3 rounded-full bg-black/40 px-3 py-1 text-sm font-semibold text-white tabular-nums"
                >
                    {{ scene.time }}
                </span>
            </section>

            <p class="text-gray-700 dark:text-gray-300">
                {{ scene.narration }}
            </p>

            <ul v-if="scene.bubbles.length" class="flex flex-col gap-2">
                <li
                    v-for="bubble in scene.bubbles"
                    :key="bubble.line"
                    class="w-fit max-w-[85%] rounded-2xl rounded-bl-sm bg-white px-4 py-2 text-sm shadow-sm odd:self-start even:self-end even:rounded-br-sm even:rounded-bl-2xl dark:bg-gray-900"
                >
                    <span class="block text-xs text-gray-500">
                        {{ bubble.speaker }}
                    </span>
                    {{ bubble.line }}
                </li>
            </ul>

            <p
                v-if="scene.safety"
                class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                {{ scene.safety }}
            </p>

            <h1 class="text-2xl font-semibold">{{ scene.question }}</h1>

            <p
                v-if="formError"
                class="rounded-xl bg-red-50 p-3 text-sm text-red-700"
            >
                {{ formError }}
            </p>

            <!-- v-show keeps the chosen state, LGA and ward when the voter comes back to this scene. -->
            <div v-show="step === 'location'" class="flex flex-col gap-4">
                <PollingUnitPicker
                    v-model:polling-unit-id="form.polling_unit_id"
                    :states="props.states"
                    :error="form.errors.polling_unit_id"
                />
            </div>

            <div v-if="scene.choices" class="flex flex-col gap-3">
                <button
                    type="button"
                    class="rounded-xl bg-green-700 p-5 text-lg font-semibold text-white active:bg-green-800"
                    @click="
                        step === 'held' ? answerHeld(true) : answerVoted(true)
                    "
                >
                    {{ scene.choices.yes }}
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-gray-300 bg-white p-5 text-lg font-semibold active:bg-gray-100 dark:border-gray-700 dark:bg-gray-900"
                    @click="
                        step === 'held' ? answerHeld(false) : answerVoted(false)
                    "
                >
                    {{ scene.choices.no }}
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

            <ResultSheetPicker
                v-else-if="step === 'resultSheet'"
                v-model:photo="form.result_sheet"
                v-model:preparing="preparingPhoto"
                :error="form.errors.result_sheet"
                :disabled="form.processing"
                @chosen="form.clearErrors('result_sheet')"
            />

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
                class="sticky bottom-0 mt-auto flex flex-col gap-3 bg-gray-50 pt-2 pb-4 dark:bg-gray-950"
            >
                <button
                    v-if="!scene.choices"
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
                <Link
                    :href="reportForm()"
                    class="text-center text-sm font-medium text-green-700 dark:text-green-400"
                >
                    Use the plain form instead
                </Link>
            </div>
        </form>
    </div>
</template>
