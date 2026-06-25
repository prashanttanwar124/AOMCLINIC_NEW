<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import { KeyRound } from '@lucide/vue';
import InputError from '@/components/InputError.vue';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
};

const props = defineProps<Props>();

const { verify, isLoading, error, isSupported } = usePasskeyVerify({
    ...(props.routes
        ? {
              routes: {
                  options: props.routes.options.url,
                  submit: props.routes.submit.url,
              },
          }
        : {}),
    onSuccess: (response) => {
        router.visit(response.redirect ?? '/dashboard');
    },
});
</script>

<template>
    <div v-if="isSupported" class="section-stack">
        <Button
            type="button"
            :label="
                isLoading
                    ? (props.loadingLabel ?? 'Authenticating...')
                    : (props.label ?? 'Sign in with a passkey')
            "
            :loading="isLoading"
            severity="secondary"
            fluid
            @click="verify"
        >
            <template #icon>
                <KeyRound v-if="!isLoading" style="width: 1rem; height: 1rem" />
            </template>
        </Button>

        <InputError v-if="error" :message="error" />

        <Divider align="center">
            {{ props.separator ?? 'Or continue with email' }}
        </Divider>
    </div>
</template>
