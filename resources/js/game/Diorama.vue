<script setup lang="ts">
import { onBeforeUnmount, onMounted, useTemplateRef, watch } from 'vue';
import { createDiorama } from '@/game/diorama';
import type { Crowd, Diorama, Point } from '@/game/diorama';

const props = defineProps<{
    /** Minutes since midnight in the story. */
    minutes: number;
    crowd: Crowd;
    /** Where the camera looks for this scene. */
    focus: Point;
}>();

const canvas = useTemplateRef<HTMLCanvasElement>('canvas');

let diorama: Diorama | null = null;
let resizeObserver: ResizeObserver | null = null;
let visibilityObserver: IntersectionObserver | null = null;

onMounted(() => {
    if (!canvas.value) {
        return;
    }

    const created = createDiorama(canvas.value, {
        reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)')
            .matches,
    });
    diorama = created;
    created.setTime(props.minutes);
    created.setCrowd(props.crowd);
    created.setFocus(props.focus);

    resizeObserver = new ResizeObserver(([entry]) =>
        created.resize(entry.contentRect.width, entry.contentRect.height),
    );
    resizeObserver.observe(canvas.value);

    // Stop drawing while the scene is scrolled out of view to save battery.
    visibilityObserver = new IntersectionObserver(([entry]) =>
        created.setRunning(entry.isIntersecting),
    );
    visibilityObserver.observe(canvas.value);
});

watch(
    () => props.minutes,
    (minutes) => diorama?.setTime(minutes),
);
watch(
    () => props.crowd,
    (crowd) => diorama?.setCrowd(crowd),
);
watch(
    () => props.focus,
    (focus) => diorama?.setFocus(focus),
);

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    visibilityObserver?.disconnect();
    diorama?.dispose();
    diorama = null;
});
</script>

<template>
    <canvas ref="canvas" class="block size-full" />
</template>
