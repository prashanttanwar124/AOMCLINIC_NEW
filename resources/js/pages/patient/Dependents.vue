<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Divider from 'primevue/divider';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';

type Dependent = {
    id: number;
    name: string;
    date_of_birth: string | null;
    gender: string | null;
    address: string | null;
    city: string | null;
    is_account_holder?: boolean;
};

const props = defineProps<{
    dependents: Dependent[];
    canAddDependents: boolean;
}>();

defineOptions({
    layout: {
        title: 'Manage dependents',
    },
});

const genderOptions = [
    { label: 'Male', value: 'male' },
    { label: 'Female', value: 'female' },
    { label: 'Other', value: 'other' },
    { label: 'Prefer not to say', value: 'prefer_not_to_say' },
];

const dialogVisible = ref(false);
const editingDependent = ref<Dependent | null>(null);

const form = useForm({
    name: '',
    date_of_birth: '',
    gender: '',
    address: '',
    city: '',
});

function openAddDialog(): void {
    editingDependent.value = null;
    form.clearErrors();
    form.reset();
    dialogVisible.value = true;
}

function openEditDialog(dep: Dependent): void {
    editingDependent.value = dep;
    form.clearErrors();
    form.setData({
        name: dep.name,
        date_of_birth: dep.date_of_birth ?? '',
        gender: dep.gender ?? '',
        address: dep.address ?? '',
        city: dep.city ?? '',
    });
    dialogVisible.value = true;
}

