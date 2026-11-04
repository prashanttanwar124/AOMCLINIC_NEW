<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { dashboard } from '@/routes';
import { roles as rolesRoute, users } from '@/routes/admin';

defineOptions({
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'User Management', href: users().url },
        ],
    },
});

type UserItem = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    created_at: string;
};

type PaginatedUsers = {
    data: UserItem[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    total: number;
    from?: number | null;
    to?: number | null;
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

const canTeleportActions = ref(false);
onMounted(() => {
    canTeleportActions.value = !!document.getElementById('admin-header-actions');
});

const searchQuery = ref(props.filters.search ?? '');
const roleFilter = ref<string>('all');
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

const filteredUsers = computed(() => {
    if (roleFilter.value === 'all') {
        return props.users.data;
    }
    if (roleFilter.value === 'unassigned') {
        return props.users.data.filter((u) => !u.roles || u.roles.length === 0);
    }
    return props.users.data.filter((u) => u.roles.includes(roleFilter.value));
});

const summaryStats = computed(() => {
    const rows = props.users.data;
    return {
        total: props.users.total,
        admins: rows.filter((u) => u.roles.includes('admin')).length,
        staff: rows.filter((u) => u.roles.includes('staff')).length,
        rolesDefined: props.roles.length,
    };
});

function getInitials(name: string): string {
    return (
        name
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || 'US'
    );
}

function openCreateModal(): void {
    isEditMode.value = false;
    editingUserId.value = null;
    form.reset();
    form.clearErrors();
    userDialog.value = true;
}

function openEditModal(user: UserItem): void {
    isEditMode.value = true;
    editingUserId.value = user.id;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.roles = [...user.roles];
    userDialog.value = true;
}

function openDeleteModal(user: UserItem): void {
    userToDelete.value = user;
    deleteUserDialog.value = true;
}

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

function handleSearch(): void {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = null;
    }

    router.get(
        users().url,
        { search: searchQuery.value.trim() || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch(searchQuery, (val) => {
    if (val.trim() === (props.filters.search ?? '').trim()) {
        return;
    }
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    searchDebounceTimer = setTimeout(() => {
        handleSearch();
    }, 300);
});

onBeforeUnmount(() => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});

function handleClearSearch(): void {
    searchQuery.value = '';
    roleFilter.value = 'all';
    handleSearch();
}

function goToPage(url: string | null): void {
    if (!url) {
        return;
    }
    router.get(
        url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function submitForm(): void {
    if (isEditMode.value && editingUserId.value) {
        form.patch(`${users().url}/${editingUserId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                userDialog.value = false;
                form.reset();
            },
        });
    } else {
        form.post(users().url, {
            preserveScroll: true,
            onSuccess: () => {
                userDialog.value = false;
                form.reset();
            },
        });
    }
}

function confirmDelete(): void {
    if (userToDelete.value) {
        form.delete(`${users().url}/${userToDelete.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                deleteUserDialog.value = false;
                userToDelete.value = null;
            },
        });
    }
}

function getRoleSeverity(
    role: string,
): 'success' | 'info' | 'warn' | 'danger' | 'secondary' {
    if (role === 'admin') {
        return 'danger';
    }
    if (role === 'staff') {
        return 'info';
    }
    return 'secondary';
}
</script>

<template>
    <Head title="User Management" />

    <div class="page-grid user-management-workspace">
        <!-- Teleport Primary Actions into Header -->
        <Teleport v-if="canTeleportActions" to="#admin-header-actions">
            <div class="header-action-group">
                <Button
                    label="Manage Roles"
                    icon="pi pi-key"
                    severity="secondary"
                    outlined
                    size="small"
                    class="btn-auto"
                    @click="router.visit(rolesRoute().url)"
                />
                <Button
                    label="Create User"
                    icon="pi pi-user-plus"
                    severity="primary"
                    size="small"
                    class="btn-auto"
                    @click="openCreateModal"
                />
            </div>
        </Teleport>

        <!-- Interactive KPI Summary Strip -->
        <section class="summary-grid" aria-label="Users overview">
            <button
                type="button"
                class="summary-card"
                :class="{ 'is-active': roleFilter === 'all' }"
                @click="roleFilter = 'all'"
            >
                <div class="summary-card__icon summary-card__icon--blue">
                    <i class="pi pi-users"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Total Accounts</span>
                    <strong class="summary-card__value">{{
                        summaryStats.total
                    }}</strong>
                    <span class="summary-card__sub"
                        >Registered clinic users</span
                    >
                </div>
            </button>

            <button
                type="button"
                class="summary-card"
                :class="{ 'is-active': roleFilter === 'admin' }"
                @click="roleFilter = roleFilter === 'admin' ? 'all' : 'admin'"
            >
                <div class="summary-card__icon summary-card__icon--rose">
                    <i class="pi pi-shield"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Administrators</span>
                    <strong class="summary-card__value">{{
                        summaryStats.admins
                    }}</strong>
                    <span class="summary-card__sub"
                        >Full system clearance</span
                    >
                </div>
            </button>

            <button
                type="button"
                class="summary-card"
                :class="{ 'is-active': roleFilter === 'staff' }"
                @click="roleFilter = roleFilter === 'staff' ? 'all' : 'staff'"
            >
                <div class="summary-card__icon summary-card__icon--green">
                    <i class="pi pi-id-card"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Front Desk & Staff</span>
                    <strong class="summary-card__value">{{
                        summaryStats.staff
                    }}</strong>
                    <span class="summary-card__sub"
                        >Clinical & reception staff</span
                    >
                </div>
            </button>

            <button
                type="button"
                class="summary-card"
                @click="router.visit(rolesRoute().url)"
            >
                <div class="summary-card__icon summary-card__icon--amber">
                    <i class="pi pi-key"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Configured Roles</span>
                    <strong class="summary-card__value">{{
                        summaryStats.rolesDefined
                    }}</strong>
                    <span class="summary-card__sub"
                        >Manage permissions →</span
                    >
                </div>
            </button>
        </section>

        <!-- Main Workspace Table Card -->
        <section class="page-card admin-table-card">
            <!-- Top Segment Filter & Actions Bar -->
            <div class="workspace-header">
                <div class="status-segments" role="group" aria-label="Role filter">
                    <button
                        type="button"
                        class="segment-pill"
                        :class="{ 'is-selected': roleFilter === 'all' }"
                        @click="roleFilter = 'all'"
                    >
                        All Users ({{ props.users.data.length }})
                    </button>
                    <button
                        v-for="role in props.roles"
                        :key="role.id"
                        type="button"
                        class="segment-pill capitalize"
                        :class="{ 'is-selected': roleFilter === role.name }"
                        @click="
                            roleFilter =
                                roleFilter === role.name ? 'all' : role.name
                        "
                    >
                        {{ role.name }}
                    </button>
                </div>

                <div class="workspace-header__right">
                    <Button
                        label="Create User"
                        icon="pi pi-user-plus"
                        severity="primary"
                        size="small"
                        class="btn-auto"
                        @click="openCreateModal"
                    />
                </div>
            </div>

            <!-- Search Bar -->
            <div class="filter-bar">
                <div class="filter-bar__search">
                    <i class="pi pi-search filter-bar__search-icon"></i>
                    <InputText
                        v-model="searchQuery"
                        placeholder="Search staff by name or email address..."
                        class="filter-bar__search-input"
                        @keyup.enter="handleSearch"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="filter-bar__clear-input"
                        title="Clear search"
                        @click="handleClearSearch"
                    >
                        <i class="pi pi-times"></i>
                    </button>
                </div>

                <div class="filter-bar__controls">
                    <Button
                        v-if="searchQuery || roleFilter !== 'all'"
                        icon="pi pi-filter-slash"
                        label="Reset"
                        severity="secondary"
                        outlined
                        size="small"
                        class="btn-auto"
                        @click="handleClearSearch"
                    />
                </div>
            </div>

            <!-- Users Table -->
            <DataTable
                v-if="filteredUsers.length > 0"
                :value="filteredUsers"
                class="admin-users-table"
                responsive-layout="scroll"
                row-hover
                striped-rows
                data-key="id"
            >
                <Column field="id" header="ID" style="width: 6rem">
                    <template #body="{ data }">
                        <span class="admin-token-chip">#{{ data.id }}</span>
                    </template>
                </Column>

                <Column
                    field="name"
                    header="Staff Member"
                    sortable
                    style="min-width: 15rem"
                >
                    <template #body="{ data }">
                        <div class="admin-person-cell">
                            <Avatar
                                :label="getInitials(data.name)"
                                shape="circle"
                                class="admin-person-cell__avatar"
                            />
                            <div class="user-identity">
                                <strong class="user-identity__name">{{
                                    data.name
                                }}</strong>
                                <span class="user-identity__email">{{
                                    data.email
                                }}</span>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column
                    field="email"
                    header="Email Address"
                    sortable
                    style="min-width: 14rem"
                >
                    <template #body="{ data }">
                        <span class="email-cell">
                            <i class="pi pi-envelope"></i>
                            {{ data.email }}
                        </span>
                    </template>
                </Column>

                <Column header="Assigned Roles" style="min-width: 12rem">
                    <template #body="{ data }">
                        <div class="roles-badge-group">
                            <Tag
                                v-for="role in data.roles"
                                :key="role"
                                :value="role"
                                :severity="getRoleSeverity(role)"
                                rounded
                                class="capitalize"
                            />
                            <span
                                v-if="!data.roles.length"
                                class="text-muted-italic"
                            >
                                No roles assigned
                            </span>
                        </div>
                    </template>
                </Column>

                <Column
                    field="created_at"
                    header="Joined Date"
                    sortable
                    style="width: 11rem"
                >
                    <template #body="{ data }">
                        <span class="joined-date-cell">
                            <i class="pi pi-calendar"></i>
                            {{ data.created_at }}
                        </span>
                    </template>
                </Column>

                <Column
                    header="Actions"
                    class="text-right"
                    header-class="text-right"
                    style="width: 8rem"
                >
                    <template #body="{ data }">
                        <div class="row-actions">
                            <Button
                                v-tooltip.top="'Edit Staff Account'"
                                icon="pi pi-pencil"
                                severity="secondary"
                                text
                                rounded
                                size="small"
                                class="action-icon-btn"
                                @click="openEditModal(data)"
                            />
                            <Button
                                v-tooltip.top="'Delete Account'"
                                icon="pi pi-trash"
                                severity="danger"
                                text
                                rounded
                                size="small"
                                class="action-icon-btn"
                                @click="openDeleteModal(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <!-- Empty State -->
            <div v-else class="admin-empty-state admin-empty-state--soft">
                <i class="pi pi-users"></i>
                <h3>No staff accounts found</h3>
                <p>No user accounts match your current search or role filter.</p>
                <div class="empty-state__actions">
                    <Button
                        v-if="searchQuery || roleFilter !== 'all'"
                        label="Reset Filters"
                        icon="pi pi-filter-slash"
                        severity="secondary"
                        outlined
                        size="small"
                        class="btn-auto"
                        @click="handleClearSearch"
                    />
                    <Button
                        label="Create User"
                        icon="pi pi-user-plus"
                        severity="primary"
                        size="small"
                        class="btn-auto"
                        @click="openCreateModal"
                    />
                </div>
            </div>

            <!-- Table Pagination -->
            <div
                v-if="props.users.data.length > 0"
                class="admin-pagination-container"
            >
                <span class="pagination-summary">
                    Showing
                    <strong>{{ filteredUsers.length }}</strong>
                    of <strong>{{ props.users.total }}</strong> users
                </span>

                <div
                    v-if="props.users.links && props.users.links.length > 3"
                    class="admin-pagination"
                >
                    <button
                        v-for="link in props.users.links"
                        :key="link.label"
                        type="button"
                        class="pagination-btn"
                        :class="{
                            'is-active': link.active,
                            'is-disabled': !link.url,
                        }"
                        :disabled="!link.url"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>
        </section>

        <!-- Create/Edit User Dialog -->
        <Dialog
            v-model:visible="userDialog"
            :header="isEditMode ? 'Edit Staff Account' : 'New Staff Account'"
            :modal="true"
            class="admin-dialog"
            :style="{ width: '30rem', maxWidth: '95vw' }"
        >
            <form class="dialog-form" @submit.prevent="submitForm">
                <div class="field">
                    <label for="name" class="form-label">
                        Full Name <span class="required">*</span>
                    </label>
                    <InputText
                        id="name"
                        v-model="form.name"
                        required
                        autofocus
                        fluid
                        placeholder="e.g., Dr. Rajesh Sharma"
                        :invalid="!!form.errors.name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="field">
                    <label for="email" class="form-label">
                        Email Address <span class="required">*</span>
                    </label>
                    <InputText
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        fluid
                        placeholder="staff@clinic.com"
                        :invalid="!!form.errors.email"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="field">
                    <label for="password" class="form-label">
                        Password
                        <span v-if="!isEditMode" class="required">*</span>
                        <span v-else class="form-label__hint">
                            (Leave blank to keep current)
                        </span>
                    </label>
                    <InputText
                        id="password"
                        v-model="form.password"
                        type="password"
                        :required="!isEditMode"
                        fluid
                        placeholder="••••••••"
                        :invalid="!!form.errors.password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="field">
                    <label class="form-label">Assign Access Roles</label>
                    <div class="roles-checkbox-box">
                        <label
                            v-for="role in props.roles"
                            :key="role.id"
                            :for="`role-${role.id}`"
                            class="role-checkbox-tile"
                            :class="{
                                'is-checked': form.roles.includes(role.name),
                            }"
                        >
                            <Checkbox
                                :input-id="`role-${role.id}`"
                                v-model="form.roles"
                                :value="role.name"
                            />
                            <span class="capitalize">{{ role.name }}</span>
                        </label>
                    </div>
                    <InputError :message="form.errors.roles" />
                </div>

                <div class="dialog-footer">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        outlined
                        class="btn-auto"
                        :disabled="form.processing"
                        @click="userDialog = false"
                    />
                    <Button
                        type="submit"
                        :label="isEditMode ? 'Save Changes' : 'Create Account'"
                        icon="pi pi-check"
                        severity="primary"
                        class="btn-auto"
                        :loading="form.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog
            v-model:visible="deleteUserDialog"
            header="Confirm Account Deletion"
            :modal="true"
            class="admin-dialog"
            :style="{ width: '26rem', maxWidth: '95vw' }"
        >
            <div class="delete-confirm-body">
                <div class="delete-confirm-alert">
                    <i class="pi pi-exclamation-triangle"></i>
                    <div>
                        <strong>Delete {{ userToDelete?.name }}?</strong>
                        <p>
                            This will permanently remove the account for
                            <strong>{{ userToDelete?.name }}</strong>. This
                            action cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="dialog-footer">
                    <Button
                        type="button"
                        label="Keep Account"
                        severity="secondary"
                        outlined
                        class="btn-auto"
                        :disabled="form.processing"
                        @click="deleteUserDialog = false"
                    />
                    <Button
                        type="button"
                        label="Delete Permanently"
                        icon="pi pi-trash"
                        severity="danger"
                        class="btn-auto"
                        :loading="form.processing"
                        @click="confirmDelete"
                    />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.user-management-workspace {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.header-action-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.btn-auto {
    width: auto !important;
    white-space: nowrap !important;
}

.capitalize {
    text-transform: capitalize;
}

/* KPI Summary Cards */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

@media (max-width: 1100px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
}

.summary-card {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.15rem 1.25rem;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    text-align: left;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        transform 0.15s ease,
        box-shadow 0.15s ease;
    font-family: inherit;
}

.summary-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 50%, var(--surface-border));
    transform: translateY(-1px);
}

.summary-card.is-active {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary-color) 18%, transparent);
}

