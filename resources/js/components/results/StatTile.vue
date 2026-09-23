<script setup lang="ts">
defineProps<{
    label: string;
    value: string;
    detail?: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-1 rounded-xl bg-white p-4 dark:bg-gray-900">
        <span class="text-sm text-gray-600 dark:text-gray-400">{{
            label
        }}</span>
        <span class="text-2xl font-semibold">{{ value }}</span>
        <span v-if="detail" class="text-xs text-gray-500">{{ detail }}</span>
    </div>
</template>
