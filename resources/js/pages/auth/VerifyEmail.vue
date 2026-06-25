<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Message from 'primevue/message';
import TextLink from '@/components/TextLink.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Email verification',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <Message v-if="status === 'verification-link-sent'" severity="success">
        A new verification link has been sent to the email address you provided
        during registration.
    </Message>

    <Form v-bind="send.form()" class="section-stack" v-slot="{ processing }">
        <Button
            label="Resend verification email"
            :loading="processing"
            severity="secondary"
        />

        <div class="form-footer">
            <TextLink :href="logout()" as="button">Log out</TextLink>
        </div>
    </Form>
</template>
