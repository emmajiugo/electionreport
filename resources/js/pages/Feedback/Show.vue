<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppHeader from '@/components/AppHeader.vue';
import { home } from '@/routes';

defineProps<{
    formUrl: string;
}>();
</script>

<template>
    <Head title="Feedback" />
    <div class="flex h-dvh flex-col bg-white text-gray-900">
        <AppHeader show-back @back="router.visit(home())" />
        <iframe
            :src="formUrl"
            title="ElectionReport feedback form"
            class="w-full grow border-0"
        ></iframe>
    </div>
</template>
