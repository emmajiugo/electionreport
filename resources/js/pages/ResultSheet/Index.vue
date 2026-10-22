<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppHeader from '@/components/AppHeader.vue';
import NotOfficialNotice from '@/components/NotOfficialNotice.vue';
import SeeResultsButton from '@/components/SeeResultsButton.vue';
import SelectChevron from '@/components/SelectChevron.vue';
import StatTile from '@/components/results/StatTile.vue';
import { formatNumber } from '@/lib/format';
import { resultSheets } from '@/routes/polling-units';
import { index as resultSheetsIndex } from '@/routes/result-sheets';
import type { Election, SheetUnit } from '@/types';

defineProps<{
    election: Election | null;
    selectedState: { id: number; name: string } | null;
    states: { id: number; name: string }[];
    sheets: {
        polling_units: number;
        corroborated: number;
        contested: number;
        units: SheetUnit[];
    } | null;
}>();

const sheetStatusClass: Record<SheetUnit['status'], string> = {
    contested:
        'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    corroborated:
        'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200',
    unconfirmed:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
};

function scopeTo(stateId: number | null): void {
    router.get(
        resultSheetsIndex.url(
            stateId ? { query: { state: stateId } } : undefined,
        ),
    );
}
</script>

<template>
    <Head :title="`Result sheets · ${selectedState?.name ?? 'Nigeria'}`" />
    <div
        class="flex min-h-dvh flex-col bg-gray-200 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <AppHeader :election-name="election?.name" />

        <main class="mx-auto flex w-full max-w-md grow flex-col gap-6 p-4">
            <SeeResultsButton />

            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold">Result sheets</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Photos of polling unit result sheets (Form EC8A) uploaded by
                    voters, confirmed or disputed by other voters at the same
                    polling unit. Tap a polling unit to see its sheets.
                </p>
            </div>

            <NotOfficialNotice />

            <div class="relative">
                <label for="state" class="sr-only">State</label>
                <select
                    id="state"
                    :value="selectedState?.id ?? ''"
                    class="h-12 w-full appearance-none rounded-xl border border-gray-300 bg-white pr-10 pl-3 text-base dark:border-gray-700 dark:bg-gray-900"
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
                        v-for="state in states"
                        :key="state.id"
                        :value="state.id"
                    >
                        {{ state.name }}
                    </option>
                </select>
                <SelectChevron />
            </div>

            <template v-if="sheets">
                <div class="grid grid-cols-3 gap-3">
                    <StatTile
                        label="Polling units"
                        :value="formatNumber(sheets.polling_units)"
                        detail="with a sheet"
                    />
                    <StatTile
                        label="Confirmed"
                        :value="formatNumber(sheets.corroborated)"
                        detail="by several voters"
                    />
                    <StatTile
                        label="Disputed"
                        :value="formatNumber(sheets.contested)"
                        detail="by voters"
                    />
                </div>

                <ul
                    v-if="sheets.units.length"
                    class="flex flex-col divide-y divide-gray-100 rounded-xl bg-white px-4 dark:divide-gray-800 dark:bg-gray-900"
                >
                    <li v-for="unit in sheets.units" :key="unit.id">
                        <Link
                            :href="resultSheets(unit.id)"
                            class="flex flex-col items-start gap-1 py-3"
                        >
                            <span class="flex w-full min-w-0 flex-col">
                                <span
                                    class="truncate font-medium text-green-700 dark:text-green-400"
                                    >{{ unit.name }}</span
                                >
                                <span
                                    class="text-sm text-gray-600 dark:text-gray-400"
                                    >{{ unit.code }} · {{ unit.lga
                                    }}<template v-if="!selectedState"
                                        >, {{ unit.state }}</template
                                    >
                                    · {{ unit.sheets }}
                                    {{
                                        unit.sheets === 1 ? 'sheet' : 'sheets'
                                    }}</span
                                >
                            </span>
                            <span
                                class="rounded-lg px-2 py-1 text-xs"
                                :class="sheetStatusClass[unit.status]"
                                >{{ unit.status_label }}</span
                            >
                        </Link>
                    </li>
                </ul>

                <p
                    v-else
                    class="rounded-xl bg-white p-4 text-center text-gray-600 dark:bg-gray-900 dark:text-gray-400"
                >
                    No result sheet has been uploaded
                    {{ selectedState ? `in ${selectedState.name}` : '' }} yet.
                </p>

                <p
                    v-if="sheets.units.length < sheets.polling_units"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >
                    Showing {{ sheets.units.length }} of
                    {{ formatNumber(sheets.polling_units) }}, disputed polling
                    units first. Choose a state to narrow the list.
                </p>
            </template>

            <p
                v-else
                class="rounded-xl bg-white p-4 text-center text-gray-600 dark:bg-gray-900 dark:text-gray-400"
            >
                Result sheets appear here once an election opens for reports.
            </p>
        </main>
    </div>
</template>
