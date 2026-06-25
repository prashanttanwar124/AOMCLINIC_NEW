<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Reset password',
        description: 'Please enter your new password below',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Reset password" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="auth-form"
    >
        <div class="field">
            <label for="email">Email</label>
            <InputText
                id="email"
                v-model="inputEmail"
                type="email"
                name="email"
                autocomplete="email"
                readonly
                fluid
            />
            <InputError :message="errors.email" />
        </div>

        <div class="field">
            <label for="password">Password</label>
            <PasswordInput
                id="password"
                name="password"
                autocomplete="new-password"
                autofocus
                placeholder="Password"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                placeholder="Confirm password"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <Button
            type="submit"
            label="Reset password"
            :loading="processing"
            fluid
            data-test="reset-password-button"
        />
    </Form>
</template>
