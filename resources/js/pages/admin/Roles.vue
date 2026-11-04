<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { computed, onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { dashboard } from '@/routes';
import { roles, users as usersRoute } from '@/routes/admin';

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

const canTeleportActions = ref(false);
onMounted(() => {
    canTeleportActions.value = !!document.getElementById('admin-header-actions');
});

const searchQuery = ref('');
const roleDialog = ref(false);
const deleteRoleDialog = ref(false);
const isEditMode = ref(false);
const editingRoleId = ref<number | null>(null);

const form = useForm({
    name: '',
    permissions: [] as string[],
});

const roleToDelete = ref<RoleItem | null>(null);

const filteredRoles = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.roles;
    }
    return props.roles.filter(
        (r) =>
            r.name.toLowerCase().includes(q) ||
            r.permissions.some((p) => p.toLowerCase().includes(q)),
    );
});

const summaryStats = computed(() => ({
    totalRoles: props.roles.length,
    systemRoles: props.roles.filter((r) => isSystemRole(r.name)).length,
    customRoles: props.roles.filter((r) => !isSystemRole(r.name)).length,
    totalPermissions: props.permissions.length,
}));

function openCreateModal(): void {
    isEditMode.value = false;
    editingRoleId.value = null;
    form.reset();
    form.clearErrors();
    roleDialog.value = true;
}

function openEditModal(role: RoleItem): void {
    isEditMode.value = true;
    editingRoleId.value = role.id;
    form.clearErrors();
    form.name = role.name;
    form.permissions = [...role.permissions];
    roleDialog.value = true;
}

function openDeleteModal(role: RoleItem): void {
    roleToDelete.value = role;
    deleteRoleDialog.value = true;
}

function selectAllPermissions(): void {
    form.permissions = props.permissions.map((p) => p.name);
}

function clearAllPermissions(): void {
    form.permissions = [];
}

