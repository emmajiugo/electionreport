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

const ramp = (name: string): string[] =>
    [1, 2, 3, 4, 5].map((step) => `var(--viz-${name}-${step})`);

const RAMPS: Record<MapMetric, string[]> = {
    reports: ramp('seq'),
    not_held: ramp('red'),
    not_voted: ramp('violet'),
};

// Fixed bands so a colour means the same thing on every visit. Coverage starts near zero and grows
// through election day; the problem shares mostly sit under 20%. Both are narrow at the low end.
const BIN_LIMITS: Record<MapMetric, number[]> = {
    reports: [1, 5, 10, 25, 100],
    not_held: [5, 10, 20, 40, 100],
    not_voted: [5, 10, 20, 40, 100],
};

const statesByCode = computed(
    () => new Map(props.states.map((state) => [state.code, state])),
);

/**
 * Coverage keeps one decimal because a state can sit well under 1% for most of the day.
 */
function coverage(state: AreaResult): number | null {
    return state.polling_units > 0
        ? Math.round(
              (state.polling_units_reporting / state.polling_units) * 1000,
          ) / 10
        : null;
}

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
            return coverage(state);
    }
}

type Bin = { max: number; color: string; label: string };

// No reports gets its own hatched class, and zero percent of a problem its own neutral class,
// so only states with a problem take that view's colour.
const bins = computed<Bin[]>(() => {
    const colors = RAMPS[props.metric];
    const limits = BIN_LIMITS[props.metric];

    return limits.map((max, index) => ({
        max,
        color: colors[index],
        label:
            index === 0
                ? `Up to ${max}%`
                : index === limits.length - 1
                  ? `Over ${limits[index - 1]}%`
                  : `${limits[index - 1]}–${max}%`,
    }));
});

function fill(code: string): string {
    const value = metricValue(statesByCode.value.get(code));

    if (value === null) {
        return 'url(#no-reports)';
    }

    if (value === 0 && props.metric !== 'reports') {
        return 'var(--viz-zero)';
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
    const items = bins.value.map(({ color, label }) => ({ color, label }));

    return props.metric === 'reports'
        ? items
        : [{ color: 'var(--viz-zero)', label: '0%' }, ...items];
});

const legendTitle = computed(
    () =>
        ({
            reports: 'Share of polling units with at least one report',
            not_held: "Share of reports saying the election didn't hold",
            not_voted:
                "Share of voters who couldn't vote where the election held",
        })[props.metric],
);

const metricLabel = computed(
    () =>
        ({
            reports: 'Polling units reporting',
            not_held: "Election didn't hold",
            not_voted: "Couldn't vote",
        })[props.metric],
);

function describe(code: string, name: string): string {
    const value = metricValue(statesByCode.value.get(code));

    if (value === null) {
        return `${name}: no reports`;
    }

    return `${name}: ${metricLabel.value} ${formatPercent(value)}`;
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
                <defs>
                    <pattern
                        id="no-reports"
                        width="10"
                        height="10"
                        patternUnits="userSpaceOnUse"
                        patternTransform="rotate(45)"
                    >
                        <rect
                            width="10"
                            height="10"
                            fill="var(--viz-no-data)"
                        />
                        <line
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="10"
                            stroke="var(--viz-no-data-line)"
                            stroke-width="3"
                        />
                    </pattern>
                </defs>
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
                            formatPercent(coverage(tooltipState))
                        }}</span>
                        <span class="text-gray-500"> of polling units</span>
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

        <p class="-mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
            {{ legendTitle }}
        </p>
        <ul
            class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-400"
            :aria-label="legendTitle"
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
                <span
                    class="size-3 rounded-sm bg-[repeating-linear-gradient(45deg,var(--viz-no-data-line)_0_1.5px,var(--viz-no-data)_1.5px_4px)]"
                />
                No reports
            </li>
        </ul>
    </div>
</template>
