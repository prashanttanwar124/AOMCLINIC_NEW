<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import { store } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { register } from '@/routes';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div v-if="status" class="inline-status">
        <InputError :message="status" />
    </div>

    <PasskeyVerify />

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
                />
                <InputError :message="errors.email" />
            </div>

            <div class="field">
                <div class="field-row">
                    <label for="password">Password</label>
                    <TextLink v-if="canResetPassword" :href="request()">
                        Forgot your password?
                    </TextLink>
                </div>
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

            <Button
                type="submit"
                label="Log in"
                :loading="processing"
                fluid
                data-test="login-button"
            />
        </div>

        <div class="form-footer">
            Don't have an account?
            <TextLink :href="register()">Sign up</TextLink>
        </div>
    </Form>
</template>

<style scoped>
.remember-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
</style>
