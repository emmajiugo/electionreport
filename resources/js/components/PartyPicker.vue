<script setup lang="ts">
import PartyLogo from '@/components/PartyLogo.vue';
import type { Party } from '@/types';

defineProps<{
    parties: Party[];
    error?: string;
}>();

const partyId = defineModel<number | null>('partyId', { required: true });
</script>

<template>
    <fieldset class="flex flex-col gap-3">
        <label
            v-for="party in parties"
            :key="party.id"
            class="flex cursor-pointer items-center gap-4 rounded-xl border border-gray-200 bg-white p-3 has-checked:border-green-700 has-checked:bg-green-50 dark:border-gray-800 dark:bg-gray-900 dark:has-checked:border-green-500 dark:has-checked:bg-green-950"
        >
            <input
                v-model="partyId"
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
            <span class="flex min-w-0 flex-col">
                <span class="font-semibold">
                    {{ party.acronym }}
                    <span class="font-normal text-gray-600 dark:text-gray-400"
                        >· {{ party.name }}</span
                    >
                </span>
                <span v-if="party.candidate" class="text-sm">
                    {{ party.candidate }}
                    <span
                        v-if="party.running_mate"
                        class="text-gray-600 dark:text-gray-400"
                        >/ {{ party.running_mate }}</span
                    >
                </span>
            </span>
        </label>
        <p v-if="error" class="text-sm text-red-600">
            {{ error }}
        </p>
    </fieldset>
</template>