.summary-card__icon {
    width: 2.85rem;
    height: 2.85rem;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.summary-card__icon--blue {
    background: color-mix(in srgb, #3b82f6 14%, var(--surface-card));
    color: #2563eb;
}

.summary-card__icon--rose {
    background: color-mix(in srgb, #f43f5e 14%, var(--surface-card));
    color: #e11d48;
}

.summary-card__icon--green {
    background: color-mix(in srgb, #10b981 14%, var(--surface-card));
    color: #059669;
}

.summary-card__icon--amber {
    background: color-mix(in srgb, #f59e0b 14%, var(--surface-card));
    color: #d97706;
}

.summary-card__content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.summary-card__label {
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary-color);
}

.summary-card__value {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--text-color);
    line-height: 1.2;
}

.summary-card__sub {
    font-size: 0.76rem;
    font-weight: 500;
    color: var(--text-secondary-color);
}

/* Workspace Header & Filter Bar */
.workspace-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--surface-border);
    background: color-mix(in srgb, var(--surface-50) 65%, var(--surface-card));
    flex-wrap: wrap;
}

.status-segments {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.segment-pill {
    padding: 0.36rem 0.75rem;
    border-radius: 999px;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-secondary-color);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    font-family: inherit;
}

.segment-pill:hover {
    color: var(--text-color);
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--surface-border));
}

