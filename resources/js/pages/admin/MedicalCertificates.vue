<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PatientHistorySidebar from '@/components/PatientHistorySidebar.vue';
import http from '@/lib/http';
import { dashboard } from '@/routes';
import { medicalCertificates } from '@/routes/admin';
import { print as printRoute } from '@/routes/admin/medical-certificates';
import certificateTypesRoute from '@/routes/admin/certificate-types';
import PatientController from '@/actions/App/Http/Controllers/Admin/PatientController';

type PatientDetails = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    label: string;
};

type CertificateTypeItem = {
    id: number;
    name: string;
    description: string | null;
    default_charge: string | number;
};

type MedicalCertificateItem = {
    id: number;
    patient_id: number;
    patient_name: string;
    patient_phone: string | null;
    patient_email: string | null;
    certificate_type_id: number;
    certificate_type_name: string;
    certificate_number: string;
    issue_date: string;
    start_date: string | null;
    end_date: string | null;
    diagnosis: string | null;
    charge_amount: string | number;
    payment_status: string;
    notes: string | null;
    status: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedCertificates = {
    data: MedicalCertificateItem[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
    total: number;
};

type ClinicDetails = {
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

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Medical Certificates', href: '#' },
        ],
    },
});

const props = defineProps<{
    certificates: PaginatedCertificates;
    certificateTypes: CertificateTypeItem[];
    clinic: ClinicDetails;
    filters: {
        search: string | null;
        status: string | null;
        payment_status: string | null;
        type_id: string | number | null;
    };
}>();

// Active tab ('certificates' | 'types')
const activeTab = ref<'certificates' | 'types'>('certificates');

// Filter values
const searchFilter = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const paymentStatusFilter = ref(props.filters.payment_status ?? '');
const typeFilter = ref<number | null>(props.filters.type_id ? Number(props.filters.type_id) : null);

// Dialog states
const showCertDialog = ref(false);
const isCertEditMode = ref(false);
const editingCertId = ref<number | null>(null);

const showTypeDialog = ref(false);
const isTypeEditMode = ref(false);
const editingTypeId = ref<number | null>(null);

const showHistorySidebar = ref(false);
const historyPatientId = ref<number | null>(null);

// Selected patient for autocomplete
const selectedPatient = ref<PatientDetails | null>(null);
const patientSuggestions = ref<PatientDetails[]>([]);

// Options lists
const tabOptions = [
    { label: 'Issued Certificates', value: 'certificates', icon: 'pi pi-file' },
    { label: 'Certificate Types', value: 'types', icon: 'pi pi-sliders-h' },
];

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Void', value: 'void' },
];

const paymentStatusOptions = [
    { label: 'Unpaid', value: 'unpaid' },
    { label: 'Paid', value: 'paid' },
];

// Forms setup
const certForm = useForm({
    patient_id: null as number | null,
    certificate_type_id: null as number | null,
    issue_date: new Date() as Date | string,
    start_date: null as Date | string | null,
    end_date: null as Date | string | null,
    diagnosis: '',
    charge_amount: 0,
    payment_status: 'unpaid',
    notes: '',
    status: 'active',
});

const typeForm = useForm({
    name: '',
    description: '',
    default_charge: 0,
});

// Watch autocomplete patient
watch(selectedPatient, (newVal) => {
    if (newVal && typeof newVal === 'object' && newVal.id) {
        certForm.patient_id = newVal.id;
    } else {
        certForm.patient_id = null;
    }
});

// Watch type selection in certificate form to prefill default charge
watch(() => certForm.certificate_type_id, (newTypeId) => {
    if (newTypeId && !isCertEditMode.value) {
        const found = props.certificateTypes.find(t => t.id === newTypeId);
        if (found) {
            certForm.charge_amount = typeof found.default_charge === 'string'
                ? parseFloat(found.default_charge)
                : found.default_charge;
        }
    }
});

