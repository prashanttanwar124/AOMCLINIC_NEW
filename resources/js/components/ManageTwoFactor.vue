<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { onUnmounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { disable, enable } from '@/routes/two-factor';

withDefaults(
    defineProps<{
        canManageTwoFactor?: boolean;
        requiresConfirmation?: boolean;
        twoFactorEnabled?: boolean;
    }>(),
    {
        canManageTwoFactor: false,
        requiresConfirmation: false,
        twoFactorEnabled: false,
    },
);

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <div v-if="canManageTwoFactor" class="page-card section-stack">
        <Heading
            variant="small"
            title="Two-factor authentication"
            description="Manage your two-factor authentication settings"
        />

        <p class="panel-subtitle">
            When enabled, you will be prompted for a secure pin from your
            authenticator app during login.
        </p>

        <div>
            <Button
                v-if="hasSetupData && !twoFactorEnabled"
                label="Continue setup"
                @click="showSetupModal = true"
            />
            <Form
                v-else-if="!twoFactorEnabled"
                v-bind="enable.form()"
                @success="showSetupModal = true"
                #default="{ processing }"
            >
                <Button label="Enable 2FA" :loading="processing" />
            </Form>
            <Form v-else v-bind="disable.form()" #default="{ processing }">
                <Button
                    label="Disable 2FA"
                    severity="danger"
                    :loading="processing"
                />
            </Form>
        </div>

        <TwoFactorRecoveryCodes v-if="twoFactorEnabled" />

        <TwoFactorSetupModal
            v-model:isOpen="showSetupModal"
            :requiresConfirmation="requiresConfirmation"
            :twoFactorEnabled="twoFactorEnabled"
        />
    </div>
</template>
