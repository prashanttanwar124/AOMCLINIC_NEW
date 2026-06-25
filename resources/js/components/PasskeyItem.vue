<script setup lang="ts">
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { KeyRound } from '@lucide/vue';
import { ref } from 'vue';
import type { Passkey } from '@/types/auth';

const props = defineProps<{
    passkey: Passkey;
}>();

const emit = defineEmits<{
    remove: [id: number, onError: () => void];
}>();

const isDeleting = ref(false);
const dialogVisible = ref(false);

const handleDelete = () => {
    isDeleting.value = true;
    emit('remove', props.passkey.id, () => {
        isDeleting.value = false;
    });
};
</script>

<template>
    <div
        style="
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--surface-border);
        "
    >
        <div style="display: flex; align-items: center; gap: 1rem">
            <div class="empty-panel-icon" style="margin: 0">
                <KeyRound style="width: 1.25rem; height: 1.25rem" />
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem">
                    <strong>{{ passkey.name }}</strong>
                    <small
                        v-if="passkey.authenticator"
                        style="color: var(--text-secondary-color)"
                    >
                        {{ passkey.authenticator }}
                    </small>
                </div>
                <small style="color: var(--text-secondary-color)">
                    Added {{ passkey.created_at_diff }}
                    <template v-if="passkey.last_used_at_diff">
                        / Last used {{ passkey.last_used_at_diff }}
                    </template>
                </small>
            </div>
        </div>

        <Button
            icon="pi pi-trash"
            text
            severity="danger"
            @click="dialogVisible = true"
        />

        <Dialog v-model:visible="dialogVisible" modal header="Remove passkey">
            <p>
                Are you sure you want to remove the "{{ passkey.name }}"
                passkey?
            </p>
            <template #footer>
                <Button label="Cancel" text @click="dialogVisible = false" />
                <Button
                    label="Remove passkey"
                    severity="danger"
                    :loading="isDeleting"
                    @click="handleDelete"
                />
            </template>
        </Dialog>
    </div>
</template>
