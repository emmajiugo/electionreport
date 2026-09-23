<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    src: string | null;
    alt: string;
}>();

const failed = ref(false);
const image = ref<HTMLImageElement | null>(null);

// With SSR the browser can fail to load the image before Vue hydrates and attaches @error, so check once mounted too.
onMounted(() => {
    if (image.value?.complete && image.value.naturalWidth === 0) {
        failed.value = true;
    }
});

watch(
    () => props.src,
    () => (failed.value = false),
);
</script>

<template>
    <img
        v-if="src && !failed"
        ref="image"
        :src="src"
        :alt="alt"
        v-bind="$attrs"
        @error="failed = true"
    />
    <slot v-else />
</template>
