<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Checkbox from 'primevue/checkbox';
import { computed, ref, watch } from 'vue';
import { dashboard } from '@/routes';
import { users } from '@/routes/admin';

defineOptions({
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'User Management', href: users().url },
        ],
    },
});

type PaginatedUsers = {
    data: Array<{
        id: number;
        name: string;
        email: string;
        roles: string[];
        created_at: string;
    }>;
    links: any[];
    total: number;
};

type RoleItem = {
    id: number;
    name: string;
};

const props = defineProps<{
    users: PaginatedUsers;
    roles: RoleItem[];
    filters: {
        search: string | null;
    };
}>();

const searchQuery = ref(props.filters.search ?? '');
const userDialog = ref(false);
const deleteUserDialog = ref(false);
const isEditMode = ref(false);
const editingUserId = ref<number | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    roles: [] as string[],
});

const userToDelete = ref<{ id: number; name: string } | null>(null);

function openCreateModal() {
    isEditMode.value = false;
    editingUserId.value = null;
    form.reset();
    form.clearErrors();
    userDialog.value = true;
}

function openEditModal(user: any) {
    isEditMode.value = true;
    editingUserId.value = user.id;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.roles = [...user.roles];
    userDialog.value = true;
}

function openDeleteModal(user: any) {
    userToDelete.value = user;
    deleteUserDialog.value = true;
}

function handleSearch() {
    form.get(users().url, {
        data: { search: searchQuery.value },
        preserveState: true,
        preserveScroll: true,
    });
}

function handleClearSearch() {
    searchQuery.value = '';
    handleSearch();
}

function submitForm() {
    if (isEditMode.value && editingUserId.value) {
        form.patch(`${users().url}/${editingUserId.value}`, {
            onSuccess: () => {
                userDialog.value = false;
                form.reset();
            },
        });
    } else {
        form.post(users().url, {
            onSuccess: () => {
                userDialog.value = false;
                form.reset();
            },
        });
    }
}

function confirmDelete() {
    if (userToDelete.value) {
        form.delete(`${users().url}/${userToDelete.value.id}`, {
            onSuccess: () => {
                deleteUserDialog.value = false;
                userToDelete.value = null;
            },
        });
    }
}

function getRoleSeverity(role: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' {
    if (role === 'admin') return 'danger';
    if (role === 'staff') return 'info';
    return 'secondary';
}
</script>

