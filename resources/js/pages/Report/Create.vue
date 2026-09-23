<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppHeader from '@/components/AppHeader.vue';
import PartyLogo from '@/components/PartyLogo.vue';
import ReasonPicker from '@/components/ReasonPicker.vue';
import { home } from '@/routes';
import { lgas, pollingUnits, wards } from '@/routes/locations';
import { store } from '@/routes/reports';
import type { Election, Option, Party, ReasonOption } from '@/types';

const props = defineProps<{
    election: Election | null;
    maskedVin: string;
    parties: Party[];
    states: Option[];
    notHeldReasons: ReasonOption[];
    notVotedReasons: ReasonOption[];
}>();

type Step =
    | 'location'
    | 'held'
    | 'notHeldReason'
    | 'voted'
    | 'notVotedReason'
    | 'party';

const step = ref<Step>('location');
const history = ref<Step[]>([]);

const form = useForm({
    polling_unit_id: null as number | null,
    election_held: null as boolean | null,
    not_held_reason: null as string | null,
    voted: null as boolean | null,
    not_voted_reason: null as string | null,
    party_id: null as number | null,
    reason_details: '',
    website: '',
});

const stateId = ref<number | null>(null);
const lgaId = ref<number | null>(null);
const wardId = ref<number | null>(null);
const lgaOptions = ref<Option[]>([]);
const wardOptions = ref<Option[]>([]);
const pollingUnitOptions = ref<Option[]>([]);
const pollingUnitQuery = ref('');

// Some wards have close to 300 units, far too many to scroll through on a phone.
const filteredPollingUnits = computed(() => {
    const query = pollingUnitQuery.value.trim().toLowerCase();

    if (query === '') {
        return pollingUnitOptions.value;
    }

    return pollingUnitOptions.value.filter(
        (unit) =>
            unit.id === form.polling_unit_id ||
            unit.name.toLowerCase().includes(query),
    );
});
const loadingLocations = ref(false);
const locationError = ref<string | null>(null);

async function fetchOptions(url: string): Promise<Option[]> {
    loadingLocations.value = true;
    locationError.value = null;

    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        return (await response.json()) as Option[];
    } catch {
        locationError.value =
            'Could not load locations. Check your connection and try again.';

        return [];
    } finally {
        loadingLocations.value = false;
    }
}

watch(stateId, async (id) => {
    lgaId.value = null;
    lgaOptions.value = id ? await fetchOptions(lgas.url(id)) : [];
});

watch(lgaId, async (id) => {
    wardId.value = null;
    wardOptions.value = id ? await fetchOptions(wards.url(id)) : [];
});

watch(wardId, async (id) => {
    form.polling_unit_id = null;
    pollingUnitQuery.value = '';
    pollingUnitOptions.value = id
        ? await fetchOptions(pollingUnits.url(id))
        : [];
});

const canContinue = computed(() => {
    switch (step.value) {
        case 'location':
            return form.polling_unit_id !== null;
        case 'notHeldReason':
            return hasReason(form.not_held_reason);
        case 'notVotedReason':
            return hasReason(form.not_voted_reason);
        case 'party':
            return form.party_id !== null;
        default:
            return false;
    }
});

const isFinalStep = computed(() =>
    ['notHeldReason', 'notVotedReason', 'party'].includes(step.value),
);

function hasReason(reason: string | null): boolean {
    if (reason === null) {
        return false;
    }

    return reason !== 'other' || form.reason_details.trim() !== '';
}

function goTo(next: Step): void {
    history.value.push(step.value);
    step.value = next;
}

function back(): void {
    const previous = history.value.pop();

    if (previous) {
        step.value = previous;
    }
}

function answerHeld(held: boolean): void {
    form.election_held = held;
    goTo(held ? 'voted' : 'notHeldReason');
}

function answerVoted(voted: boolean): void {
    form.voted = voted;
    goTo(voted ? 'party' : 'notVotedReason');
}

function next(): void {
    if (!canContinue.value) {
        return;
    }

    if (step.value === 'location') {
        goTo('held');
    } else if (isFinalStep.value) {
        submit();
    }
}

// Server-only errors with no field on this page: the election closed, or this VIN reported from another tab.
const formError = computed(() => {
    const errors = form.errors as Partial<Record<string, string>>;

    return errors.election ?? errors.vin;
});

const stepForField: Record<string, Step> = {
    polling_unit_id: 'location',
    not_held_reason: 'notHeldReason',
    not_voted_reason: 'notVotedReason',
    party_id: 'party',
};

