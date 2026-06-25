<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Enter your email to receive a password reset link',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <Message v-if="status" severity="success">{{ status }}</Message>

    <div class="section-stack">
        <Form
            v-bind="email.form()"
            v-slot="{ errors, processing }"
            class="auth-form"
        >
            <div class="field">
                <label for="email">Email address</label>
                <InputText
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="email@example.com"
                    fluid
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                label="Email password reset link"
                :loading="processing"
                fluid
                data-test="email-password-reset-link-button"
            />
        </Form>

        <div class="form-footer">
            Or, return to
            <TextLink :href="login()">log in</TextLink>
        </div>
    </div>
</template>
