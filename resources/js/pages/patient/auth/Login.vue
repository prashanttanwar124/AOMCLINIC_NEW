<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import { store } from '@/actions/App/Http/Controllers/Patient/Auth/AuthenticatedPatientSessionController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { liveStatus } from '@/routes/patient';

defineOptions({
    layout: {
        title: 'Patient Login',
        description: 'Enter your email and password to access your patient portal',
    },
});
</script>

<template>
    <Head title="Patient Login" />

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
                    placeholder="patient@example.com"
                    fluid
                    :invalid="!!errors.email"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="field">
                <label for="password">Password</label>
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
                data-test="patient-login-button"
            />
        </div>

        <div class="form-footer">
            <TextLink :href="liveStatus()">View Live Queue Status</TextLink>
        </div>
    </Form>
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

.form-footer {
    padding-top: 0.25rem;
    font-size: 0.86rem;
}
</style>
