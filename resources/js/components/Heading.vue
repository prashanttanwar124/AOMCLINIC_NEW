<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header class="section-heading">
        <h2 v-if="variant === 'default'">{{ title }}</h2>
        <h3 v-else>{{ title }}</h3>
        <p v-if="description">{{ description }}</p>
    </header>
</template>
