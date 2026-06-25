<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { clinicSettings as clinicSettingsRoute } from '@/routes/admin';

type Props = {
    settings: {
        clinic_name: string | null;
        doctor_name: string | null;
        doctor_qualifications: string | null;
        doctor_title: string | null;
        doctor_registration_no: string | null;
        clinic_registration_no: string | null;
        address: string | null;
        phone: string | null;
        email: string | null;
        logo_url: string | null;
    };
};

const props = defineProps<Props>();

const mounted = ref(false);
const logoPreview = ref<string | null>(props.settings.logo_url);
const logoFileInput = ref<HTMLInputElement | null>(null);

onMounted(() => {
    mounted.value = true;
});

defineOptions({
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Clinic Settings', href: '/clinic-settings' },
        ],
    },
});

const form = useForm({
    clinic_name: props.settings.clinic_name ?? '',
    doctor_name: props.settings.doctor_name ?? '',
    doctor_qualifications: props.settings.doctor_qualifications ?? '',
    doctor_title: props.settings.doctor_title ?? '',
    doctor_registration_no: props.settings.doctor_registration_no ?? '',
    clinic_registration_no: props.settings.clinic_registration_no ?? '',
    address: props.settings.address ?? '',
    phone: props.settings.phone ?? '',
    email: props.settings.email ?? '',
    logo: null as File | null,
});

function handleFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        const file = target.files[0];
        form.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
}

function triggerFileInput(): void {
    logoFileInput.value?.click();
}

