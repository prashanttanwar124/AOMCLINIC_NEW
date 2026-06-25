<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { reactive } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import http, { extractErrors, pushToast } from '@/lib/http';
import { edit } from '@/routes/security';
import type { Passkey } from '@/types/auth';

defineProps<{
    passwordRules: string;
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    },
});

const toast = useToast();

const form = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
    errors: {} as Record<string, string>,
    processing: false,
});

const submit = async (): Promise<void> => {
    form.processing = true;
    form.errors = {};

    try {
        const { data } = await http.put(SecurityController.update.url(), {
            current_password: form.current_password,
            password: form.password,
            password_confirmation: form.password_confirmation,
        });

        pushToast(toast, data.toast);
        form.current_password = '';
        form.password = '';
        form.password_confirmation = '';
    } catch (error) {
        form.errors = extractErrors(error);
        form.password = '';
        form.password_confirmation = '';
        form.current_password = '';
    } finally {
        form.processing = false;
    }
};
</script>

<template>
    <Head title="Security settings" />

    <div class="page-card section-stack">
        <Heading
            variant="small"
            title="Update password"
            description="Ensure your account is using a long, random password to stay secure"
        />

        <form class="auth-form" @submit.prevent="submit">
            <div class="field">
                <label for="current_password">Current password</label>
                <PasswordInput
                    id="current_password"
                    v-model="form.current_password"
                    name="current_password"
                    autocomplete="current-password"
                    placeholder="Current password"
                />
                <InputError :message="form.errors.current_password" />
            </div>

            <div class="field">
                <label for="password">New password</label>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    name="password"
                    autocomplete="new-password"
                    placeholder="New password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <PasswordInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    placeholder="Confirm password"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                label="Save"
                :loading="form.processing"
                data-test="update-password-button"
            />
        </form>
    </div>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />
</template>