function submitForm(): void {
    if (isEditMode.value && editingRoleId.value) {
        form.patch(`${roles().url}/${editingRoleId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                roleDialog.value = false;
                form.reset();
            },
        });
    } else {
        form.post(roles().url, {
            preserveScroll: true,
            onSuccess: () => {
                roleDialog.value = false;
                form.reset();
            },
        });
    }
}

function confirmDelete(): void {
    if (roleToDelete.value) {
        form.delete(`${roles().url}/${roleToDelete.value.id}`, {
            preserveScroll: true,
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

function getPermissionSeverity(
    perm: string,
): 'success' | 'info' | 'warn' | 'danger' | 'secondary' {
    if (perm === 'manage staff') {
        return 'danger';
    }
    if (perm === 'manage patients') {
        return 'info';
    }
    return 'secondary';
}
</script>

<template>
    <Head title="Roles & Permissions" />

    <div class="page-grid roles-permissions-workspace">
        <!-- Teleport Primary Actions into Header -->
        <Teleport v-if="canTeleportActions" to="#admin-header-actions">
            <div class="header-action-group">
                <Button
                    label="Manage Users"
                    icon="pi pi-users"
                    severity="secondary"
                    outlined
                    size="small"
                    class="btn-auto"
                    @click="router.visit(usersRoute().url)"
                />
                <Button
                    label="Create Role"
                    icon="pi pi-key"
                    severity="primary"
                    size="small"
                    class="btn-auto"
                    @click="openCreateModal"
                />
            </div>
        </Teleport>

        <!-- Summary Strip -->
        <section class="summary-grid" aria-label="Roles overview">
            <div class="summary-card">
                <div class="summary-card__icon summary-card__icon--blue">
                    <i class="pi pi-key"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Defined Roles</span>
                    <strong class="summary-card__value">{{
                        summaryStats.totalRoles
                    }}</strong>
                    <span class="summary-card__sub"
                        >Active access profiles</span
                    >
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-card__icon summary-card__icon--rose">
                    <i class="pi pi-lock"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">System Roles</span>
                    <strong class="summary-card__value">{{
                        summaryStats.systemRoles
                    }}</strong>
                    <span class="summary-card__sub"
                        >Protected core roles</span
                    >
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-card__icon summary-card__icon--green">
                    <i class="pi pi-sliders-h"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Custom Roles</span>
                    <strong class="summary-card__value">{{
                        summaryStats.customRoles
                    }}</strong>
                    <span class="summary-card__sub"
                        >Clinic-defined roles</span
                    >
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-card__icon summary-card__icon--amber">
                    <i class="pi pi-shield"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Permission Scopes</span>
                    <strong class="summary-card__value">{{
                        summaryStats.totalPermissions
                    }}</strong>
                    <span class="summary-card__sub"
                        >Granular capabilities</span
                    >
                </div>
            </div>
        </section>

        <!-- Roles Table Panel -->
        <section class="page-card admin-table-card">
            <div class="workspace-header">
                <div class="workspace-header__copy">
                    <h3 class="workspace-title">Defined Access Roles</h3>
                    <span class="workspace-count-pill"
                        >{{ filteredRoles.length }} roles</span
                    >
                </div>

                <div class="workspace-header__right">
                    <Button
                        label="Create Role"
                        icon="pi pi-plus"
                        severity="primary"
                        size="small"
                        class="btn-auto"
                        @click="openCreateModal"
                    />
                </div>
            </div>

            <div class="filter-bar">
                <div class="filter-bar__search">
                    <i class="pi pi-search filter-bar__search-icon"></i>
                    <InputText
                        v-model="searchQuery"
                        placeholder="Filter by role name or permission scope..."
                        class="filter-bar__search-input"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="filter-bar__clear-input"
                        title="Clear filter"
                        @click="searchQuery = ''"
                    >
                        <i class="pi pi-times"></i>
                    </button>
                </div>
            </div>

            <DataTable
                v-if="filteredRoles.length > 0"
                :value="filteredRoles"
                class="admin-roles-table"
                responsive-layout="scroll"
                row-hover
                striped-rows
                data-key="id"
            >
                <Column
                    field="name"
                    header="Role Name"
                    sortable
                    style="width: 14rem; min-width: 14rem"
                >
                    <template #body="{ data }">
                        <div class="role-name-cell">
                            <span class="role-icon-badge">
                                <i
                                    :class="
                                        isSystemRole(data.name)
                                            ? 'pi pi-shield'
                                            : 'pi pi-key'
                                    "
                                ></i>
                            </span>
                            <div>
                                <div class="role-title-row">
                                    <strong class="role-title capitalize">{{
                                        data.name
                                    }}</strong>
                                    <Tag
                                        v-if="isSystemRole(data.name)"
                                        value="System"
                                        severity="contrast"
                                        rounded
                                        class="tag-xs"
                                    />
                                </div>
                                <span class="role-meta-sub">
                                    {{ data.permissions.length }} permission(s)
                                </span>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column
                    header="Authorized Permissions"
                    style="min-width: 20rem"
                >
                    <template #body="{ data }">
                        <div class="permissions-badge-group">
                            <Tag
                                v-for="perm in data.permissions"
                                :key="perm"
                                :value="perm"
                                :severity="getPermissionSeverity(perm)"
                                rounded
                                class="capitalize"
                            />
                            <span
                                v-if="!data.permissions.length"
                                class="text-muted-italic"
                            >
                                No permissions assigned
                            </span>
                        </div>
                    </template>
                </Column>

                <Column
                    field="created_at"
                    header="Created Date"
                    sortable
                    style="width: 11rem"
                >
                    <template #body="{ data }">
                        <span class="created-date-cell">
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
                                v-tooltip.top="'Edit Role & Permissions'"
                                icon="pi pi-pencil"
                                severity="secondary"
                                text
                                rounded
                                size="small"
                                class="action-icon-btn"
                                @click="openEditModal(data)"
                            />
                            <Button
                                v-if="!isSystemRole(data.name)"
                                v-tooltip.top="'Delete Role'"
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

            <div v-else class="admin-empty-state admin-empty-state--soft">
                <i class="pi pi-key"></i>
                <h3>No matching roles found</h3>
                <p>Try clearing your filter or create a new access role.</p>
            </div>
        </section>

        <!-- Create/Edit Role Dialog -->
        <Dialog
            v-model:visible="roleDialog"
            :header="isEditMode ? 'Edit Access Role' : 'New Access Role'"
            :modal="true"
            class="admin-dialog"
            :style="{ width: '32rem', maxWidth: '95vw' }"
        >
            <form class="dialog-form" @submit.prevent="submitForm">
                <div class="field">
                    <label for="name" class="form-label">
                        Role Name <span class="required">*</span>
                    </label>
                    <InputText
                        id="name"
                        v-model="form.name"
                        required
                        autofocus
                        fluid
                        placeholder="e.g., receptionist, pharmacist"
                        :invalid="!!form.errors.name"
                        :disabled="isEditMode && isSystemRole(form.name)"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="field">
                    <div class="field-header-row">
                        <label class="form-label"
                            >Grant System Permissions</label
                        >
                        <div class="quick-select-links">
                            <button
                                type="button"
                                class="link-btn"
                                @click="selectAllPermissions"
                            >
                                Select All
                            </button>
                            <span>·</span>
                            <button
                                type="button"
                                class="link-btn"
                                @click="clearAllPermissions"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                    <div class="permissions-checkbox-grid">
                        <label
                            v-for="perm in props.permissions"
                            :key="perm.id"
                            :for="`perm-${perm.id}`"
                            class="perm-checkbox-tile"
                            :class="{
                                'is-checked': form.permissions.includes(
                                    perm.name,
                                ),
                            }"
                        >
                            <Checkbox
                                :input-id="`perm-${perm.id}`"
                                v-model="form.permissions"
                                :value="perm.name"
                            />
                            <span class="capitalize">{{ perm.name }}</span>
                        </label>
                    </div>
                    <InputError :message="form.errors.permissions" />
                </div>

                <div class="dialog-footer">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        outlined
                        class="btn-auto"
                        :disabled="form.processing"
                        @click="roleDialog = false"
                    />
                    <Button
                        type="submit"
                        :label="isEditMode ? 'Save Changes' : 'Create Role'"
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
            v-model:visible="deleteRoleDialog"
            header="Confirm Role Deletion"
            :modal="true"
            class="admin-dialog"
            :style="{ width: '26rem', maxWidth: '95vw' }"
        >
            <div class="delete-confirm-body">
                <div class="delete-confirm-alert">
                    <i class="pi pi-exclamation-triangle"></i>
                    <div>
                        <strong>Delete role "{{ roleToDelete?.name }}"?</strong>
                        <p>
                            Users assigned this role will lose its mapped
                            permissions. This action cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="dialog-footer">
                    <Button
                        type="button"
                        label="Keep Role"
                        severity="secondary"
                        outlined
                        class="btn-auto"
                        :disabled="form.processing"
                        @click="deleteRoleDialog = false"
                    />
                    <Button
                        type="button"
                        label="Delete Role"
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
.roles-permissions-workspace {
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

.workspace-header__copy {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.workspace-title {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--text-color);
}

.workspace-count-pill {
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-card));
    color: var(--primary-color);
}

.filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--surface-border);
    background: var(--surface-card);
}

.filter-bar__search {
    position: relative;
    flex: 1 1 20rem;
    max-width: 28rem;
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

/* Cells */
.role-name-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.role-icon-badge {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-card));
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.role-title-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.role-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text-color);
}

.role-meta-sub {
    font-size: 0.75rem;
    color: var(--text-secondary-color);
}

.tag-xs {
    font-size: 0.68rem !important;
    padding: 0.12rem 0.45rem !important;
}

.permissions-badge-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.created-date-cell {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.84rem;
    color: var(--text-color);
    font-weight: 500;
}

.created-date-cell i {
    color: var(--text-secondary-color);
    font-size: 0.8rem;
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

/* Dialog */
.dialog-form {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.field-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.quick-select-links {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    color: var(--text-secondary-color);
}

.link-btn {
    border: none;
    background: none;
    padding: 0;
    color: var(--primary-color);
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}

.link-btn:hover {
    text-decoration: underline;
}

.form-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-color);
}

.required {
    color: #e11d48;
}

.permissions-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

@media (max-width: 500px) {
    .permissions-checkbox-grid {
        grid-template-columns: 1fr;
    }
}

.perm-checkbox-tile {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    border: 1px solid var(--surface-border);
    background: var(--surface-50);
    font-size: 0.84rem;
    font-weight: 600;
    color: var(--text-color);
    cursor: pointer;
    transition: all 0.15s ease;
}

.perm-checkbox-tile.is-checked {
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