// Helpers
function toDateString(date: Date | null): string | null {
    if (!date) return null;
    if (typeof date === 'string') return date;
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const searchPatients = async (event: { query: string }) => {
    try {
        const { data } = await http.get(PatientController.search().url, {
            params: { query: event.query },
        });
        patientSuggestions.value = data;
    } catch (e) {
        console.error(e);
    }
};

function viewPatientHistory(patientId: number | null): void {
    if (!patientId) return;
    historyPatientId.value = patientId;
    showHistorySidebar.value = true;
}

// Certificate actions
function openNewCert(): void {
    isCertEditMode.value = false;
    editingCertId.value = null;
    selectedPatient.value = null;
    certForm.reset();
    certForm.clearErrors();
    certForm.issue_date = new Date();
    certForm.status = 'active';
    certForm.payment_status = 'unpaid';
    showCertDialog.value = true;
}

function openEditCert(cert: MedicalCertificateItem): void {
    isCertEditMode.value = true;
    editingCertId.value = cert.id;
    certForm.clearErrors();

    selectedPatient.value = {
        id: cert.patient_id,
        name: cert.patient_name,
        email: cert.patient_email,
        phone: cert.patient_phone,
        address: null,
        label: `${cert.patient_name} (#${cert.patient_id})`,
    };

    certForm.patient_id = cert.patient_id;
    certForm.certificate_type_id = cert.certificate_type_id;
    certForm.issue_date = cert.issue_date ? new Date(cert.issue_date) : new Date();
    certForm.start_date = cert.start_date ? new Date(cert.start_date) : null;
    certForm.end_date = cert.end_date ? new Date(cert.end_date) : null;
    certForm.diagnosis = cert.diagnosis || '';
    certForm.charge_amount = typeof cert.charge_amount === 'string' ? parseFloat(cert.charge_amount) : cert.charge_amount;
    certForm.payment_status = cert.payment_status;
    certForm.notes = cert.notes || '';
    certForm.status = cert.status;

    showCertDialog.value = true;
}

function submitCertForm(): void {
    certForm.transform((data) => ({
        ...data,
        issue_date: data.issue_date instanceof Date ? toDateString(data.issue_date) : data.issue_date,
        start_date: data.start_date instanceof Date ? toDateString(data.start_date) : data.start_date,
        end_date: data.end_date instanceof Date ? toDateString(data.end_date) : data.end_date,
    }));

    if (isCertEditMode.value && editingCertId.value !== null) {
        certForm.patch(medicalCertificates.update(editingCertId.value).url, {
            preserveScroll: true,
            onSuccess: () => {
                showCertDialog.value = false;
                certForm.reset();
                selectedPatient.value = null;
            },
        });
    } else {
        certForm.post(medicalCertificates.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                showCertDialog.value = false;
                certForm.reset();
                selectedPatient.value = null;
            },
        });
    }
}

function deleteCert(cert: MedicalCertificateItem): void {
    if (confirm(`Are you sure you want to delete medical certificate ${cert.certificate_number}?`)) {
        router.delete(medicalCertificates.destroy(cert.id).url, {
            preserveScroll: true,
        });
    }
}

function applyCertFilters(): void {
    router.get(
        medicalCertificates().url,
        {
            search: searchFilter.value || undefined,
            status: statusFilter.value || undefined,
            payment_status: paymentStatusFilter.value || undefined,
            type_id: typeFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
}

function clearCertFilters(): void {
    searchFilter.value = '';
    statusFilter.value = '';
    paymentStatusFilter.value = '';
    typeFilter.value = null;
    applyCertFilters();
}

function goToPage(url: string | null): void {
    if (!url) return;
    router.get(
        url,
        {
            search: searchFilter.value || undefined,
            status: statusFilter.value || undefined,
            payment_status: paymentStatusFilter.value || undefined,
            type_id: typeFilter.value || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
}

// Certificate Type actions
function openNewType(): void {
    isTypeEditMode.value = false;
    editingTypeId.value = null;
    typeForm.reset();
    typeForm.clearErrors();
    showTypeDialog.value = true;
}

function openEditType(type: CertificateTypeItem): void {
    isTypeEditMode.value = true;
    editingTypeId.value = type.id;
    typeForm.clearErrors();

    typeForm.name = type.name;
    typeForm.description = type.description || '';
    typeForm.default_charge = typeof type.default_charge === 'string' ? parseFloat(type.default_charge) : type.default_charge;

    showTypeDialog.value = true;
}

function submitTypeForm(): void {
    if (isTypeEditMode.value && editingTypeId.value !== null) {
        typeForm.patch(certificateTypesRoute.update(editingTypeId.value).url, {
            preserveScroll: true,
            onSuccess: () => {
                showTypeDialog.value = false;
                typeForm.reset();
            },
        });
    } else {
        typeForm.post(certificateTypesRoute.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                showTypeDialog.value = false;
                typeForm.reset();
            },
        });
    }
}

function deleteType(type: CertificateTypeItem): void {
    if (confirm(`Are you sure you want to delete certificate type "${type.name}"? This will also remove associated certificates.`)) {
        router.delete(certificateTypesRoute.destroy(type.id).url, {
            preserveScroll: true,
        });
    }
}

// Print Preview
function openPrintPreview(cert: MedicalCertificateItem | { id: number }): void {
    window.open(printRoute(cert.id).url, '_blank');
}

// Formatters
function formatCurrency(amount: string | number): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'INR' }).format(num);
}

