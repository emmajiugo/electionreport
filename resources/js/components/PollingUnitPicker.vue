<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import SelectChevron from '@/components/SelectChevron.vue';
import { lgas, pollingUnits, wards } from '@/routes/locations';
import type { Option } from '@/types';

defineProps<{
    states: Option[];
    error?: string;
}>();

const pollingUnitId = defineModel<number | null>('pollingUnitId', {
    required: true,
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
            unit.id === pollingUnitId.value ||
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
    pollingUnitId.value = null;
    pollingUnitQuery.value = '';
    pollingUnitOptions.value = id
        ? await fetchOptions(pollingUnits.url(id))
        : [];
});

const fieldClass =
    'w-full rounded-xl border border-gray-300 bg-white p-3 text-base disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900';
const selectClass = `${fieldClass} appearance-none pr-10`;
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
            <label for="state" class="text-sm font-medium">State</label>
            <div class="relative">
                <select id="state" v-model="stateId" :class="selectClass">
                    <option :value="null" disabled>Select state</option>
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
        </div>
        <div class="flex flex-col gap-2">
            <label for="lga" class="text-sm font-medium">LGA</label>
            <div class="relative">
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
                <SelectChevron />
            </div>
        </div>
        <div class="flex flex-col gap-2">
            <label for="ward" class="text-sm font-medium">Ward</label>
            <div class="relative">
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
                <SelectChevron />
            </div>
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
                :class="fieldClass"
            />
            <div class="relative">
                <select
                    id="polling-unit"
                    v-model="pollingUnitId"
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
                <SelectChevron />
            </div>
        </div>
        <p v-if="loadingLocations" class="text-sm text-gray-500">Loading…</p>
        <p v-if="locationError" class="text-sm text-red-600">
            {{ locationError }}
        </p>
        <p v-if="error" class="text-sm text-red-600">
            {{ error }}
        </p>
    </div>
</template>
