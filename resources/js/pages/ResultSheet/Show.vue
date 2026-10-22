<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppHeader from '@/components/AppHeader.vue';
import NotOfficialNotice from '@/components/NotOfficialNotice.vue';
import ResultSheetCard from '@/components/ResultSheetCard.vue';
import SeeResultsButton from '@/components/SeeResultsButton.vue';
import { index as resultSheetsIndex } from '@/routes/result-sheets';
import type {
    BallotParty,
    Election,
    FigureField,
    PollingUnitDetails,
    ResultSheet,
} from '@/types';

defineProps<{
    election: Election | null;
    pollingUnit: PollingUnitDetails;
    sheets: ResultSheet[];
    figureFields: FigureField[];
    parties: BallotParty[];
}>();
</script>

<template>
    <Head :title="`Result sheets · ${pollingUnit.name}`" />
    <div
        class="flex min-h-dvh flex-col bg-gray-200 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <AppHeader :election-name="election?.name" />

        <main class="mx-auto flex w-full max-w-md grow flex-col gap-6 p-4">
            <SeeResultsButton />

            <h1 class="text-2xl font-semibold">Result sheets</h1>

            <div class="rounded-xl bg-white p-4 dark:bg-gray-900">
                <p class="font-medium">{{ pollingUnit.name }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ pollingUnit.code }} · {{ pollingUnit.ward }},
                    {{ pollingUnit.lga }}, {{ pollingUnit.state }}
                </p>
            </div>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Photos of the result sheet (Form EC8A) posted at this polling
                unit, uploaded by voters who reported from it. Other voters from
                the same polling unit confirm or dispute each photo. Warnings
                point out things worth a closer look; they are not proof of
                wrongdoing.
            </p>

            <NotOfficialNotice />

            <section v-if="sheets.length" class="flex flex-col gap-3">
                <ResultSheetCard
                    v-for="sheet in sheets"
                    :key="sheet.id"
                    :sheet="sheet"
                    :figure-fields="figureFields"
                    :parties="parties"
                />
            </section>

            <p
                v-else
                class="rounded-xl bg-white p-4 text-center text-gray-600 dark:bg-gray-900 dark:text-gray-400"
            >
                No result sheet has been uploaded for this polling unit yet.
            </p>

            <div class="mt-auto flex flex-col gap-3 text-center">
                <Link
                    :href="resultSheetsIndex()"
                    class="font-medium text-green-700 dark:text-green-400"
                >
                    See all result sheets
                </Link>
            </div>
        </main>
    </div>
</template>
