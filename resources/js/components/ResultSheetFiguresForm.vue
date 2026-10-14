<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { store } from '@/routes/result-sheets/figures';
import type { BallotParty, FigureField } from '@/types';

const props = defineProps<{
    fields: FigureField[];
    parties: BallotParty[];
}>();

const form = useForm({
    totals: Object.fromEntries(
        props.fields.map((field) => [field.key, '']),
    ) as Record<string, string>,
    party_votes: Object.fromEntries(
        props.parties.map((party) => [party.id, '']),
    ) as Record<number, string>,
});

const errors = computed(() => form.errors as Partial<Record<string, string>>);

function submit(): void {
    // The server expects each total as its own top-level field, as named on the sheet.
    form.transform((data) => ({
        ...data.totals,
        party_votes: data.party_votes,
    })).post(store.url(), { preserveScroll: true });
}

const inputClass =
    'w-28 shrink-0 rounded-xl border border-gray-300 bg-white p-3 text-right text-base dark:border-gray-700 dark:bg-gray-900';
</script>

<template>
    <form
        class="flex flex-col gap-4 rounded-xl bg-white p-4 dark:bg-gray-900"
        @submit.prevent="submit"
    >
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold">Add the figures from your sheet</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Optional. Copy each number exactly as it is written on the
                sheet, even if it looks wrong. Use 0 for an empty box. You can
                only do this once.
            </p>
        </div>

        <p
            v-if="errors.figures"
            class="rounded-xl bg-red-50 p-3 text-sm text-red-700"
        >
            {{ errors.figures }}
        </p>

        <div v-for="field in fields" :key="field.key" class="flex flex-col">
            <label class="flex items-center justify-between gap-3">
                <span class="text-sm">{{ field.label }}</span>
                <input
                    v-model="form.totals[field.key]"
                    type="number"
                    inputmode="numeric"
                    min="0"
                    :class="inputClass"
                />
            </label>
            <p v-if="errors[field.key]" class="text-sm text-red-600">
                {{ errors[field.key] }}
            </p>
        </div>

        <h3 class="pt-2 font-semibold">Votes for each party</h3>
        <p v-if="errors.party_votes" class="text-sm text-red-600">
            {{ errors.party_votes }}
        </p>
        <div v-for="party in parties" :key="party.id" class="flex flex-col">
            <label class="flex items-center justify-between gap-3">
                <span class="text-sm">
                    <span class="font-semibold">{{ party.acronym }}</span>
                    <span class="text-gray-600 dark:text-gray-400">
                        · {{ party.name }}</span
                    >
                </span>
                <input
                    v-model="form.party_votes[party.id]"
                    type="number"
                    inputmode="numeric"
                    min="0"
                    :class="inputClass"
                />
            </label>
            <p
                v-if="errors[`party_votes.${party.id}`]"
                class="text-sm text-red-600"
            >
                {{ errors[`party_votes.${party.id}`] }}
            </p>
        </div>

        <button
            type="submit"
            class="rounded-xl bg-green-700 px-6 py-3 font-semibold text-white disabled:opacity-50"
            :disabled="form.processing"
        >
            {{ form.processing ? 'Saving…' : 'Save the figures' }}
        </button>
    </form>
</template>