function handleSubmit(): void {
    if (editingDependent.value) {
        form.patch(`/patient/dependents/${editingDependent.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                dialogVisible.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/patient/dependents', {
            preserveScroll: true,
            onSuccess: () => {
                dialogVisible.value = false;
                form.reset();
            },
        });
    }
}

function formatGender(gender: string | null): string {
    if (!gender) return '-';
    const found = genderOptions.find((g) => g.value === gender);
    return found ? found.label : gender;
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    const [year, month, day] = dateStr.split('-').map(Number);
    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
}
</script>

<template>
    <Head title="Dependents" />

    <div class="dependents-container">
        <!-- Header actions banner -->
        <div class="dependents-header">
            <div>
                <h2 class="section-title">Your Dependents</h2>
                <p class="section-subtitle">Add and manage family members linked to your primary account.</p>
            </div>
            <Button
                v-if="props.canAddDependents"
                label="Add Dependent"
                icon="pi pi-plus"
                class="p-button-primary"
                @click="openAddDialog"
            />
        </div>

        <Divider class="my-4" />

        <!-- Dependents Grid -->
        <div v-if="props.dependents.length > 0" class="dependents-grid">
            <article
                v-for="dep in props.dependents"
                :key="dep.id"
                class="page-card dependent-card"
            >
                <div class="dependent-card__header">
                    <div class="avatar-circle">
                        {{ dep.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="dependent-identity">
                        <h3>{{ dep.name }}</h3>
                        <div class="identity-tags">
                            <span v-if="dep.is_account_holder" class="account-holder-tag">Account Holder</span>
                            <span class="gender-tag">{{ formatGender(dep.gender) }}</span>
                        </div>
                    </div>
                </div>

                <Divider class="my-3" />

                <div class="dependent-details-list">
                    <div class="detail-item">
                        <i class="pi pi-calendar detail-icon"></i>
                        <div class="detail-content">
                            <span class="detail-label">Date of Birth</span>
                            <span class="detail-value">{{ formatDate(dep.date_of_birth) }}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <i class="pi pi-map-marker detail-icon"></i>
                        <div class="detail-content">
                            <span class="detail-label">City</span>
                            <span class="detail-value">{{ dep.city || '-' }}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <i class="pi pi-home detail-icon"></i>
                        <div class="detail-content">
                            <span class="detail-label">Address</span>
                            <span class="detail-value">{{ dep.address || '-' }}</span>
                        </div>
                    </div>
                </div>

                <Divider v-if="!dep.is_account_holder" class="my-3" />

                <div v-if="!dep.is_account_holder" class="dependent-card__actions">
                    <Button
                        label="Edit Details"
                        icon="pi pi-pencil"
                        outlined
                        severity="secondary"
                        size="small"
                        class="w-full"
                        @click="openEditDialog(dep)"
                    />
                </div>
            </article>
        </div>

        <!-- Empty State -->
        <div v-else class="page-card empty-state">
            <i class="pi pi-users empty-icon"></i>
            <h3>No Dependents Linked</h3>
            <p>You have not added any dependents to your account yet.</p>
            <Button
                v-if="props.canAddDependents"
                label="Add Your First Dependent"
                icon="pi pi-plus"
                outlined
                class="mt-3"
                @click="openAddDialog"
            />
        </div>

        <!-- Add/Edit Dialog -->
        <Dialog
            v-model:visible="dialogVisible"
            modal
            dismissable-mask
            :header="editingDependent ? 'Edit Dependent Details' : 'Add New Dependent'"
            :style="{ width: '32rem' }"
        >
            <form @submit.prevent="handleSubmit" class="dependent-form">
                <div class="field">
                    <label for="name" class="field-label">Full Name</label>
                    <InputText
                        id="name"
                        v-model="form.name"
                        placeholder="e.g. Liam Smith"
                        class="w-full"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="field">
                    <label for="date_of_birth" class="field-label">Date of Birth</label>
                    <InputText
                        id="date_of_birth"
                        type="date"
                        v-model="form.date_of_birth"
                        class="w-full"
                        required
                    />
                    <InputError :message="form.errors.date_of_birth" />
                </div>

                <div class="field">
                    <label for="gender" class="field-label">Gender</label>
                    <Select
                        id="gender"
                        v-model="form.gender"
                        :options="genderOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Select gender"
                        class="w-full"
                        required
                    />
                    <InputError :message="form.errors.gender" />
                </div>

                <div class="field">
                    <label for="city" class="field-label">City</label>
                    <InputText
                        id="city"
                        v-model="form.city"
                        placeholder="City"
                        class="w-full"
                    />
                    <InputError :message="form.errors.city" />
                </div>

                <div class="field">
                    <label for="address" class="field-label">Address</label>
                    <Textarea
                        id="address"
                        v-model="form.address"
                        placeholder="Street address..."
                        rows="3"
                        class="w-full"
                    />
                    <InputError :message="form.errors.address" />
                </div>

                <div class="form-actions">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        type="button"
                        :disabled="form.processing"
                        @click="dialogVisible = false"
                    />
                    <Button
                        type="submit"
                        :label="editingDependent ? 'Save Changes' : 'Add Dependent'"
                        icon="pi pi-check"
                        :loading="form.processing"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>

<style scoped>
.dependents-container {
    padding: 0.5rem 0 2rem;
}

.dependents-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    background: var(--panel-bg);
    border: 1px solid var(--panel-border);
    border-radius: var(--content-border-radius);
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
}

.dependents-header :deep(.p-button) {
    width: auto !important;
    padding: 0.6rem 1.5rem;
    font-weight: 600;
}

.section-title {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--text-color);
}

.section-subtitle {
    margin: 0.35rem 0 0;
    font-size: 0.88rem;
    color: var(--text-secondary-color);
}

.dependents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.dependent-card {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--panel-border);
    border-radius: var(--content-border-radius);
    background: var(--panel-bg);
    box-shadow: var(--card-shadow);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.dependent-card:hover {
    transform: translateY(-4px);
    border-color: var(--primary-color);
    box-shadow: var(--card-shadow-strong), 0 4px 20px rgba(15, 181, 186, 0.08);
}

.dependent-card__header {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.avatar-circle {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 50%;
    background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-card));
    color: var(--primary-color);
    border: 1px solid color-mix(in srgb, var(--primary-color) 20%, transparent);
    font-weight: 700;
    font-size: 1.35rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(15, 181, 186, 0.08);
}

.dependent-identity {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.dependent-identity h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-color);
    line-height: 1.2;
}

.identity-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    align-items: center;
    margin-top: 0.35rem;
}

.account-holder-tag {
    display: inline-flex;
    padding: 0.15rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 700;
    background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-card));
    color: var(--primary-color);
    border: 1px solid color-mix(in srgb, var(--primary-color) 20%, transparent);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.gender-tag {
    display: inline-flex;
    padding: 0.15rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 700;
    background: var(--surface-hover);
    color: var(--text-secondary-color);
    border: 1px solid var(--surface-border);
    text-transform: capitalize;
}

.dependent-details-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    flex-grow: 1;
}

.detail-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.detail-icon {
    font-size: 0.95rem;
    color: var(--primary-color);
    margin-top: 0.2rem;
    opacity: 0.85;
    flex-shrink: 0;
}

.detail-content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.detail-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-secondary-color);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.7;
}

.detail-value {
    font-size: 0.9rem;
    color: var(--text-color);
    line-height: 1.45;
}

.dependent-card__actions {
    margin-top: 1rem;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 5rem 2rem;
    border: 1.5px dashed var(--surface-border);
    background: var(--panel-bg);
}

.empty-icon {
    font-size: 3.5rem;
    color: var(--text-secondary-color);
    opacity: 0.4;
    margin-bottom: 1.5rem;
}

.empty-state h3 {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--text-color);
}

.empty-state p {
    margin: 0.6rem 0 1.5rem;
    color: var(--text-secondary-color);
    font-size: 0.9rem;
    max-width: 24rem;
    line-height: 1.5;
}

.empty-state :deep(.p-button) {
    width: auto !important;
    padding: 0.6rem 1.5rem;
}

.dependent-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0.5rem 0.25rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.field-label {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-color);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--surface-border);
}

.form-actions :deep(.p-button) {
    width: auto !important;
    min-width: 8rem;
    padding: 0.65rem 1.5rem;
}
</style>
