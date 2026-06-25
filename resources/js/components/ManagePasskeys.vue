<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import Heading from '@/components/Heading.vue';
import PasskeyItem from '@/components/PasskeyItem.vue';
import PasskeyRegister from '@/components/PasskeyRegister.vue';
import type { Passkey } from '@/types/auth';

withDefaults(
    defineProps<{
        canManagePasskeys?: boolean;
        passkeys?: Passkey[];
    }>(),
    {
        canManagePasskeys: false,
        passkeys: () => [],
    },
);

const handleDelete = (id: number, onError: () => void) => {
    router.delete(destroy.url(id), {
        preserveScroll: true,
        onError,
    });
};

const handleRegisterSuccess = () => {
    router.reload();
};
</script>

<template>
    <div v-if="canManagePasskeys" class="page-card section-stack">
        <Heading
            variant="small"
            title="Passkeys"
            description="Manage your passkeys for passwordless sign-in"
        />

        <div class="page-card" style="padding: 0">
            <template v-if="passkeys.length">
                <PasskeyItem
                    v-for="passkey in passkeys"
                    :key="passkey.id"
                    :passkey="passkey"
                    @remove="handleDelete"
                />
            </template>

            <div v-else class="empty-panel">
                <div class="empty-panel-icon">
                    <KeyRound style="width: 1.5rem; height: 1.5rem" />
                </div>
                <p><strong>No passkeys yet</strong></p>
                <p class="panel-subtitle">
                    Add a passkey to sign in without a password.
                </p>
            </div>
        </div>

        <PasskeyRegister @success="handleRegisterSuccess" />
    </div>
</template>