.segment-pill.is-selected {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--primary-contrast-color);
}

.filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--surface-border);
    background: var(--surface-card);
    flex-wrap: wrap;
}

.filter-bar__search {
    position: relative;
    flex: 1 1 20rem;
    min-width: 15rem;
    display: flex;
    align-items: center;
}

.filter-bar__search-icon {
    position: absolute;
    left: 0.85rem;
    color: var(--text-secondary-color);
    font-size: 0.88rem;
    pointer-events: none;
    z-index: 1;
}

.filter-bar__search-input {
    width: 100%;
    padding-left: 2.35rem !important;
    padding-right: 2.2rem !important;
}

.filter-bar__clear-input {
    position: absolute;
    right: 0.6rem;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: var(--text-secondary-color);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.filter-bar__clear-input:hover {
    background: var(--surface-hover);
    color: var(--text-color);
}

/* Cells */
.user-identity {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.user-identity__name {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text-color);
}

.user-identity__email {
    font-size: 0.76rem;
    color: var(--text-secondary-color);
}

.email-cell,
.joined-date-cell {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    color: var(--text-color);
    font-weight: 500;
}

.email-cell i,
.joined-date-cell i {
    color: var(--text-secondary-color);
    font-size: 0.8rem;
}

.roles-badge-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.text-muted-italic {
    font-size: 0.78rem;
    font-style: italic;
    color: var(--text-secondary-color);
}

.text-right {
    text-align: right !important;
}

.row-actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
}

