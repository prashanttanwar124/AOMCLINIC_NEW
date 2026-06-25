<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { nextTick, onMounted, ref } from 'vue';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

const { recoveryCodesList, fetchRecoveryCodes, errors } = useTwoFactorAuth();
const isRecoveryCodesVisible = ref(false);

const toggleRecoveryCodesVisibility = async () => {
    if (!isRecoveryCodesVisible.value && !recoveryCodesList.value.length) {
        await fetchRecoveryCodes();
    }

    isRecoveryCodesVisible.value = !isRecoveryCodesVisible.value;
    await nextTick();
};

onMounted(async () => {
    if (!recoveryCodesList.value.length) {
        await fetchRecoveryCodes();
    }
});
</script>

<template>
    <div class="page-card section-stack">
        <Heading
            variant="small"
            title="2FA recovery codes"
            description="Recovery codes let you regain access if you lose your 2FA device."
        />

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap">
            <Button
                :label="
                    isRecoveryCodesVisible
                        ? 'Hide recovery codes'
                        : 'View recovery codes'
                "
                severity="secondary"
                @click="toggleRecoveryCodesVisibility"
            />

            <Form
                v-if="isRecoveryCodesVisible && recoveryCodesList.length"
                v-bind="regenerateRecoveryCodes.form()"
                method="post"
                :options="{ preserveScroll: true }"
                @success="fetchRecoveryCodes"
                #default="{ processing }"
            >
                <Button
                    type="submit"
                    label="Regenerate codes"
                    text
                    :loading="processing"
                />
            </Form>
        </div>

        <AlertError v-if="errors?.length" :errors="errors" />

        <div
            v-if="isRecoveryCodesVisible"
            class="page-card"
            style="padding: 1rem"
        >
            <div
                v-for="(code, index) in recoveryCodesList"
                :key="index"
                style="
                    font-family: monospace;
                    padding: 0.35rem 0;
                    color: var(--text-color);
                "
            >
                {{ code }}
            </div>
        </div>
    </div>
</template>
