<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import InputOtp from 'primevue/inputotp';
import InputText from 'primevue/inputtext';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref(false);
const code = ref('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery code',
            description:
                'Please confirm access to your account by entering one of your emergency recovery codes.',
            buttonText: 'login using an authentication code',
        };
    }

    return {
        title: 'Authentication code',
        description:
            'Enter the authentication code provided by your authenticator application.',
        buttonText: 'login using a recovery code',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="section-stack">
        <Form
            v-if="!showRecoveryInput"
            v-bind="store.form()"
            reset-on-error
            @error="code = ''"
            #default="{ errors, processing, clearErrors }"
            class="auth-form"
        >
            <input type="hidden" name="code" :value="code" />
            <div class="field">
                <label for="otp">Authentication code</label>
                <InputOtp v-model="code" :length="6" fluid />
                <InputError :message="errors.code" />
            </div>

            <Button
                type="submit"
                label="Continue"
                :loading="processing"
                fluid
            />

            <Divider align="center">or</Divider>

            <button
                type="button"
                class="link-button"
                @click="() => toggleRecoveryMode(clearErrors)"
            >
                {{ authConfigContent.buttonText }}
            </button>
        </Form>

        <Form
            v-else
            v-bind="store.form()"
            reset-on-error
            #default="{ errors, processing, clearErrors }"
            class="auth-form"
        >
            <div class="field">
                <label for="recovery_code">Recovery code</label>
                <InputText
                    id="recovery_code"
                    name="recovery_code"
                    type="text"
                    placeholder="Enter recovery code"
                    required
                    fluid
                />
                <InputError :message="errors.recovery_code" />
            </div>

            <Button
                type="submit"
                label="Continue"
                :loading="processing"
                fluid
            />

            <Divider align="center">or</Divider>

            <button
                type="button"
                class="link-button"
                @click="() => toggleRecoveryMode(clearErrors)"
            >
                {{ authConfigContent.buttonText }}
            </button>
        </Form>
    </div>
</template>
