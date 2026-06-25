<script setup lang="ts">
import Message from 'primevue/message';

defineProps<{
    message?: string;
}>();
</script>

<template>
    <Message v-if="message" severity="error" size="small" variant="simple">
        {{ message }}
    </Message>
</template>
