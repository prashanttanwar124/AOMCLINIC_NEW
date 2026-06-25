<script setup lang="ts">
import Message from 'primevue/message';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        errors: string[];
        title?: string;
    }>(),
    {
        title: 'Something went wrong.',
    },
);

const uniqueErrors = computed(() => Array.from(new Set(props.errors)));
</script>

<template>
    <Message severity="error">
        <strong>{{ title }}</strong>
        <ul style="margin: 0.5rem 0 0; padding-left: 1rem">
            <li v-for="(error, index) in uniqueErrors" :key="index">
                {{ error }}
            </li>
        </ul>
    </Message>
</template>