function submitSettings(): void {
    // Standard Inertia post request with file upload
    form.post(clinicSettingsRoute().url, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Clinic Settings" />

    <Teleport v-if="mounted" to="#admin-header-actions">
        <Button
            label="Save Details"
            icon="pi pi-check"
            :loading="form.processing"
            class="p-button-primary flat-btn shadow-sm"
            @click="submitSettings"
        />
    </Teleport>

    <div class="page-grid clinic-settings-page">
        <div class="settings-grid">
            <!-- Left Column: Settings Form -->
            <div class="settings-column">
                <article class="page-card form-section-card">
                    <h3 class="card-title">Clinic Details</h3>
                    <p class="card-subtitle">General clinic and organization parameters.</p>
                    <Divider class="my-3" />

                    <div class="form-container">
                        <div class="form-row">
                            <div class="field">
                                <label for="clinic_name" class="field-label">Clinic Name</label>
                                <InputText
                                    id="clinic_name"
                                    v-model="form.clinic_name"
                                    placeholder="e.g. AOM Clinic"
                                    class="w-full"
                                />
                                <InputError :message="form.errors.clinic_name" />
                            </div>
                        </div>

                        <div class="form-row-2-col">
                            <div class="field">
                                <label for="phone" class="field-label">Phone Number</label>
                                <InputText
                                    id="phone"
                                    v-model="form.phone"
                                    placeholder="e.g. +91 98765 43210"
                                    class="w-full"
                                />
                                <InputError :message="form.errors.phone" />
                            </div>

                            <div class="field">
                                <label for="email" class="field-label">Email Address</label>
                                <InputText
                                    id="email"
                                    v-model="form.email"
                                    placeholder="e.g. contact@aomclinic.com"
                                    class="w-full"
                                />
                                <InputError :message="form.errors.email" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field">
                                <label for="address" class="field-label">Clinic Address</label>
                                <Textarea
                                    id="address"
                                    v-model="form.address"
                                    placeholder="Full mailing address of the clinic..."
                                    rows="3"
                                    class="w-full"
                                    fluid
                                />
                                <InputError :message="form.errors.address" />
                            </div>
                        </div>
                    </div>
                </article>

                <article class="page-card form-section-card mt-4">
                    <h3 class="card-title">Consultant Profile & Registration</h3>
                    <p class="card-subtitle">Configure doctor credentials printed on the consultation receipts.</p>
                    <Divider class="my-3" />

                    <div class="form-container">
                        <div class="form-row-2-col">
                            <div class="field">
                                <label for="doctor_name" class="field-label">Doctor Name</label>
                                <InputText
                                    id="doctor_name"
                                    v-model="form.doctor_name"
                                    placeholder="e.g. Dr. Appasaheb S. Waghmare"
                                    class="w-full"
                                />
                                <InputError :message="form.errors.doctor_name" />
                            </div>

                            <div class="field">
                                <label for="doctor_qualifications" class="field-label">Doctor Qualifications</label>
                                <InputText
                                    id="doctor_qualifications"
                                    v-model="form.doctor_qualifications"
                                    placeholder="e.g. M.D. (HOM)"
                                    class="w-full"
                                />
                                <InputError :message="form.errors.doctor_qualifications" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field">
                                <label for="doctor_title" class="field-label">Doctor Title / Specialization</label>
                                <InputText
                                    id="doctor_title"
                                    v-model="form.doctor_title"
                                    placeholder="e.g. Homeopathic Consultant"
                                    class="w-full"
                                />
                                <InputError :message="form.errors.doctor_title" />
                            </div>
                        </div>

                        <div class="form-row-2-col">
                            <div class="field">
                                <label for="doctor_registration_no" class="field-label">Doctor Registration No.</label>
                                <InputText
                                    id="doctor_registration_no"
                                    v-model="form.doctor_registration_no"
                                    placeholder="e.g. 49112"
                                    class="w-full"
                                />
                                <InputError :message="form.errors.doctor_registration_no" />
                            </div>

                            <div class="field">
                                <label for="clinic_registration_no" class="field-label">Clinic Registration No.</label>
                                <InputText
                                    id="clinic_registration_no"
                                    v-model="form.clinic_registration_no"
                                    placeholder="e.g. VVCMC/C-C442/2014"
                                    class="w-full"
                                />
                                <InputError :message="form.errors.clinic_registration_no" />
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Right Column: Logo Card -->
            <div class="logo-column">
                <article class="page-card form-section-card logo-card">
                    <h3 class="card-title">Clinic Logo</h3>
                    <p class="card-subtitle">Upload clinic branding logo for the printed receipts.</p>
                    <Divider class="my-3" />

                    <div class="logo-upload-container">
                        <div class="logo-preview-box" @click="triggerFileInput">
                            <img
                                v-if="logoPreview"
                                :src="logoPreview"
                                alt="Clinic Logo Preview"
                                class="logo-preview-image"
                            />
                            <div v-else class="logo-upload-placeholder">
                                <i class="pi pi-image upload-icon"></i>
                                <span>Click to Upload Logo</span>
                                <span class="upload-hint">PNG, JPG, or SVG, up to 2MB</span>
                            </div>
                        </div>
                        <input
                            ref="logoFileInput"
                            type="file"
                            accept="image/*"
                            class="hidden-file-input"
                            @change="handleFileChange"
                        />
                        <InputError :message="form.errors.logo" class="mt-2 text-center" />
                        
                        <Button
                            v-if="form.logo || logoPreview"
                            label="Change Logo"
                            icon="pi pi-pencil"
                            outlined
                            size="small"
                            class="mt-3 w-full"
                            @click="triggerFileInput"
                        />
                    </div>
                </article>
            </div>
        </div>
    </div>
</template>

<style scoped>
.clinic-settings-page {
    display: flex;
    flex-direction: column;
}

.settings-grid {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    gap: 1.5rem;
    align-items: start;
}

.settings-column {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.form-section-card {
    padding: 1.5rem;
}

.card-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-color);
}

.card-subtitle {
    margin: 0.25rem 0 0;
    font-size: 0.85rem;
    color: var(--text-secondary-color);
}

.form-container {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.form-row {
    display: flex;
    flex-direction: column;
}

.form-row-2-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.field-label {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text-color);
}

.logo-card {
    position: sticky;
    top: 1.5rem;
}

.logo-upload-container {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.logo-preview-box {
    width: 100%;
    aspect-ratio: 16/9;
    border: 2px dashed var(--surface-border);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    overflow: hidden;
    background: var(--surface-ground);
    transition: border-color 0.2s ease;
}

.logo-preview-box:hover {
    border-color: var(--primary-color);
}

.logo-preview-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.logo-upload-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary-color);
    font-size: 0.85rem;
    padding: 1.5rem;
    text-align: center;
}

.upload-icon {
    font-size: 2rem;
    color: var(--primary-color);
    opacity: 0.75;
}

.upload-hint {
    font-size: 0.75rem;
    opacity: 0.8;
}

.hidden-file-input {
    display: none;
}

@media (max-width: 991px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
    .logo-card {
        position: static;
    }
}

@media (max-width: 640px) {
    .form-row-2-col {
        grid-template-columns: 1fr;
    }
}
</style>
