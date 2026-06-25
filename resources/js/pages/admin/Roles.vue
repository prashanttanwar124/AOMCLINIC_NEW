<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Checkbox from 'primevue/checkbox';
import { ref } from 'vue';
import { dashboard } from '@/routes';
import { roles } from '@/routes/admin';

defineOptions({
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Roles & Permissions', href: roles().url },
        ],
    },
});

type RoleItem = {
    id: number;
    name: string;
    permissions: string[];
    created_at: string;
};

type PermissionItem = {
    id: number;
    name: string;
};

const props = defineProps<{
    roles: RoleItem[];
    permissions: PermissionItem[];
}>();

const roleDialog = ref(false);
const deleteRoleDialog = ref(false);
const isEditMode = ref(false);
const editingRoleId = ref<number | null>(null);

const form = useForm({
    name: '',
    permissions: [] as string[],
});

const roleToDelete = ref<RoleItem | null>(null);

function openCreateModal() {
    isEditMode.value = false;
    editingRoleId.value = null;
    form.reset();
    form.clearErrors();
    roleDialog.value = true;
}

function openEditModal(role: RoleItem) {
    isEditMode.value = true;
    editingRoleId.value = role.id;
    form.clearErrors();
    form.name = role.name;
    form.permissions = [...role.permissions];
    roleDialog.value = true;
}

function openDeleteModal(role: RoleItem) {
    roleToDelete.value = role;
    deleteRoleDialog.value = true;
}
function submitForm() {
    if (isEditMode.value && editingRoleId.value) {
        form.patch(`${roles().url}/${editingRoleId.value}`, {
            onSuccess: () => {
                roleDialog.value = false;
                form.reset();
            },
        });
    } else {
        form.post(roles().url, {
            onSuccess: () => {
                roleDialog.value = false;
                form.reset();
            },
        });
    }
}

function confirmDelete() {
    if (roleToDelete.value) {
        form.delete(`${roles().url}/${roleToDelete.value.id}`, {
            onSuccess: () => {
                deleteRoleDialog.value = false;
                roleToDelete.value = null;
            },
        });
    }
}

function isSystemRole(name: string): boolean {
    return ['admin', 'staff'].includes(name.toLowerCase());
}

function getPermissionSeverity(perm: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' {
    if (perm === 'manage staff') return 'danger';
    if (perm === 'manage patients') return 'info';
    return 'secondary';
}
</script>

