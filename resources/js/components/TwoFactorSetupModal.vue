<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import InputOtp from 'primevue/inputotp';
import InputText from 'primevue/inputtext';
import { useClipboard } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { confirm } from '@/routes/two-factor';

const props = defineProps<{
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
}>();

const isOpen = defineModel<boolean>('isOpen');
const showVerificationStep = ref(false);
const code = ref('');
const { copy, copied } = useClipboard();
const { qrCodeSvg, manualSetupKey, clearSetupData, fetchSetupData, errors } =
    useTwoFactorAuth();

const title = computed(() => {
    if (props.twoFactorEnabled) {
        return 'Two-factor authentication enabled';
    }

    return showVerificationStep.value
        ? 'Verify authentication code'
        : 'Enable two-factor authentication';
});

watch(
    () => isOpen.value,
    async (open) => {
        if (!open) {
            showVerificationStep.value = false;
            code.value = '';
            return;
        }

        if (!qrCodeSvg.value) {
            await fetchSetupData();
        }
    },
);
</script>

<template>
    <Dialog v-model:visible="isOpen" modal :header="title" style="width: 34rem">
        <div class="section-stack">
            <template v-if="!showVerificationStep">
                <AlertError v-if="errors?.length" :errors="errors" />

                <div
                    v-else
                    style="display: flex; flex-direction: column; gap: 1rem"
                >
                    <div
                        v-if="qrCodeSvg"
                        class="page-card"
                        style="
                            display: grid;
                            place-items: center;
                            padding: 1rem;
                        "
                    >
                        <div v-html="qrCodeSvg"></div>
                    </div>

                    <Button
                        :label="requiresConfirmation ? 'Continue' : 'Close'"
                        @click="
                            requiresConfirmation
                                ? (showVerificationStep = true)
                                : ((isOpen = false), clearSetupData())
                        "
                    />

                    <Divider align="center">Manual setup key</Divider>

                    <div style="display: flex; gap: 0.5rem">
                        <InputText
                            :model-value="manualSetupKey ?? ''"
                            readonly
                            fluid
                        />
                        <Button
                            :icon="copied ? 'pi pi-check' : 'pi pi-copy'"
                            severity="secondary"
                            @click="copy(manualSetupKey ?? '')"
                        />
                    </div>
                </div>
            </template>

            <template v-else>
                <Form
                    v-bind="confirm.form()"
                    error-bag="confirmTwoFactorAuthentication"
                    reset-on-error
                    @finish="code = ''"
                    @success="isOpen = false"
                    v-slot="{ errors, processing }"
                    class="auth-form"
                >
                    <input type="hidden" name="code" :value="code" />
                    <div class="field">
                        <label for="otp">Verification code</label>
                        <InputOtp v-model="code" :length="6" fluid />
                        <InputError :message="errors?.code" />
                    </div>

                    <div style="display: flex; gap: 0.75rem">
                        <Button
                            type="button"
                            label="Back"
                            severity="secondary"
                            outlined
                            @click="showVerificationStep = false"
                        />
                        <Button
                            type="submit"
                            label="Confirm"
                            :loading="processing"
                            :disabled="code.length < 6"
                        />
                    </div>
                </Form>
            </template>
        </div>
    </Dialog>
</template>