.action-icon-btn {
    width: 2rem !important;
    height: 2rem !important;
    padding: 0 !important;
}

.empty-state__actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-top: 0.5rem;
}

.pagination-summary {
    font-size: 0.82rem;
    color: var(--text-secondary-color);
}

.pagination-summary strong {
    color: var(--text-color);
}

/* Dialog */
.dialog-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.38rem;
}

.form-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-color);
}

.form-label__hint {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-secondary-color);
}

.required {
    color: #e11d48;
}

.roles-checkbox-box {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.role-checkbox-tile {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    border: 1px solid var(--surface-border);
    background: var(--surface-50);
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-color);
    cursor: pointer;
    transition: all 0.15s ease;
}

.role-checkbox-tile.is-checked {
    border-color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 8%, var(--surface-card));
}

.dialog-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.65rem;
    padding-top: 1rem;
    border-top: 1px solid var(--surface-border);
}

.delete-confirm-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.delete-confirm-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1rem;
    border-radius: 10px;
    background: color-mix(in srgb, #f43f5e 8%, var(--surface-card));
    border: 1px solid color-mix(in srgb, #f43f5e 25%, var(--surface-border));
}

.delete-confirm-alert i {
    font-size: 1.5rem;
    color: #e11d48;
    margin-top: 0.1rem;
}

.delete-confirm-alert strong {
    font-size: 0.92rem;
    color: var(--text-color);
}

.delete-confirm-alert p {
    margin: 0.25rem 0 0;
    font-size: 0.82rem;
    color: var(--text-secondary-color);
    line-height: 1.45;
}
</style>
