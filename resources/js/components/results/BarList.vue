<script setup lang="ts">
import { computed } from 'vue';
import PartyLogo from '@/components/PartyLogo.vue';
import { formatNumber, formatPercent, percent } from '@/lib/format';

export type BarRow = {
    key: string | number;
    label: string;
    sublabel?: string;
    value: number;
    logoUrl?: string | null;
};

const props = defineProps<{
    rows: BarRow[];
}>();

const total = computed(() =>
    props.rows.reduce((sum, row) => sum + row.value, 0),
);
const max = computed(() => Math.max(...props.rows.map((row) => row.value), 0));

// Bars scale to the largest value so small differences stay visible; the label carries the share of the total.
function barWidth(value: number): string {
    return max.value > 0 ? `${(value / max.value) * 100}%` : '0%';
}
</script>

<template>
    <ul class="flex flex-col gap-3">
        <li v-for="row in rows" :key="row.key" class="flex flex-col gap-1.5">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    <PartyLogo
                        v-if="row.logoUrl"
                        :src="row.logoUrl"
                        alt=""
                        class="size-6 shrink-0 rounded object-contain"
                    />
                    <span class="text-sm font-medium">{{ row.label }}</span>
                    <span
                        v-if="row.sublabel"
                        class="hidden truncate text-xs text-gray-500 sm:inline"
                        >{{ row.sublabel }}</span
                    >
                </div>
                <span
                    class="shrink-0 text-sm text-gray-700 tabular-nums dark:text-gray-300"
                >
                    {{ formatNumber(row.value) }}
                    <span class="text-gray-500"
                        >· {{ formatPercent(percent(row.value, total)) }}</span
                    >
                </span>
            </div>
            <div class="h-2.5">
                <div
                    class="h-2.5 rounded-r bg-(--viz-bar)"
                    :class="{ 'min-w-0.5': row.value > 0 }"
                    :style="{ width: barWidth(row.value) }"
                />
            </div>
        </li>
    </ul>
</template>
