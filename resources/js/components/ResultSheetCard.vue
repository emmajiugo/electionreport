<script setup lang="ts">
import type { BallotParty, FigureField, ResultSheet } from '@/types';

defineProps<{
    sheet: ResultSheet;
    figureFields: FigureField[];
    parties: BallotParty[];
}>();

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

const timeFormat = new Intl.DateTimeFormat('en-NG', {
    hour: 'numeric',
    minute: '2-digit',
});

function uploadedAt(sheet: ResultSheet): string {
    return timeFormat.format(new Date(sheet.uploaded_at));
}
</script>

<template>
    <article class="overflow-hidden rounded-xl bg-white dark:bg-gray-900">
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
                    sheet.is_yours ? 'Your upload' : 'Uploaded by a voter'
                }}</span>
                <span>{{ uploadedAt(sheet) }}</span>
            </p>
            <p
                class="rounded-lg px-3 py-2 text-sm"
                :class="statusClass[sheet.status]"
            >
                {{ sheet.status_label }} · {{ sheet.confirmations }} confirmed,
                {{ sheet.disputes }} disputed
            </p>

            <ul
                v-if="sheet.flags.length"
                class="flex flex-col gap-1 rounded-lg border border-amber-300 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:text-amber-200"
            >
                <li v-for="flag in sheet.flags" :key="flag.value">
                    ⚠ {{ flag.label }}
                </li>
            </ul>

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
                        <dt class="text-gray-600 dark:text-gray-400">
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
                            {{ sheet.figures.party_votes[party.id] }}
                        </dd>
                    </div>
                </dl>
            </details>

            <slot />
        </div>
    </article>
</template>
