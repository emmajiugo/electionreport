<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppHeader from '@/components/AppHeader.vue';
import NotOfficialNotice from '@/components/NotOfficialNotice.vue';
import BarList from '@/components/results/BarList.vue';
import StatTile from '@/components/results/StatTile.vue';
import StateMap from '@/components/results/StateMap.vue';
import { formatNumber, formatPercent, percent } from '@/lib/format';
import { home, results as resultsRoute } from '@/routes';
import type { AreaResult, Election, MapMetric, Results } from '@/types';

const props = defineProps<{
    election: Election | null;
    selectedState: { id: number; code: string; name: string } | null;
    results: Results | null;
}>();

// Matches the server's cache lifetime; polling faster would only return the same numbers.
usePoll(60_000, { only: ['results'] });

const metric = ref<MapMetric>('reports');

const metrics: { value: MapMetric; label: string }[] = [
    { value: 'reports', label: 'Reports' },
    { value: 'not_held', label: "Didn't hold" },
    { value: 'not_voted', label: "Couldn't vote" },
];

const summary = computed(() => props.results?.summary);
const scopeName = computed(() => props.selectedState?.name ?? 'Nigeria');

// Parties nobody has reported voting for are listed by name instead of as a column of empty bars.
const partiesWithoutVotes = computed(() =>
    (props.results?.parties ?? [])
        .filter((party) => party.votes === 0)
        .map((party) => party.acronym),
);

const partyRows = computed(() =>
    (props.results?.parties ?? [])
        .filter((party) => party.votes > 0)
        .map((party) => ({
            key: party.id,
            label: party.acronym,
            sublabel: party.name,
            value: party.votes,
            logoUrl: party.logo_url,
        })),
);

const notHeldRows = computed(() =>
    (props.results?.notHeldReasons ?? []).map((reason) => ({
        key: reason.value,
        label: reason.label,
        value: reason.count,
    })),
);

const notVotedRows = computed(() =>
    (props.results?.notVotedReasons ?? []).map((reason) => ({
        key: reason.value,
        label: reason.label,
        value: reason.count,
    })),
);

const tableRows = computed<AreaResult[]>(() =>
    [
        ...((props.selectedState
            ? props.results?.lgas
            : props.results?.states) ?? []),
    ].sort((a, b) => b.reports - a.reports || a.name.localeCompare(b.name)),
);