function getStatusSeverity(status: string): 'success' | 'danger' | 'secondary' {
    return status === 'active' ? 'success' : 'danger';
}

function getPaymentSeverity(status: string): 'success' | 'danger' {
    return status === 'paid' ? 'success' : 'danger';
}
</script>

<template>
    <Head title="Medical Certificates Management" />

    <div class="page-grid certificates-page">
        <!-- Hero Header -->
        <section class="page-card certificates-hero">
            <div class="certificates-hero__copy">
                <p class="stat-label">Registrar Desk</p>
                <h2 class="certificates-hero__title">Medical Certificates</h2>
                <p class="panel-subtitle certificates-hero__subtitle">
                    Issue sick leaves, fitness records, and general medical certificates, assign customizable charges, and print official reports.
                </p>
            </div>
            <div class="certificates-hero__actions">
                <Button
                    v-if="activeTab === 'certificates'"
                    label="Issue Certificate"
                    icon="pi pi-plus"
                    class="p-button-primary flat-btn"
                    @click="openNewCert"
                />
                <Button
                    v-else
                    label="Add Certificate Type"
                    icon="pi pi-plus"
                    class="p-button-primary flat-btn"
                    @click="openNewType"
                />
            </div>
        </section>

        <!-- Navigation Tabs Selector -->
        <div class="tabs-header-container">
            <SelectButton
                v-model="activeTab"
                :options="tabOptions"
                option-label="label"
                option-value="value"
                class="tab-switcher"
                aria-label="Registrar tabs"
            >
                <template #option="{ option }">
                    <span class="switch-option">
                        <i :class="option.icon"></i>
                        <span>{{ option.label }}</span>
                    </span>
                </template>
            </SelectButton>
        </div>

        <!-- TAB 1: Issued Certificates -->
        <div v-if="activeTab === 'certificates'" class="tab-pane-container">
            <!-- Search & Filters Toolbar -->
            <article class="page-card filter-card">
                <div class="filter-grid">
                    <div class="filter-field">
                        <label for="search-input" class="filter-label">Search Patient / Cert No</label>
                        <span class="p-input-icon-left w-full search-input-wrapper">
                            <i class="pi pi-search search-icon"></i>
                            <InputText
                                id="search-input"
                                v-model="searchFilter"
                                placeholder="Name, Cert No, phone..."
                                class="w-full search-input"
                                @keyup.enter="applyCertFilters"
                            />
                        </span>
                    </div>

                    <div class="filter-field">
                        <label for="type-filter" class="filter-label">Certificate Type</label>
                        <Select
                            id="type-filter"
                            v-model="typeFilter"
                            :options="props.certificateTypes"
                            option-label="name"
                            option-value="id"
                            placeholder="All Types"
                            show-clear
                            fluid
                        />
                    </div>

                    <div class="filter-field">
                        <label for="payment-filter" class="filter-label">Payment Status</label>
                        <Select
                            id="payment-filter"
                            v-model="paymentStatusFilter"
                            :options="paymentStatusOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="All Statuses"
                            show-clear
                            fluid
                        />
                    </div>

                    <div class="filter-field">
                        <label for="status-filter" class="filter-label">Certificate Status</label>
                        <Select
                            id="status-filter"
                            v-model="statusFilter"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="All Statuses"
                            show-clear
                            fluid
                        />
                    </div>

                    <div class="filter-buttons">
                        <Button
                            label="Apply"
                            icon="pi pi-filter"
                            class="p-button-secondary flat-btn w-full"
                            @click="applyCertFilters"
                        />
                        <Button
                            label="Clear"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            class="flat-btn w-full"
                            @click="clearCertFilters"
                        />
                    </div>
                </div>
            </article>

            <!-- Certificates List -->
            <article class="page-card admin-table-card">
                <DataTable :value="props.certificates.data" responsive-layout="scroll" class="admin-table">
                    <Column header="Certificate #" style="min-width: 8rem">
                        <template #body="{ data }">
                            <span class="font-mono font-bold">{{ data.certificate_number }}</span>
                        </template>
                    </Column>
                    <Column header="Patient" style="min-width: 12rem">
                        <template #body="{ data }">
                            <div class="patient-cell">
                                <span class="patient-name font-bold">{{ data.patient_name }}</span>
                                <span class="text-secondary text-xxs" v-if="data.patient_phone">{{ data.patient_phone }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Type & Validity" style="min-width: 12rem">
                        <template #body="{ data }">
                            <div class="type-cell">
                                <span class="font-semibold">{{ data.certificate_type_name }}</span>
                                <span v-if="data.start_date && data.end_date" class="text-secondary text-xxs">
                                    Validity: {{ data.start_date }} to {{ data.end_date }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Issue Date" style="min-width: 8rem">
                        <template #body="{ data }">
                            <span>{{ data.issue_date }}</span>
                        </template>
                    </Column>
                    <Column header="Charge & Payment" style="min-width: 9rem">
                        <template #body="{ data }">
                            <div class="charge-cell">
                                <span class="font-semibold">{{ formatCurrency(data.charge_amount) }}</span>
                                <div>
                                    <Tag
                                        :value="data.payment_status.toUpperCase()"
                                        :severity="getPaymentSeverity(data.payment_status)"
                                        rounded
                                        style="font-size: 0.65rem; padding: 0.15rem 0.4rem;"
                                    />
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column header="Status" style="min-width: 7rem">
                        <template #body="{ data }">
                            <Tag
                                :value="data.status.toUpperCase()"
                                :severity="getStatusSeverity(data.status)"
                                rounded
                            />
                        </template>
                    </Column>
                    <Column header="Actions" class="text-right" style="width: 12rem">
                        <template #body="{ data }">
                            <div class="actions-cell">
                                <Button
                                    icon="pi pi-print"
                                    severity="info"
                                    outlined
                                    rounded
                                    size="small"
                                    v-tooltip="'Print Preview'"
                                    class="action-btn"
                                    @click="openPrintPreview(data)"
                                />
                                <Button
                                    icon="pi pi-history"
                                    severity="info"
                                    outlined
                                    rounded
                                    size="small"
                                    v-tooltip="'View Patient History'"
                                    class="action-btn"
                                    @click="viewPatientHistory(data.patient_id)"
                                />
                                <Button
                                    icon="pi pi-pencil"
                                    severity="secondary"
                                    outlined
                                    rounded
                                    size="small"
                                    v-tooltip="'Edit Certificate'"
                                    class="action-btn"
                                    :disabled="data.status === 'void'"
                                    @click="openEditCert(data)"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    outlined
                                    rounded
                                    size="small"
                                    v-tooltip="'Delete Certificate'"
                                    class="action-btn"
                                    @click="deleteCert(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <!-- Empty State -->
                <div v-if="props.certificates.data.length === 0" class="empty-state">
                    <i class="pi pi-file text-4xl opacity-50 mb-2"></i>
                    <h3>No medical certificates found</h3>
                    <p>No issued certificates match your filters or search query.</p>
                </div>

                <!-- Pagination Grid -->
                <div
                    v-if="props.certificates.links && props.certificates.links.length > 3"
                    class="admin-pagination-container"
                >
                    <div class="admin-pagination">
                        <button
                            v-for="link in props.certificates.links"
                            :key="link.label"
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
            </article>
        </div>

        <!-- TAB 2: Certificate Types -->
        <div v-else class="tab-pane-container">
            <article class="page-card admin-table-card">
                <DataTable :value="props.certificateTypes" responsive-layout="scroll" class="admin-table">
                    <Column header="Type Name" style="min-width: 12rem">
                        <template #body="{ data }">
                            <span class="font-bold">{{ data.name }}</span>
                        </template>
                    </Column>
                    <Column header="Description" style="min-width: 20rem">
                        <template #body="{ data }">
                            <span>{{ data.description || 'No description provided' }}</span>
                        </template>
                    </Column>
                    <Column header="Default Charge" style="min-width: 10rem">
                        <template #body="{ data }">
                            <span class="font-semibold">{{ formatCurrency(data.default_charge) }}</span>
                        </template>
                    </Column>
                    <Column header="Actions" class="text-right" style="width: 8rem">
                        <template #body="{ data }">
                            <div class="flex justify-end gap-2">
                                <Button
                                    icon="pi pi-pencil"
                                    severity="secondary"
                                    outlined
                                    rounded
                                    size="small"
                                    class="action-btn"
                                    @click="openEditType(data)"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    outlined
                                    rounded
                                    size="small"
                                    class="action-btn"
                                    @click="deleteType(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <div v-if="props.certificateTypes.length === 0" class="empty-state">
                    <i class="pi pi-sliders-h text-4xl opacity-50 mb-2"></i>
                    <h3>No certificate types defined</h3>
                    <p>Start by defining certificate types (e.g. Sick Leave) and their default pricing.</p>
                </div>
            </article>
        </div>

        <!-- DIALOG: Issue / Edit Certificate -->
        <Dialog
            v-model:visible="showCertDialog"
            :header="isCertEditMode ? 'Edit Medical Certificate' : 'Issue Medical Certificate'"
            :modal="true"
            class="certificates-dialog"
            :style="{ width: '550px' }"
            fluid
        >
            <form @submit.prevent="submitCertForm" class="dialog-form">
                <div class="dialog-body">
                    <!-- Patient lookup -->
                    <div class="field">
                        <label for="patient-search" class="form-label font-semibold">Select Patient <span class="required">*</span></label>
                        <AutoComplete
                            id="patient-search"
                            v-model="selectedPatient"
                            :suggestions="patientSuggestions"
                            option-label="label"
                            placeholder="Search by patient name or phone..."
                            fluid
                            @complete="searchPatients"
                            :disabled="isCertEditMode"
                        />
                        <InputError :message="certForm.errors.patient_id" />
                    </div>

                    <!-- Row: Type & Issue Date -->
                    <div class="form-row">
                        <div class="field col-6">
                            <label for="cert-type" class="form-label font-semibold">Certificate Type <span class="required">*</span></label>
                            <Select
                                id="cert-type"
                                v-model="certForm.certificate_type_id"
                                :options="props.certificateTypes"
                                option-label="name"
                                option-value="id"
                                placeholder="Select Type"
                                fluid
                            />
                            <InputError :message="certForm.errors.certificate_type_id" />
                        </div>
                        <div class="field col-6">
                            <label for="issue-date" class="form-label font-semibold">Date of Issue <span class="required">*</span></label>
                            <DatePicker
                                id="issue-date"
                                v-model="certForm.issue_date"
                                dateFormat="yy-mm-dd"
                                placeholder="Select Date"
                                fluid
                            />
                            <InputError :message="certForm.errors.issue_date" />
                        </div>
                    </div>

                    <!-- Row: Start Date & End Date -->
                    <div class="form-row">
                        <div class="field col-6">
                            <label for="start-date" class="form-label font-semibold">Validity Start Date</label>
                            <DatePicker
                                id="start-date"
                                v-model="certForm.start_date"
                                dateFormat="yy-mm-dd"
                                placeholder="Optional"
                                show-clear
                                fluid
                            />
                            <InputError :message="certForm.errors.start_date" />
                        </div>
                        <div class="field col-6">
                            <label for="end-date" class="form-label font-semibold">Validity End Date</label>
                            <DatePicker
                                id="end-date"
                                v-model="certForm.end_date"
                                dateFormat="yy-mm-dd"
                                placeholder="Optional"
                                show-clear
                                fluid
                            />
                            <InputError :message="certForm.errors.end_date" />
                        </div>
                    </div>

                    <!-- Diagnosis text area -->
                    <div class="field">
                        <label for="diagnosis" class="form-label font-semibold">Diagnosis / Condition Details</label>
                        <Textarea
                            id="diagnosis"
                            v-model="certForm.diagnosis"
                            rows="2"
                            placeholder="State the medical condition or reason..."
                            fluid
                        />
                        <InputError :message="certForm.errors.diagnosis" />
                    </div>

                    <!-- Row: Charge & Payment Status -->
                    <div class="form-row">
                        <div class="field col-6">
                            <label for="charge-amount" class="form-label font-semibold">Charge Amount (INR) <span class="required">*</span></label>
                            <InputNumber
                                id="charge-amount"
                                v-model="certForm.charge_amount"
                                mode="decimal"
                                :minFractionDigits="2"
                                :maxFractionDigits="2"
                                :min="0"
                                placeholder="0.00"
                                fluid
                            />
                            <InputError :message="certForm.errors.charge_amount" />
                        </div>
                        <div class="field col-6">
                            <label for="payment-status" class="form-label font-semibold">Payment Status <span class="required">*</span></label>
                            <Select
                                id="payment-status"
                                v-model="certForm.payment_status"
                                :options="paymentStatusOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Select Status"
                                fluid
                            />
                            <InputError :message="certForm.errors.payment_status" />
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="field">
                        <label for="notes" class="form-label font-semibold">Internal Remarks / Notes</label>
                        <Textarea
                            id="notes"
                            v-model="certForm.notes"
                            rows="2"
                            placeholder="Any internal administration remarks..."
                            fluid
                        />
                        <InputError :message="certForm.errors.notes" />
                    </div>

                    <!-- Status (only edit mode) -->
                    <div class="field" v-if="isCertEditMode">
                        <label for="cert-status" class="form-label font-semibold">Certificate Status</label>
                        <Select
                            id="cert-status"
                            v-model="certForm.status"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Select Status"
                            fluid
                        />
                        <InputError :message="certForm.errors.status" />
                    </div>
                </div>

                <div class="dialog-footer flex justify-end gap-2 mt-3">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showCertDialog = false"
                    />
                    <Button
                        v-if="isCertEditMode && editingCertId"
                        label="Print Certificate"
                        icon="pi pi-print"
                        severity="secondary"
                        outlined
                        @click="openPrintPreview({ id: editingCertId })"
                    />
                    <Button
                        type="submit"
                        :label="isCertEditMode ? 'Update' : 'Issue'"
                        :loading="certForm.processing"
                        severity="primary"
                    />
                </div>
            </form>
        </Dialog>

        <!-- DIALOG: Add / Edit Type -->
        <Dialog
            v-model:visible="showTypeDialog"
            :header="isTypeEditMode ? 'Edit Certificate Type' : 'Add Certificate Type'"
            :modal="true"
            class="types-dialog"
            :style="{ width: '450px' }"
            fluid
        >
            <form @submit.prevent="submitTypeForm" class="dialog-form">
                <div class="dialog-body">
                    <div class="field">
                        <label for="type-name" class="form-label font-semibold">Type Name <span class="required">*</span></label>
                        <InputText
                            id="type-name"
                            v-model="typeForm.name"
                            placeholder="e.g. Sick Leave Certificate"
                            fluid
                        />
                        <InputError :message="typeForm.errors.name" />
                    </div>

                    <div class="field">
                        <label for="type-desc" class="form-label font-semibold">Description</label>
                        <Textarea
                            id="type-desc"
                            v-model="typeForm.description"
                            rows="3"
                            placeholder="Briefly describe when this certificate is issued..."
                            fluid
                        />
                        <InputError :message="typeForm.errors.description" />
                    </div>

                    <div class="field">
                        <label for="default-charge" class="form-label font-semibold">Default Charge Amount (INR)</label>
                        <InputNumber
                            id="default-charge"
                            v-model="typeForm.default_charge"
                            mode="decimal"
                            :minFractionDigits="2"
                            :maxFractionDigits="2"
                            :min="0"
                            placeholder="0.00"
                            fluid
                        />
                        <InputError :message="typeForm.errors.default_charge" />
                    </div>
                </div>

                <div class="dialog-footer flex justify-end gap-2 mt-3">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showTypeDialog = false"
                    />
                    <Button
                        type="submit"
                        :label="isTypeEditMode ? 'Update' : 'Save'"
                        :loading="typeForm.processing"
                        severity="primary"
                    />
                </div>
            </form>
        </Dialog>


        <!-- Patient History Sidebar -->
        <PatientHistorySidebar
            v-model:visible="showHistorySidebar"
            :patient-id="historyPatientId"
        />
    </div>
</template>

<style scoped>
.certificates-page {
    gap: 1.5rem;
}

.certificates-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
}

@media (max-width: 768px) {
    .certificates-hero {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}

.tabs-header-container {
    display: flex;
    justify-content: flex-start;
    margin-bottom: 0.5rem;
}

.patient-cell,
.type-cell,
.charge-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.actions-cell {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.switch-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.filter-card {
    padding: 1.25rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr) auto;
    gap: 1rem;
    align-items: flex-end;
}

@media (max-width: 1024px) {
    .filter-grid {
        grid-template-columns: 1fr 1fr;
    }
    .filter-buttons {
        grid-column: span 2;
    }
}

@media (max-width: 640px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    .filter-buttons {
        grid-column: span 1;
    }
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.filter-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-color-secondary);
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
}

.search-input-wrapper {
    position: relative;
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-color-secondary);
}

