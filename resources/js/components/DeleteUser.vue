<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';

const dialogVisible = ref(false);
</script>

<template>
    <div class="page-card section-stack">
        <Heading
            variant="small"
            title="Delete account"
            description="Delete your account and all of its resources"
        />

        <div class="danger-panel section-stack">
            <div>
                <p style="margin: 0; font-weight: 700">Warning</p>
                <p class="panel-subtitle">
                    Please proceed with caution, this cannot be undone.
                </p>
            </div>

            <Button
                label="Delete account"
                severity="danger"
                data-test="delete-user-button"
                @click="dialogVisible = true"
            />
        </div>

        <Dialog v-model:visible="dialogVisible" modal header="Delete account">
            <Form
                v-bind="ProfileController.destroy.form()"
                reset-on-success
                :options="{ preserveScroll: true }"
                class="auth-form"
                v-slot="{ errors, processing, reset, clearErrors }"
            >
                <p class="panel-subtitle">
                    Once your account is deleted, all of its resources and data
                    will also be permanently deleted. Enter your password to
                    confirm.
                </p>

                <div class="field">
                    <label for="delete-password">Password</label>
                    <PasswordInput
                        id="delete-password"
                        name="password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div style="display: flex; gap: 0.75rem">
                    <Button
                        type="button"
                        label="Cancel"
                        text
                        @click="
                            dialogVisible = false;
                            clearErrors();
                            reset();
                        "
                    />
                    <Button
                        type="submit"
                        label="Delete account"
                        severity="danger"
                        :loading="processing"
                        data-test="confirm-delete-user-button"
                    />
                </div>
            </Form>
        </Dialog>
    </div>
</template>
