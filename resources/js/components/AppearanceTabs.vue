<script setup lang="ts">
import SelectButton from 'primevue/selectbutton';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const options = [
    { label: 'Light', value: 'light' },
    { label: 'Dark', value: 'dark' },
    { label: 'System', value: 'system' },
];

const selected = computed({
    get: () => appearance.value,
    set: (value) => updateAppearance(value),
});
</script>

<template>
    <SelectButton
        v-model="selected"
        :options="options"
        option-label="label"
        option-value="value"
        aria-labelledby="appearance-mode"
    />
</template>
