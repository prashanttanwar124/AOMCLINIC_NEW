<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import type { FlashToast } from '@/types/ui';

const toast = useToast();

let removeListener: (() => void) | null = null;

onMounted(() => {
    removeListener = router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast.add({
            severity: data.type,
            summary: data.type.charAt(0).toUpperCase() + data.type.slice(1),
            detail: data.message,
            life: 3500,
        });
    });
});

onUnmounted(() => {
    removeListener?.();
});
</script>

<template>
    <span class="visually-hidden" aria-hidden="true"></span>
</template>
