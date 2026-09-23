<script setup lang="ts">
import { computed, ref } from 'vue';
import geography from '@/data/nigeria-states.json';
import { formatNumber, formatPercent, percent } from '@/lib/format';
import type { AreaResult, MapMetric } from '@/types';

const props = defineProps<{
    states: AreaResult[];
    metric: MapMetric;
    selectedCode: string | null;
}>();

const emit = defineEmits<{
    select: [state: AreaResult];
}>();

const BIN_COLORS = [
    'var(--viz-seq-1)',
    'var(--viz-seq-2)',
    'var(--viz-seq-3)',
    'var(--viz-seq-4)',
    'var(--viz-seq-5)',
];

const statesByCode = computed(
    () => new Map(props.states.map((state) => [state.code, state])),
);

function metricValue(state: AreaResult | undefined): number | null {
    if (!state || state.reports === 0) {
        return null;
    }

    switch (props.metric) {
        case 'not_held':
            return percent(state.not_held, state.reports);
        case 'not_voted':
            return percent(state.not_voted, state.held);
        default:
            return state.reports;
    }
}

type Bin = { min: number; max: number; color: string };

// Percentages use fixed 20-point bins so colours mean the same thing on every visit; counts scale to the busiest state.
// Zero reports is its own "No reports" class, so count bins start at 1.
const bins = computed<Bin[]>(() => {
    if (props.metric !== 'reports') {
        return BIN_COLORS.map((color, index) => ({
            min: index === 0 ? 0 : index * 20 + 1,
            max: (index + 1) * 20,
            color,
        }));
    }

    const max = Math.max(...props.states.map((state) => state.reports), 0);
    const step = Math.max(1, Math.ceil(max / BIN_COLORS.length));

    return BIN_COLORS.map((color, index) => ({
        min: index * step + 1,
        max: (index + 1) * step,
        color,
    }));
});

function fill(code: string): string {
    const value = metricValue(statesByCode.value.get(code));

    if (value === null) {
        return 'var(--viz-no-data)';
    }

    return (
        bins.value.find((bin) => value <= bin.max) ??
        bins.value[bins.value.length - 1]
    ).color;
}

const hasReports = computed(() =>
    props.states.some((state) => state.reports > 0),
);

const legend = computed(() => {
    const unit = props.metric === 'reports' ? '' : '%';

    return bins.value.map((bin) => ({
        color: bin.color,
        label:
            bin.min === bin.max
                ? `${formatNumber(bin.min)}${unit}`
                : `${formatNumber(bin.min)}–${formatNumber(bin.max)}${unit}`,
    }));
});

const metricLabel = computed(
    () =>
        ({
            reports: 'Reports',
            not_held: "Election didn't hold",
            not_voted: "Couldn't vote",
        })[props.metric],
);

function describe(code: string, name: string): string {
    const value = metricValue(statesByCode.value.get(code));

    if (value === null) {
        return `${name}: no reports`;
    }

    return `${name}: ${metricLabel.value} ${props.metric === 'reports' ? formatNumber(value) : formatPercent(value)}`;
}

const container = ref<HTMLElement | null>(null);
const tooltip = ref<{ code: string; x: number; y: number } | null>(null);

const tooltipState = computed(() =>
    tooltip.value ? statesByCode.value.get(tooltip.value.code) : undefined,
);

function showAt(code: string, clientX: number, clientY: number): void {
    const bounds = container.value?.getBoundingClientRect();

    if (bounds) {
        tooltip.value = {
            code,
            x: clientX - bounds.left,
            y: clientY - bounds.top,
        };
    }
}

function showForFocus(code: string, event: FocusEvent): void {
    const box = (event.target as SVGGraphicsElement).getBoundingClientRect();
    showAt(code, box.left + box.width / 2, box.top + box.height / 2);
}

function select(code: string): void {
    const state = statesByCode.value.get(code);

    if (state) {
        emit('select', state);
    }
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div ref="container" class="relative">
            <svg
                :viewBox="geography.viewBox"
                class="h-auto w-full"
                role="group"
                :aria-label="`Map of Nigerian states coloured by ${metricLabel.toLowerCase()}`"
                @pointerleave="tooltip = null"
            >
                <path
                    v-for="shape in geography.states"
                    :key="shape.code"
                    :d="shape.d"
                    :fill="fill(shape.code)"
                    class="cursor-pointer stroke-white outline-none hover:opacity-80 focus-visible:opacity-80 dark:stroke-gray-900"
                    :class="
                        shape.code === selectedCode
                            ? 'stroke-gray-900! stroke-[3] dark:stroke-white!'
                            : 'stroke-[1.5]'
                    "
                    stroke-linejoin="round"
                    tabindex="0"
                    role="button"
                    :aria-label="describe(shape.code, shape.name)"
                    @pointermove="
                        showAt(shape.code, $event.clientX, $event.clientY)
                    "
                    @focus="showForFocus(shape.code, $event)"
                    @blur="tooltip = null"
                    @click="select(shape.code)"
                    @keydown.enter.prevent="select(shape.code)"
                />
            </svg>

            <div
                v-if="tooltip && tooltipState"
                class="pointer-events-none absolute z-10 w-44 -translate-x-1/2 -translate-y-[calc(100%+12px)] rounded-lg bg-white p-3 text-sm shadow-lg ring-1 ring-black/10 dark:bg-gray-800 dark:ring-white/10"
                :style="{ left: `${tooltip.x}px`, top: `${tooltip.y}px` }"
            >
                <p class="font-semibold">{{ tooltipState.name }}</p>
                <p v-if="tooltipState.reports === 0" class="text-gray-500">
                    No reports yet
                </p>
                <template v-else>
                    <p>
                        <span class="font-semibold tabular-nums">{{
                            formatNumber(tooltipState.reports)
                        }}</span>
                        <span class="text-gray-500"> reports</span>
                    </p>
                    <p>
                        <span class="font-semibold tabular-nums">{{
                            formatPercent(
                                percent(
                                    tooltipState.not_held,
                                    tooltipState.reports,
                                ),
                            )
                        }}</span>
                        <span class="text-gray-500"> didn't hold</span>
                    </p>
                    <p>
                        <span class="font-semibold tabular-nums">{{
                            formatPercent(
                                percent(
                                    tooltipState.not_voted,
                                    tooltipState.held,
                                ),
                            )
                        }}</span>
                        <span class="text-gray-500"> couldn't vote</span>
                    </p>
                    <p v-if="tooltipState.leading_party" class="text-gray-500">
                        Leading: {{ tooltipState.leading_party }}
                    </p>
                </template>
            </div>
        </div>

        <ul
            class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-400"
            :aria-label="`${metricLabel} legend`"
        >
            <li
                v-for="bin in hasReports ? legend : []"
                :key="bin.label"
                class="flex items-center gap-1.5"
            >
                <span
                    class="size-3 rounded-sm"
                    :style="{ background: bin.color }"
                />
                {{ bin.label }}
            </li>
            <li class="flex items-center gap-1.5">
                <span class="size-3 rounded-sm bg-(--viz-no-data)" />
                No reports
            </li>
        </ul>
    </div>
</template>