const updatedAt = computed(() =>
    props.results
        ? new Date(props.results.generatedAt).toLocaleTimeString('en-NG', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : null,
);

function scopeTo(stateId: number | null): void {
    router.get(
        resultsRoute.url(stateId ? { query: { state: stateId } } : undefined),
        {},
        { preserveScroll: true },
    );
}

function selectFromMap(state: AreaResult): void {
    scopeTo(state.id === props.selectedState?.id ? null : state.id);
}
</script>

<template>
    <Head :title="`Results · ${scopeName}`" />
    <div
        class="flex min-h-dvh flex-col bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <AppHeader :election-name="election?.name" />

        <main
            v-if="!election || !results || !summary"
            class="mx-auto flex w-full max-w-md grow flex-col items-center justify-center gap-2 p-6 text-center"
        >
            <h1 class="text-xl font-semibold">No results yet</h1>
            <p class="text-gray-600 dark:text-gray-400">
                Results appear here once an election opens for reports.
            </p>
        </main>

        <main v-else class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold">
                    What voters are reporting in {{ scopeName }}
                </h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Updated {{ updatedAt }}, refreshes every minute.
                </p>
            </div>

            <NotOfficialNotice />

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <label for="scope" class="sr-only">Area</label>
                <select
                    id="scope"
                    :value="selectedState?.id ?? ''"
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-3 text-base sm:w-64 dark:border-gray-700 dark:bg-gray-900"
                    @change="
                        scopeTo(
                            Number(
                                ($event.target as HTMLSelectElement).value,
                            ) || null,
                        )
                    "
                >
                    <option value="">All of Nigeria</option>
                    <option
                        v-for="state in results.states"
                        :key="state.id"
                        :value="state.id"
                    >
                        {{ state.name }}
                    </option>
                </select>
                <Link
                    :href="home()"
                    class="flex h-12 items-center justify-center rounded-xl border border-green-700 bg-green-700 px-4 font-semibold text-white sm:ml-auto"
                >
                    Report your polling unit
                </Link>
            </div>

            <section
                class="flex flex-col gap-1 rounded-xl bg-white p-4 dark:bg-gray-900"
                aria-label="Total reports"
            >
                <span class="text-sm text-gray-600 dark:text-gray-400"
                    >Reports received</span
                >
                <span class="text-5xl font-semibold">{{
                    formatNumber(summary.reports)
                }}</span>
            </section>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <StatTile
                    label="Polling units reporting"
                    :value="formatNumber(summary.polling_units_reporting)"
                    :detail="`of ${formatNumber(summary.polling_units)} (${formatPercent(percent(summary.polling_units_reporting, summary.polling_units))})`"
                />
                <StatTile
                    label="Election didn't hold"
                    :value="
                        formatPercent(
                            percent(summary.not_held, summary.reports),
                        )
                    "
                    :detail="`${formatNumber(summary.not_held)} reports`"
                />
                <StatTile
                    label="Couldn't vote where it held"
                    :value="
                        formatPercent(percent(summary.not_voted, summary.held))
                    "
                    :detail="`${formatNumber(summary.not_voted)} of ${formatNumber(summary.held)} reports`"
                />
            </div>

            <section
                class="flex flex-col gap-4 rounded-xl bg-white p-4 dark:bg-gray-900"
            >
                <div>
                    <h2 class="font-semibold">Who voters say they voted for</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ formatNumber(summary.voted) }} votes reported
                    </p>
                </div>
                <BarList v-if="partyRows.length" :rows="partyRows" />
                <p
                    v-if="partiesWithoutVotes.length"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >
                    {{
                        partyRows.length
                            ? `No votes reported for ${partiesWithoutVotes.join(', ')}.`
                            : 'No votes reported yet.'
                    }}
                </p>
            </section>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <section
                    class="flex flex-col gap-4 rounded-xl bg-white p-4 dark:bg-gray-900"
                >
                    <h2 class="font-semibold">Why the election didn't hold</h2>
                    <BarList :rows="notHeldRows" />
                </section>
                <section
                    class="flex flex-col gap-4 rounded-xl bg-white p-4 dark:bg-gray-900"
                >
                    <h2 class="font-semibold">Why voters couldn't vote</h2>
                    <BarList :rows="notVotedRows" />
                </section>
            </div>

            <section
                class="flex flex-col gap-4 rounded-xl bg-white p-4 dark:bg-gray-900"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">By state</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Tap a state to see its LGAs.
                        </p>
                    </div>
                    <div
                        class="flex w-full rounded-lg bg-gray-100 p-1 text-sm sm:w-auto dark:bg-gray-800"
                        role="radiogroup"
                        aria-label="Map shows"
                    >
                        <button
                            v-for="option in metrics"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :aria-checked="metric === option.value"
                            class="flex-1 rounded-md px-3 py-1.5 whitespace-nowrap"
                            :class="
                                metric === option.value
                                    ? 'bg-white font-semibold shadow-sm dark:bg-gray-950'
                                    : 'text-gray-600 dark:text-gray-400'
                            "
                            @click="metric = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
                <StateMap
                    :states="results.states"
                    :metric="metric"
                    :selected-code="selectedState?.code ?? null"
                    @select="selectFromMap"
                />
            </section>

            <section
                class="flex flex-col gap-4 rounded-xl bg-white p-4 dark:bg-gray-900"
            >
                <h2 class="font-semibold">
                    {{
                        selectedState
                            ? `LGAs in ${selectedState.name}`
                            : 'All states'
                    }}
                </h2>
                <div class="-mx-4 overflow-x-auto px-4">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead
                            class="border-b border-gray-200 text-gray-500 dark:border-gray-800"
                        >
                            <tr>
                                <th class="py-2 pr-2 font-medium">
                                    {{ selectedState ? 'LGA' : 'State' }}
                                </th>
                                <th class="py-2 pr-2 text-right font-medium">
                                    Reports
                                </th>
                                <th class="py-2 pr-2 text-right font-medium">
                                    <span class="sm:hidden">Not held</span>
                                    <span class="hidden sm:inline"
                                        >Didn't hold</span
                                    >
                                </th>
                                <th class="py-2 pr-2 text-right font-medium">
                                    <span class="sm:hidden">No vote</span>
                                    <span class="hidden sm:inline"
                                        >Couldn't vote</span
                                    >
                                </th>
                                <th class="py-2 font-medium">
                                    <span class="sm:hidden">Lead</span>
                                    <span class="hidden sm:inline"
                                        >Leading party</span
                                    >
                                </th>
                            </tr>
                        </thead>
                        <tbody class="tabular-nums">
                            <tr
                                v-for="row in tableRows"
                                :key="row.id"
                                class="border-b border-gray-100 last:border-0 dark:border-gray-800"
                            >
                                <td class="py-2 pr-2">
                                    <button
                                        v-if="!selectedState"
                                        type="button"
                                        class="text-left font-medium text-green-700 dark:text-green-400"
                                        @click="scopeTo(row.id)"
                                    >
                                        {{ row.name }}
                                    </button>
                                    <span v-else>{{ row.name }}</span>
                                </td>
                                <td class="py-2 pr-2 text-right">
                                    {{ formatNumber(row.reports) }}
                                </td>
                                <td class="py-2 pr-2 text-right">
                                    {{
                                        formatPercent(
                                            percent(row.not_held, row.reports),
                                        )
                                    }}
                                </td>
                                <td class="py-2 pr-2 text-right">
                                    {{
                                        formatPercent(
                                            percent(row.not_voted, row.held),
                                        )
                                    }}
                                </td>
                                <td class="py-2">
                                    {{ row.leading_party ?? '–' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p class="text-xs text-gray-500">
                State boundaries from
                <a
                    href="https://www.geoboundaries.org/"
                    class="underline"
                    rel="noopener"
                    target="_blank"
                    >geoBoundaries</a
                >
                (GRID3), CC BY 4.0. Polling unit register from INEC.
            </p>
        </main>
    </div>
</template>
