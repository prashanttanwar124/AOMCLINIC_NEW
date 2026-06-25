<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import { store } from '@/actions/App/Http/Controllers/Patient/Auth/AuthenticatedPatientSessionController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { register, liveStatus } from '@/routes/patient';

defineOptions({
    layout: {
        title: 'Patient login',
        description: 'Sign in to access your patient portal',
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
                />
                <InputError :message="errors.password" />
            </div>

            <div class="remember-row">
                <Checkbox input-id="remember" name="remember" binary />
                <label for="remember">Remember me</label>
            </div>

            <Button type="submit" label="Log in" :loading="processing" fluid />
        </div>

        <div class="form-footer form-footer--stacked">
            <div>
                Need a patient account?
                <TextLink :href="register()">Register here</TextLink>
            </div>
            <div class="form-footer__hint">
                Or check the <TextLink :href="liveStatus()">Live Queue Status</TextLink> without logging in.
            </div>
        </div>
    </Form>
</template>

<style scoped>
.remember-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.form-footer--stacked {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
}

.form-footer__hint {
    font-size: 0.85rem;
    color: var(--text-secondary-color);
}
</style>