<template>
    <Head title="Roles & Permissions" />

    <div class="page-grid roles-permissions-page">
        <!-- Header Panel -->
        <section class="admin-hero page-card">
            <div class="admin-hero__copy">
                <p class="stat-label">System Administration</p>
                <h2 class="admin-hero__title">Roles & Permissions</h2>
                <p class="panel-subtitle">
                    Configure Spatie roles and security clearance permissions mapped to staff accounts.
                </p>
            </div>
            <div class="admin-hero__actions">
                <Button
                    label="Create Role"
                    icon="pi pi-key"
                    class="admin-cta admin-cta--primary"
                    @click="openCreateModal"
                />
            </div>
        </section>

        <!-- Roles Table Panel -->
        <article class="page-card admin-table-card">
            <div class="admin-toolbar">
                <div class="admin-toolbar__copy">
                    <h3 class="panel-title">Defined Access Roles</h3>
                    <p class="panel-subtitle">Access scopes and mapped system permissions.</p>
                </div>
            </div>

            <DataTable :value="props.roles" class="admin-table" responsiveLayout="scroll">
                <Column field="name" header="Role Name" sortable>
                    <template #body="{ data }">
                        <span class="font-bold text-color capitalize">{{ data.name }}</span>
                        <Tag
                            v-if="isSystemRole(data.name)"
                            value="System"
                            severity="contrast"
                            class="ml-2 font-normal text-xxs px-1"
                            rounded
                        />
                    </template>
                </Column>
                <Column header="Authorized Permissions">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-1 py-1">
                            <Tag
                                v-for="perm in data.permissions"
                                :key="perm"
                                :value="perm"
                                :severity="getPermissionSeverity(perm)"
                                rounded
                                class="text-xs font-semibold"
                            />
                            <span v-if="!data.permissions.length" class="text-xs text-secondary-color italic">No permissions assigned</span>
                        </div>
                    </template>
                </Column>
                <Column field="created_at" header="Created Date" sortable></Column>
                <Column header="Actions" class="text-right">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <Button
                                icon="pi pi-pencil"
                                severity="secondary"
                                text
                                size="small"
                                @click="openEditModal(data)"
                            />
                            <Button
                                v-if="!isSystemRole(data.name)"
                                icon="pi pi-trash"
                                severity="danger"
                                text
                                size="small"
                                @click="openDeleteModal(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </article>

        <!-- Create/Edit Role Dialog -->
        <Dialog
            v-slot="roleDialog"
            v-model:visible="roleDialog"
            :header="isEditMode ? 'Edit Access Role' : 'New Access Role'"
            :modal="true"
            class="admin-dialog"
            :style="{ width: '450px' }"
        >
            <form @submit.prevent="submitForm" class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-2">
                    <label for="name" class="font-semibold text-sm">Role Name</label>
                    <InputText
                        id="name"
                        v-model="form.name"
                        required
                        autofocus
                        class="w-full"
                        :class="{ 'p-invalid': form.errors.name }"
                        :disabled="isEditMode && isSystemRole(form.name)"
                    />
                    <small v-if="form.errors.name" class="p-error">{{ form.errors.name }}</small>
                </div>

                <div class="flex flex-col gap-2 mt-2">
                    <label class="font-semibold text-sm mb-1">Grant System Permissions</label>
                    <div class="flex flex-col gap-2 border border-surface-border p-3 rounded-lg bg-surface-hover">
                        <div
                            v-for="perm in props.permissions"
                            :key="perm.id"
                            class="flex items-center gap-2 py-1"
                        >
                            <Checkbox
                                :id="`perm-${perm.id}`"
                                v-model="form.permissions"
                                :value="perm.name"
                            />
                            <label :for="`perm-${perm.id}`" class="text-sm font-medium cursor-pointer capitalize">
                                {{ perm.name }}
                            </label>
                        </div>
                        <small v-if="form.errors.permissions" class="p-error mt-1">{{ form.errors.permissions }}</small>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        text
                        @click="roleDialog = false"
                        :disabled="form.processing"
                    />
                    <Button
                        type="submit"
                        :label="isEditMode ? 'Save Changes' : 'Create Role'"
                        class="p-button-primary"
                        :loading="form.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog
            v-slot="deleteRoleDialog"
            v-model:visible="deleteRoleDialog"
            header="Confirm Role Deletion"
            :modal="true"
            class="admin-dialog"
            :style="{ width: '400px' }"
        >
            <div class="flex flex-col gap-4 py-2">
                <div class="flex items-center gap-3">
                    <i class="pi pi-exclamation-triangle text-amber-500 text-3xl"></i>
                    <div>
                        <p class="text-sm font-semibold">Are you sure you want to delete this role?</p>
                        <p class="text-xs text-secondary-color mt-1">
                            This will permanently remove the role <strong>{{ roleToDelete?.name }}</strong>. Users assigned this role will lose their mapped permissions. This action cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-2">
                    <Button
                        label="No, Keep It"
                        severity="secondary"
                        text
                        @click="deleteRoleDialog = false"
                        :disabled="form.processing"
                    />
                    <Button
                        label="Yes, Delete"
                        severity="danger"
                        class="p-button-danger"
                        @click="confirmDelete"
                        :loading="form.processing"
                    />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.roles-permissions-page {
    gap: 1.5rem;
}
</style>