<template>
    <Head title="User Management" />

    <div class="page-grid user-management-page">
        <!-- Header Panel -->
        <section class="admin-hero page-card">
            <div class="admin-hero__copy">
                <p class="stat-label">System Administration</p>
                <h2 class="admin-hero__title">User Management</h2>
                <p class="panel-subtitle">
                    Manage system administrators, front desk staff, and custom roles access control.
                </p>
            </div>
            <div class="admin-hero__actions">
                <Button
                    label="Create User"
                    icon="pi pi-user-plus"
                    class="admin-cta admin-cta--primary"
                    @click="openCreateModal"
                />
            </div>
        </section>

        <!-- Main Workspace Pane -->
        <article class="page-card admin-table-card">
            <div class="admin-toolbar">
                <div class="admin-toolbar__copy">
                    <h3 class="panel-title">System Users</h3>
                    <p class="panel-subtitle">Total accounts: {{ props.users.total }}</p>
                </div>

                <div class="admin-search">
                    <InputText
                        v-model="searchQuery"
                        placeholder="Search by name or email..."
                        class="admin-search__input"
                        @keyup.enter="handleSearch"
                    />
                    <Button
                        v-if="searchQuery"
                        icon="pi pi-times"
                        severity="secondary"
                        outlined
                        size="small"
                        @click="handleClearSearch"
                    />
                    <Button
                        icon="pi pi-search"
                        severity="primary"
                        class="ml-2"
                        @click="handleSearch"
                    />
                </div>
            </div>

            <!-- Users Table -->
            <DataTable
                :value="props.users.data"
                class="admin-table"
                responsiveLayout="scroll"
            >
                <Column field="name" header="Name" sortable>
                    <template #body="{ data }">
                        <span class="font-semibold text-color">{{ data.name }}</span>
                    </template>
                </Column>
                <Column field="email" header="Email" sortable></Column>
                <Column header="Assigned Roles">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-1">
                            <Tag
                                v-for="role in data.roles"
                                :key="role"
                                :value="role"
                                :severity="getRoleSeverity(role)"
                                rounded
                            />
                            <span v-if="!data.roles.length" class="text-xs text-secondary-color italic">No roles assigned</span>
                        </div>
                    </template>
                </Column>
                <Column field="created_at" header="Joined Date" sortable></Column>
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

            <!-- Table Pagination -->
            <div class="pagination-container flex justify-between items-center p-4">
                <span class="text-xs text-secondary-color">Showing {{ props.users.data.length }} of {{ props.users.total }} users</span>
                <div class="flex gap-2">
                    <Link
                        v-for="link in props.users.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-3 py-1 text-xs border rounded-md transition"
                        :class="{
                            'bg-primary-500 text-white border-primary-500': link.active,
                            'opacity-50 pointer-events-none': !link.url,
                            'hover:bg-surface-hover text-color border-surface-border': !link.active && link.url
                        }"
                    >
                        <span v-html="link.label"></span>
                    </Link>
                </div>
            </div>
        </article>

        <!-- Create/Edit User Dialog -->
        <Dialog
            v-slot="userDialog"
            v-model:visible="userDialog"
            :header="isEditMode ? 'Edit Staff Account' : 'New Staff Account'"
            :modal="true"
            class="admin-dialog"
            :style="{ width: '450px' }"
        >
            <form @submit.prevent="submitForm" class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-2">
                    <label for="name" class="font-semibold text-sm">Full Name</label>
                    <InputText
                        id="name"
                        v-model="form.name"
                        required
                        autofocus
                        class="w-full"
                        :class="{ 'p-invalid': form.errors.name }"
                    />
                    <small v-if="form.errors.name" class="p-error">{{ form.errors.name }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="email" class="font-semibold text-sm">Email Address</label>
                    <InputText
                        id="email"
                        type="email"
                        v-model="form.email"
                        required
                        class="w-full"
                        :class="{ 'p-invalid': form.errors.email }"
                    />
                    <small v-if="form.errors.email" class="p-error">{{ form.errors.email }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="password" class="font-semibold text-sm">
                        Password <span v-if="isEditMode" class="text-xs text-secondary-color font-normal">(Leave blank to keep current)</span>
                    </label>
                    <InputText
                        id="password"
                        type="password"
                        v-model="form.password"
                        :required="!isEditMode"
                        class="w-full"
                        :class="{ 'p-invalid': form.errors.password }"
                    />
                    <small v-if="form.errors.password" class="p-error">{{ form.errors.password }}</small>
                </div>

                <div class="flex flex-col gap-2 mt-2">
                    <label class="font-semibold text-sm mb-1">Assign Access Roles</label>
                    <div class="flex flex-col gap-2 border border-surface-border p-3 rounded-lg bg-surface-hover">
                        <div
                            v-for="role in props.roles"
                            :key="role.id"
                            class="flex items-center gap-2 py-1"
                        >
                            <Checkbox
                                :id="`role-${role.id}`"
                                v-model="form.roles"
                                :value="role.name"
                            />
                            <label :for="`role-${role.id}`" class="text-sm font-medium cursor-pointer capitalize">
                                {{ role.name }}
                            </label>
                        </div>
                        <small v-if="form.errors.roles" class="p-error mt-1">{{ form.errors.roles }}</small>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        text
                        @click="userDialog = false"
                        :disabled="form.processing"
                    />
                    <Button
                        type="submit"
                        :label="isEditMode ? 'Save Changes' : 'Create Account'"
                        class="p-button-primary"
                        :loading="form.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog
            v-slot="deleteUserDialog"
            v-model:visible="deleteUserDialog"
            header="Confirm Account Deletion"
            :modal="true"
            class="admin-dialog"
            :style="{ width: '400px' }"
        >
            <div class="flex flex-col gap-4 py-2">
                <div class="flex items-center gap-3">
                    <i class="pi pi-exclamation-triangle text-amber-500 text-3xl"></i>
                    <div>
                        <p class="text-sm font-semibold">Are you sure you want to delete this account?</p>
                        <p class="text-xs text-secondary-color mt-1">
                            This will permanently remove the account of <strong>{{ userToDelete?.name }}</strong>. This action cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-2">
                    <Button
                        label="No, Keep It"
                        severity="secondary"
                        text
                        @click="deleteUserDialog = false"
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
.user-management-page {
    gap: 1.5rem;
}

.pagination-container {
    border-top: 1px solid var(--surface-border);
}
</style>