function submit(): void {
    const held = form.election_held === true;
    const voted = held && form.voted === true;
    const reason = held ? form.not_voted_reason : form.not_held_reason;

    // Answers from branches the voter backed out of must not be sent.
    form.transform((data) => ({
        ...data,
        not_held_reason: held ? null : data.not_held_reason,
        voted: held ? data.voted : null,
        not_voted_reason: held && !voted ? data.not_voted_reason : null,
        party_id: voted ? data.party_id : null,
        reason_details:
            !voted && reason === 'other' ? data.reason_details : null,
    })).post(store.url(), {
        onError: (errors) => {
            const field = Object.keys(errors).find(
                (key) => key in stepForField,
            );

            if (field) {
                const target = stepForField[field];
                const index = history.value.indexOf(target);

                history.value =
                    index === -1 ? [] : history.value.slice(0, index);
                step.value = target;
            }
        },
    });
}

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
        default:
            return '';
    }
});

const selectClass =
    'w-full rounded-xl border border-gray-300 bg-white p-3 text-base disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900';
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

            <div v-if="step === 'location'" class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <label for="state" class="text-sm font-medium">State</label>
                    <select id="state" v-model="stateId" :class="selectClass">
                        <option :value="null" disabled>Select state</option>
                        <option
                            v-for="state in props.states"
                            :key="state.id"
                            :value="state.id"
                        >
                            {{ state.name }}
                        </option>
                    </select>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="lga" class="text-sm font-medium">LGA</label>
                    <select
                        id="lga"
                        v-model="lgaId"
                        :disabled="!lgaOptions.length"
                        :class="selectClass"
                    >
                        <option :value="null" disabled>Select LGA</option>
                        <option
                            v-for="lga in lgaOptions"
                            :key="lga.id"
                            :value="lga.id"
                        >
                            {{ lga.name }}
                        </option>
                    </select>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="ward" class="text-sm font-medium">Ward</label>
                    <select
                        id="ward"
                        v-model="wardId"
                        :disabled="!wardOptions.length"
                        :class="selectClass"
                    >
                        <option :value="null" disabled>Select ward</option>
                        <option
                            v-for="ward in wardOptions"
                            :key="ward.id"
                            :value="ward.id"
                        >
                            {{ ward.name }}
                        </option>
                    </select>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="polling-unit" class="text-sm font-medium">
                        Polling unit
                    </label>
                    <input
                        v-if="pollingUnitOptions.length"
                        v-model="pollingUnitQuery"
                        type="search"
                        aria-label="Search polling units"
                        :placeholder="`Search ${pollingUnitOptions.length} polling units`"
                        :class="selectClass"
                    />
                    <select
                        id="polling-unit"
                        v-model="form.polling_unit_id"
                        :disabled="!pollingUnitOptions.length"
                        :class="selectClass"
                    >
                        <option :value="null" disabled>
                            {{
                                pollingUnitQuery && !filteredPollingUnits.length
                                    ? 'No polling units match your search'
                                    : `Select polling unit${pollingUnitQuery ? ` (${filteredPollingUnits.length} found)` : ''}`
                            }}
                        </option>
                        <option
                            v-for="unit in filteredPollingUnits"
                            :key="unit.id"
                            :value="unit.id"
                        >
                            {{ unit.name }}
                        </option>
                    </select>
                </div>
                <p v-if="loadingLocations" class="text-sm text-gray-500">
                    Loading…
                </p>
                <p v-if="locationError" class="text-sm text-red-600">
                    {{ locationError }}
                </p>
                <p
                    v-if="form.errors.polling_unit_id"
                    class="text-sm text-red-600"
                >
                    {{ form.errors.polling_unit_id }}
                </p>
            </div>

            <div
                v-else-if="step === 'held' || step === 'voted'"
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

            <fieldset v-else-if="step === 'party'" class="flex flex-col gap-3">
                <label
                    v-for="party in props.parties"
                    :key="party.id"
                    class="flex cursor-pointer items-center gap-4 rounded-xl border border-gray-200 bg-white p-3 has-checked:border-green-700 has-checked:bg-green-50 dark:border-gray-800 dark:bg-gray-900 dark:has-checked:border-green-500 dark:has-checked:bg-green-950"
                >
                    <input
                        v-model="form.party_id"
                        type="radio"
                        name="party_id"
                        :value="party.id"
                        class="sr-only"
                    />
                    <PartyLogo
                        :src="party.logo_url"
                        :alt="`${party.acronym} logo`"
                        class="size-12 shrink-0 rounded-lg object-contain"
                    >
                        <span
                            class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-sm font-bold dark:bg-gray-800"
                        >
                            {{ party.acronym }}
                        </span>
                    </PartyLogo>
                    <span class="flex flex-col">
                        <span class="font-semibold">{{ party.acronym }}</span>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ party.name }}
                        </span>
                    </span>
                </label>
                <p v-if="form.errors.party_id" class="text-sm text-red-600">
                    {{ form.errors.party_id }}
                </p>
            </fieldset>

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
                            : isFinalStep
                              ? 'Submit report'
                              : 'Continue'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>
