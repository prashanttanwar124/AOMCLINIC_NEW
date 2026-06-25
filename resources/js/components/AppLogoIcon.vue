<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <defs>
            <linearGradient id="clinic-logo-grad" x1="0" y1="0" x2="48" y2="48" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#3b82f6" />
                <stop offset="50%" stop-color="#10b981" />
                <stop offset="100%" stop-color="#06b6d4" />
            </linearGradient>
            <filter id="clinic-logo-shadow" x="-10%" y="-10%" width="120%" height="120%">
                <feDropShadow dx="0" dy="1.5" stdDeviation="1.5" flood-color="#0f172a" flood-opacity="0.1" />
            </filter>
        </defs>
        <g filter="url(#clinic-logo-shadow)">
            <rect x="6" y="18" width="36" height="12" rx="6" fill="url(#clinic-logo-grad)" />
            <rect x="18" y="6" width="12" height="36" rx="6" fill="url(#clinic-logo-grad)" />
            <path d="M 10 24 L 17 24 L 19 14 L 22 34 L 25 20 L 27 24 L 38 24" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        </g>
    </svg>
</template>
