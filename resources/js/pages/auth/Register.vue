<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { store } from '@/actions/Laravel/Fortify/Http/Controllers/RegisteredUserController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="auth-form"
    >
        <div class="form-grid">
            <div class="field">
                <label for="name">Name</label>
                <InputText
                    id="name"
                    type="text"
                    name="name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Full name"
                    fluid
                />
                <InputError :message="errors.name" />
            </div>

            <div class="field">
                <label for="email">Email address</label>
                <InputText
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="email@example.com"
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
                    autocomplete="new-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm password"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                label="Create account"
                :loading="processing"
                fluid
                data-test="register-user-button"
            />
        </div>

        <div class="form-footer">
            Already have an account?
            <TextLink :href="login()">Log in</TextLink>
        </div>
    </Form>
</template>