.search-input {
    padding-left: 2.25rem;
}

.action-btn {
    width: 2rem;
    height: 2rem;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
    background: var(--surface-card);
    border: 1px dashed var(--surface-border);
}

.empty-state h3 {
    margin: 0.5rem 0 0.25rem;
    font-size: 1.1rem;
}

.empty-state p {
    color: var(--text-color-secondary);
    font-size: 0.85rem;
    max-width: 300px;
    margin: 0;
}

/* Dialog and form fields layout */
.dialog-form {
    padding-top: 0.5rem;
}

.dialog-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.dialog-footer {
    display: flex !important;
    flex-direction: row !important;
    justify-content: flex-end !important;
    gap: 0.5rem !important;
    border-top: 1px solid var(--surface-border);
    margin-top: 1.5rem;
    padding-top: 1.25rem;
}

.form-row {
    display: flex;
    gap: 1rem;
}

.col-6 {
    flex: 1;
    width: 50%;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.form-label {
    font-size: 0.85rem;
    color: var(--text-color);
}

.required {
    color: var(--red-500);
}

/* Letterhead and Print styles */
.print-preview-container {
    background: var(--surface-ground);
    padding: 2rem;
    display: flex;
    justify-content: center;
    overflow-y: auto;
    max-height: 60vh;
}

.print-certificate-area {
    background: #ffffff;
    color: #1e293b;
    padding: 3rem;
    width: 100%;
    max-width: 700px;
    min-height: 500px;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    font-family: 'Inter', sans-serif;
    border: 1px solid #e2e8f0;
}

.letterhead-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.clinic-branding {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.clinic-logo-img {
    width: 64px;
    height: 64px;
    object-fit: contain;
}

.clinic-logo-fallback {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
}

.clinic-metadata h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.25rem;
    color: #0f172a;
}

.doctor-name-txt {
    font-weight: 600;
    margin: 0;
    color: #334155;
    font-size: 0.95rem;
}

.doctor-details-txt, .doctor-registration-txt {
    margin: 0;
    color: #64748b;
    font-size: 0.8rem;
}

.clinic-contact-info p {
    margin: 0 0 0.25rem;
    font-size: 0.8rem;
    color: #64748b;
}

.letterhead-divider {
    height: 2px;
    background: #0f172a;
    margin: 1.5rem 0 2rem;
}

.certificate-body {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.certificate-main-title {
    font-size: 1.75rem;
    font-weight: 800;
    text-align: center;
    letter-spacing: 0.1em;
    margin: 0 0 1rem;
    color: #0f172a;
}

.cert-serial-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    color: #475569;
    border-bottom: 1px dashed #cbd5e1;
    padding-bottom: 0.5rem;
}

.certificate-statement-text {
    font-size: 1rem;
    line-height: 1.7;
    color: #334155;
    text-align: justify;
}

.certificate-statement-text p {
    margin: 0 0 1rem;
}

.certificate-signatures {
    display: flex;
    justify-content: flex-end;
    margin-top: 3rem;
}

.signature-block {
    text-align: center;
    width: 250px;
}

.sig-line {
    height: 1px;
    background: #94a3b8;
    margin-bottom: 0.5rem;
}

.sig-title {
    font-weight: 600;
    margin: 0;
    font-size: 0.85rem;
    color: #1e293b;
}

.sig-subtitle {
    margin: 0;
    font-size: 0.75rem;
    color: #64748b;
}

/* Global Print styles override */
@media print {
    body {
        background: #ffffff !important;
        color: #000000 !important;
    }
    /* Hide everything in layout except dialog certificate area */
    .layout-wrapper, .layout-mask, .p-dialog-mask, .p-toast, .print-controls, .p-dialog-header {
        display: none !important;
    }
    
    .p-dialog {
        position: static !important;
        width: 100% !important;
        height: auto !important;
        max-height: none !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
    }
    
    .p-dialog-content {
        padding: 0 !important;
        background: transparent !important;
    }
    
    .print-preview-container {
        padding: 0 !important;
        overflow: visible !important;
        max-height: none !important;
        background: transparent !important;
    }
    
    .print-certificate-area {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 !important;
    }
}
</style>
