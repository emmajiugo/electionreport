<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    siteKey: string;
}>();

const token = defineModel<string>('token', { required: true });

const container = ref<HTMLElement | null>(null);
let widgetId: string | undefined;

const SCRIPT_URL =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let scriptLoading: Promise<void> | null = null;

function loadScript(): Promise<void> {
    if (window.turnstile) {
        return Promise.resolve();
    }

    scriptLoading ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Turnstile failed to load'));
        document.head.appendChild(script);
    });

    return scriptLoading;
}

onMounted(async () => {
    await loadScript();

    if (!container.value || !window.turnstile) {
        return;
    }

    widgetId = window.turnstile.render(container.value, {
        sitekey: props.siteKey,
        theme: 'auto',
        size: 'flexible',
        callback: (value) => (token.value = value),
        'expired-callback': () => (token.value = ''),
        'error-callback': () => (token.value = ''),
    });
});

onBeforeUnmount(() => {
    if (widgetId !== undefined) {
        window.turnstile?.remove(widgetId);
    }
});

// Tokens are single use, so a new challenge is needed after every submit the server rejects.
function reset(): void {
    token.value = '';

    if (widgetId !== undefined) {
        window.turnstile?.reset(widgetId);
    }
}

defineExpose({ reset });
</script>

<template>
    <div ref="container" class="min-h-[65px]" />
</template>
