<script setup lang="ts">
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';

const emit = defineEmits<{
    success: [];
}>();

const getDefaultPasskeyName = () => {
    const ua = navigator.userAgent;
    const browser = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'].find(
        (item) => new RegExp(item).test(ua),
    );
    const os = ['iPhone', 'iPad', 'Android', 'Mac', 'Windows'].find((item) =>
        new RegExp(item).test(ua),
    );

    return [browser, os].filter(Boolean).join(' on ') || '';
};

const name = ref(getDefaultPasskeyName());
const showForm = ref(false);

const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
        name.value = '';
        showForm.value = false;
        emit('success');
    },
});

const handleSubmit = async (event: Event) => {
    event.preventDefault();

    if (!name.value.trim()) {
        return;
    }

    await register(name.value);
};
</script>

<template>
    <Message v-if="!isSupported" severity="warn">
        Passkeys are not supported in this browser.
    </Message>

    <Button
        v-else-if="!showForm"
        label="Add passkey"
        severity="secondary"
        outlined
        @click="showForm = true"
    />

    <form v-else class="page-card auth-form" @submit="handleSubmit">
        <div class="field">
            <label for="passkey-name">Passkey name</label>
            <InputText
                id="passkey-name"
                v-model="name"
                type="text"
                placeholder="e.g., MacBook Pro, iPhone"
                fluid
                autofocus
            />
            <small style="color: var(--text-secondary-color)">
                A name helps you identify this passkey later.
            </small>
        </div>

        <InputError v-if="error" :message="error" />

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap">
            <Button
                type="submit"
                :label="isLoading ? 'Registering...' : 'Register passkey'"
                :loading="isLoading"
                :disabled="!name.trim()"
            />
            <Button
                type="button"
                label="Cancel"
                text
                @click="showForm = false"
            />
        </div>
    </form>
</template>
