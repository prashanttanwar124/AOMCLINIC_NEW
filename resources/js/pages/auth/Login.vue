<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import { store } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Staff Sign In',
        description: 'Enter your email and password to access the clinic workspace',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Staff Login" />

    <div v-if="status" class="inline-status">
        <Message severity="success" :closable="false">{{ status }}</Message>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="auth-form"
    >
        <div class="form-grid">
            <div class="field">
                <label for="email">Email address</label>
                <InputText
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                    fluid
                    :invalid="!!errors.email"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="field">
                <div class="field-row">
                    <label for="password">Password</label>
                    <TextLink v-if="canResetPassword" :href="request()">
                        Forgot password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Password"
                    :invalid="!!errors.password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="remember-row">
                <Checkbox input-id="remember" name="remember" binary />
                <label for="remember">Remember me</label>
            </div>

            <Button
                type="submit"
                label="Log in"
                icon="pi pi-sign-in"
                :loading="processing"
                fluid
                data-test="login-button"
            />
        </div>
    </Form>

    <PasskeyVerify />
</template>

<style scoped>
.remember-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.88rem;
    color: var(--text-secondary-color);
}

.remember-row label {
    cursor: pointer;
    user-select: none;
}
</style>
