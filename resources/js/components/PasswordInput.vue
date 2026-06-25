<script setup lang="ts">
import Password from 'primevue/password';

defineOptions({ inheritAttrs: false });
</script>

<template>
    <Password v-bind="$attrs" toggle-mask :feedback="false" fluid />
</template>
