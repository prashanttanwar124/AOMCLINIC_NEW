<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import { useToast } from 'primevue/usetoast';
import { computed, reactive } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import http, { extractErrors, pushToast } from '@/lib/http';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.staff!);
const toast = useToast();

const form = reactive({
    name: user.value.name,
    email: user.value.email,
    errors: {} as Record<string, string>,
    processing: false,
});

const submit = async (): Promise<void> => {
    form.processing = true;
    form.errors = {};

    try {
        const { data } = await http.patch(ProfileController.update.url(), {
            name: form.name,
            email: form.email,
        });

        pushToast(toast, data.toast);
        router.reload();
    } catch (error) {
        form.errors = extractErrors(error);
    } finally {
        form.processing = false;
    }
};
</script>

<template>
    <Head title="Profile settings" />

    <div class="section-stack">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <form class="page-card auth-form" @submit.prevent="submit">
            <div class="field">
                <label for="name">Name</label>
                <InputText
                    id="name"
                    v-model="form.name"
                    name="name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                    fluid
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="field">
                <label for="email">Email address</label>
                <InputText
                    id="email"
                    v-model="form.email"
                    type="email"
                    name="email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                    fluid
                />
                <InputError :message="form.errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <Message severity="warn">
                    Your email address is unverified.
                    <Link :href="send()" as="button" class="link-button">
                        Click here to re-send the verification email.
                    </Link>
                </Message>

                <Message
                    v-if="page.props.status === 'verification-link-sent'"
                    severity="success"
                >
                    A new verification link has been sent to your email address.
                </Message>
            </div>

            <Button
                type="submit"
                label="Save"
                :loading="form.processing"
                data-test="update-profile-button"
            />
        </form>
    </div>

    <DeleteUser />
</template>
