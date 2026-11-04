<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AutoComplete from 'primevue/autocomplete';
import Avatar from 'primevue/avatar';
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
import { useConfirm } from 'primevue/useconfirm';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PatientController from '@/actions/App/Http/Controllers/Admin/PatientController';
import InputError from '@/components/InputError.vue';
import PatientHistorySidebar from '@/components/PatientHistorySidebar.vue';
import http from '@/lib/http';
import { dashboard } from '@/routes';
import { medicalCertificates } from '@/routes/admin';
import certificateTypesRoute from '@/routes/admin/certificate-types';
import { print as printRoute } from '@/routes/admin/medical-certificates';

type PatientDetails = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    age?: number | null;
    gender?: string | null;
    label: string;
};

type CertificateTypeItem = {
    id: number;
    name: string;
    description: string | null;
    default_charge: string | number;
    medical_certificates_count?: number;
};

type MedicalCertificateItem = {
    id: number;
    patient_id: number;
    patient_name: string;
    patient_phone: string | null;
    patient_email: string | null;
    patient_age?: number | null;
    patient_gender?: string | null;
    patient_address?: string | null;
    patient_city?: string | null;
    certificate_type_id: number;
    certificate_type_name: string;
    certificate_number: string;
    issue_date: string;
    start_date: string | null;
    end_date: string | null;
    duration_days?: number | null;
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

type SummaryMetrics = {
    total: number;
    active: number;
    paidAmount: number;
    unpaidCount: number;
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
    summary?: SummaryMetrics;
    clinic: ClinicDetails;
    filters: {
        search: string | null;
        status: string | null;
        payment_status: string | null;
        type_id: string | number | null;
    };
}>();

const mounted = ref(false);
onMounted(() => {
    mounted.value = true;
});

const activeTab = ref<'certificates' | 'types'>('certificates');

// Filter values
const searchFilter = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const paymentStatusFilter = ref(props.filters.payment_status ?? '');
const typeFilter = ref<number | null>(
    props.filters.type_id ? Number(props.filters.type_id) : null,
);

const hasActiveFilters = computed(() => {
    return Boolean(
        searchFilter.value.trim() ||
            statusFilter.value ||
            paymentStatusFilter.value ||
            typeFilter.value,
    );
});

const activeQuickSegment = computed<
    'all' | 'active' | 'unpaid' | 'paid' | 'void'
>(() => {
    if (statusFilter.value === 'void') {
        return 'void';
    }
    if (paymentStatusFilter.value === 'unpaid') {
        return 'unpaid';
    }
    if (paymentStatusFilter.value === 'paid') {
        return 'paid';
    }
    if (statusFilter.value === 'active') {
        return 'active';
    }
    return 'all';
});

// Dialog states
const showCertDialog = ref(false);
const isCertEditMode = ref(false);
const editingCertId = ref<number | null>(null);
const shouldPrintAfterCreate = ref(false);

const showTypeDialog = ref(false);
const isTypeEditMode = ref(false);
const editingTypeId = ref<number | null>(null);

const showHistorySidebar = ref(false);
const historyPatientId = ref<number | null>(null);

// Selected patient for autocomplete
const selectedPatient = ref<PatientDetails | null>(null);
const patientSuggestions = ref<PatientDetails[]>([]);

// Options lists
const tabOptions = computed(() => [
    {
        label: 'Issued Certificates',
        value: 'certificates',
        icon: 'pi pi-file-check',
        count: props.summary?.total ?? props.certificates.total ?? 0,
    },
    {
        label: 'Certificate Types',
        value: 'types',
        icon: 'pi pi-sliders-h',
        count: props.certificateTypes.length,
    },
]);

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Void', value: 'void' },
];

const paymentStatusOptions = [
    { label: 'Unpaid', value: 'unpaid' },
    { label: 'Paid', value: 'paid' },
];

const durationPresets = [
    { label: '1d', days: 1 },
    { label: '2d', days: 2 },
    { label: '3d', days: 3 },
    { label: '5d', days: 5 },
    { label: '7d', days: 7 },
    { label: '10d', days: 10 },
    { label: '14d', days: 14 },
];

const diagnosisPresets = [
    {
        label: 'Viral Fever / URTI',
        value: 'Acute Viral Fever & Upper Respiratory Tract Infection',
    },
    {
        label: 'Gastroenteritis',
        value: 'Acute Gastroenteritis with Mild Dehydration',
    },
    {
        label: 'Severe Migraine',
        value: 'Severe Migraine / Acute Cephalgia',
    },
    {
        label: 'Back / Lumbar Strain',
        value: 'Acute Lumbosacral Strain / Musculoskeletal Pain',
    },
    {
        label: 'Allergic Bronchitis',
        value: 'Seasonal Allergic Rhinitis & Bronchitis',
    },
    {
        label: 'Post-Procedure Rest',
        value: 'Post-Procedure Convalescence & Medical Rest',
    },
    {
        label: 'Routine Fitness',
        value: 'Routine Clinical Fitness Examination (No Contagious Illness)',
    },
];

const remarksPresets = [
    {
        label: 'Bed Rest Advice',
        value: 'Advised complete medical rest, adequate hydration, and compliance with prescribed medication. Expected to be fit to resume regular duties after completion of the rest period.',
    },
    {
        label: 'Fit to Resume Duty',
        value: 'Clinically examined today; vital signs are stable and recovery is satisfactory. Patient is medically fit to resume regular duties / classes without restrictions.',
    },
    {
        label: 'Light Duty Only',
        value: 'Advised modified / light duties only. Avoid heavy lifting, prolonged standing, or strenuous physical exertion during the recovery period.',
    },
    {
        label: 'OPD Attendance',
        value: 'Patient attended the Outpatient Department (OPD) for clinical evaluation, consultation, and therapeutic care.',
    },
    {
        label: 'Follow-up Before Joining',
        value: 'Advised clinical re-evaluation in OPD prior to resuming regular workplace or academic activities.',
    },
];

const standardTypePresets = [
    {
        name: 'Sick Leave Certificate',
        description:
            'Official medical rest certificate for absence from work, school, or college due to acute illness.',
        default_charge: 200,
    },
    {
        name: 'Medical Fitness Certificate',
        description:
            'Clearance certificate confirming recovery from illness or general physical fitness for employment/studies.',
        default_charge: 300,
    },
    {
        name: 'OPD Attendance Certificate',
        description:
            'Proof of outpatient clinic visit and consultation for employer or institutional records.',
        default_charge: 100,
    },
    {
        name: 'Light Duty / Modified Work Advice',
        description:
            'Clinical recommendation for temporary work modifications or avoidance of strenuous activity.',
        default_charge: 200,
    },
    {
        name: 'Travel & Sports Fitness Certificate',
        description:
            'Medical evaluation certifying fitness to undertake travel, trekking, or sports participation.',
        default_charge: 350,
    },
];

// Local timezone-safe date helpers (prevents UTC midnight off-by-one day shift)
function parseLocalDate(value: Date | string | null | undefined): Date | null {
    if (!value) {
        return null;
    }
    if (value instanceof Date) {
        return Number.isNaN(value.getTime())
            ? null
            : new Date(value.getFullYear(), value.getMonth(), value.getDate());
    }
    const trimmed = String(value).trim();
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(trimmed);
    if (match) {
        const year = Number(match[1]);
        const month = Number(match[2]) - 1;
        const day = Number(match[3]);
        return new Date(year, month, day);
    }
    const parsed = new Date(trimmed);
    return Number.isNaN(parsed.getTime())
        ? null
        : new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
}

function toDateString(date: Date | string | null | undefined): string | null {
    const parsed = parseLocalDate(date);
    if (!parsed) {
        return null;
    }
    const year = parsed.getFullYear();
    const month = `${parsed.getMonth() + 1}`.padStart(2, '0');
    const day = `${parsed.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatDisplayDate(dateStr: string | null | undefined): string {
    const parsed = parseLocalDate(dateStr);
    if (!parsed) {
        return '-';
    }
    return parsed.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatShortDisplayDate(dateStr: string | null | undefined): string {
    const parsed = parseLocalDate(dateStr);
    if (!parsed) {
        return '-';
    }
    return parsed.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
    });
}

function getInitials(name: string): string {
    if (!name) {
        return 'PT';
    }
    return (
        name
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || 'PT'
    );
}

// Forms setup
const certForm = useForm({
    patient_id: null as number | null,
    certificate_type_id: null as number | null,
    issue_date: parseLocalDate(new Date()) as Date | string | null,
    start_date: parseLocalDate(new Date()) as Date | string | null,
    end_date: parseLocalDate(new Date()) as Date | string | null,
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
    } else if (!newVal) {
        certForm.patient_id = null;
    }
});

function syncDefaultChargeForType(typeId: number | null): void {
    if (!typeId) {
        return;
    }
    const found = props.certificateTypes.find((t) => t.id === typeId);
    if (found) {
        certForm.charge_amount =
            typeof found.default_charge === 'string'
                ? parseFloat(found.default_charge) || 0
                : Number(found.default_charge || 0);
    }
}

watch(
    () => certForm.certificate_type_id,
    (newTypeId) => {
        if (newTypeId && !isCertEditMode.value) {
            syncDefaultChargeForType(newTypeId);
        }
    },
);

function applyTypePreset(preset: {
    name: string;
    description: string;
    default_charge: number;
}): void {
    typeForm.name = preset.name;
    typeForm.description = preset.description;
    typeForm.default_charge = preset.default_charge;
}

// Computed duration & resume date inside the form modal
const computedFormDuration = computed(() => {
    const start = parseLocalDate(certForm.start_date);
    const end = parseLocalDate(certForm.end_date);
    if (!start || !end || end < start) {
        return null;
    }
    const diffMs = end.getTime() - start.getTime();
    const days = Math.round(diffMs / (1000 * 60 * 60 * 24)) + 1;
    const resume = new Date(
        end.getFullYear(),
        end.getMonth(),
        end.getDate() + 1,
    );
    const resumeFormatted = resume.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

    return {
        days,
        resumeFormatted,
    };
});

function applyDurationPreset(days: number): void {
    const base =
        parseLocalDate(certForm.start_date) ??
        parseLocalDate(certForm.issue_date) ??
        parseLocalDate(new Date())!;
    const start = new Date(base.getFullYear(), base.getMonth(), base.getDate());
    const end = new Date(
        start.getFullYear(),
        start.getMonth(),
        start.getDate() + (days - 1),
    );

    certForm.start_date = start;
    certForm.end_date = end;
}

function clearValidityDates(): void {
    certForm.start_date = null;
    certForm.end_date = null;
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

function clearSelectedPatient(): void {
    selectedPatient.value = null;
    certForm.patient_id = null;
}

function viewPatientHistory(patientId: number | null): void {
    if (!patientId) {
        return;
    }
    historyPatientId.value = patientId;
    showHistorySidebar.value = true;
}

// Certificate actions
function openNewCert(preselectedTypeId?: number): void {
    isCertEditMode.value = false;
    editingCertId.value = null;
    shouldPrintAfterCreate.value = false;
    selectedPatient.value = null;
    certForm.reset();
    certForm.clearErrors();

    const today = parseLocalDate(new Date());
    certForm.issue_date = today;
    certForm.start_date = today;
    certForm.end_date = today;
    certForm.status = 'active';
    certForm.payment_status = 'unpaid';
    certForm.diagnosis = '';
    certForm.notes = '';

    const defaultTypeId =
        preselectedTypeId ??
        (props.certificateTypes.length > 0
            ? props.certificateTypes[0].id
            : null);
    certForm.certificate_type_id = defaultTypeId;
    if (defaultTypeId) {
        syncDefaultChargeForType(defaultTypeId);
    } else {
        certForm.charge_amount = 0;
    }

    showCertDialog.value = true;
}

function openEditCert(cert: MedicalCertificateItem): void {
    isCertEditMode.value = true;
    editingCertId.value = cert.id;
    shouldPrintAfterCreate.value = false;
    certForm.clearErrors();

    selectedPatient.value = {
        id: cert.patient_id,
        name: cert.patient_name,
        email: cert.patient_email,
        phone: cert.patient_phone,
        address: cert.patient_address ?? null,
        age: cert.patient_age ?? null,
        gender: cert.patient_gender ?? null,
        label: `${cert.patient_name} (#${cert.patient_id})`,
    };

    certForm.patient_id = cert.patient_id;
    certForm.certificate_type_id = cert.certificate_type_id;
    certForm.issue_date =
        parseLocalDate(cert.issue_date) ?? parseLocalDate(new Date());
    certForm.start_date = parseLocalDate(cert.start_date);
    certForm.end_date = parseLocalDate(cert.end_date);
    certForm.diagnosis = cert.diagnosis || '';
    certForm.charge_amount =
        typeof cert.charge_amount === 'string'
            ? parseFloat(cert.charge_amount) || 0
            : Number(cert.charge_amount || 0);
    certForm.payment_status = cert.payment_status;
    certForm.notes = cert.notes || '';
    certForm.status = cert.status;

    showCertDialog.value = true;
}

function togglePaymentStatus(cert: MedicalCertificateItem): void {
    const nextPaymentStatus =
        cert.payment_status === 'paid' ? 'unpaid' : 'paid';
    router.patch(
        medicalCertificates.update(cert.id).url,
        {
            patient_id: cert.patient_id,
            certificate_type_id: cert.certificate_type_id,
            issue_date: cert.issue_date,
            start_date: cert.start_date,
            end_date: cert.end_date,
            diagnosis: cert.diagnosis,
            charge_amount: cert.charge_amount,
            payment_status: nextPaymentStatus,
            notes: cert.notes,
            status: cert.status,
        },
        {
            preserveScroll: true,
        },
    );
}

const confirm = useConfirm();

function toggleRecordStatus(cert: MedicalCertificateItem): void {
    const nextStatus = cert.status === 'active' ? 'void' : 'active';
    const actionWord =
        nextStatus === 'void' ? 'mark as VOID' : 'restore to ACTIVE';

    confirm.require({
        header:
            nextStatus === 'void'
                ? 'Void Medical Certificate'
                : 'Restore Medical Certificate',
        message: `Are you sure you want to ${actionWord} certificate "${cert.certificate_number}"?`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: nextStatus === 'void' ? 'Mark as Void' : 'Restore Active',
            severity: nextStatus === 'void' ? 'danger' : 'primary',
        },
        accept: () => {
            router.patch(
                medicalCertificates.update(cert.id).url,
                {
                    patient_id: cert.patient_id,
                    certificate_type_id: cert.certificate_type_id,
                    issue_date: cert.issue_date,
                    start_date: cert.start_date,
                    end_date: cert.end_date,
                    diagnosis: cert.diagnosis,
                    charge_amount: cert.charge_amount,
                    payment_status: cert.payment_status,
                    notes: cert.notes,
                    status: nextStatus,
                },
                {
                    preserveScroll: true,
                },
            );
        },
    });
}

function submitCertForm(printAfterSave = false): void {
    shouldPrintAfterCreate.value = printAfterSave;

    certForm.transform((data) => ({
        ...data,
        issue_date: toDateString(data.issue_date),
        start_date: toDateString(data.start_date),
        end_date: toDateString(data.end_date),
    }));

    if (isCertEditMode.value && editingCertId.value !== null) {
        const targetId = editingCertId.value;
        certForm.patch(medicalCertificates.update(targetId).url, {
            preserveScroll: true,
            onSuccess: () => {
                showCertDialog.value = false;
                certForm.reset();
                selectedPatient.value = null;
                if (shouldPrintAfterCreate.value) {
                    openPrintPreview({ id: targetId });
                }
            },
        });
    } else {
        certForm.post(medicalCertificates.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                showCertDialog.value = false;
                certForm.reset();
                selectedPatient.value = null;
                if (
                    shouldPrintAfterCreate.value &&
                    props.certificates.data.length > 0
                ) {
                    openPrintPreview({ id: props.certificates.data[0].id });
                }
            },
        });
    }
}

function deleteCert(cert: MedicalCertificateItem): void {
    confirm.require({
        header: 'Delete Medical Certificate',
        message: `Are you sure you want to permanently delete medical certificate "${cert.certificate_number}"?`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: 'Delete Certificate',
            severity: 'danger',
        },
        accept: () => {
            router.delete(medicalCertificates.destroy(cert.id).url, {
                preserveScroll: true,
            });
        },
    });
}

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

function applyCertFilters(): void {
    router.get(
        medicalCertificates().url,
        {
            search: searchFilter.value.trim() || undefined,
            status: statusFilter.value || undefined,
            payment_status: paymentStatusFilter.value || undefined,
            type_id: typeFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch(searchFilter, () => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    searchDebounceTimer = setTimeout(() => {
        applyCertFilters();
    }, 300);
});

watch([statusFilter, paymentStatusFilter, typeFilter], () => {
    applyCertFilters();
});

onBeforeUnmount(() => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});

function selectQuickSegment(
    segment: 'all' | 'active' | 'unpaid' | 'paid' | 'void',
): void {
    activeTab.value = 'certificates';
    switch (segment) {
        case 'all':
            statusFilter.value = '';
            paymentStatusFilter.value = '';
            break;
        case 'active':
            statusFilter.value = 'active';
            paymentStatusFilter.value = '';
            break;
        case 'unpaid':
            statusFilter.value = 'active';
            paymentStatusFilter.value = 'unpaid';
            break;
        case 'paid':
            statusFilter.value = 'active';
            paymentStatusFilter.value = 'paid';
            break;
        case 'void':
            statusFilter.value = 'void';
            paymentStatusFilter.value = '';
            break;
    }
}

function clearCertFilters(): void {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    searchFilter.value = '';
    statusFilter.value = '';
    paymentStatusFilter.value = '';
    typeFilter.value = null;
    applyCertFilters();
}

function goToPage(url: string | null): void {
    if (!url) {
        return;
    }
    router.get(
        url,
        {
            search: searchFilter.value.trim() || undefined,
            status: statusFilter.value || undefined,
            payment_status: paymentStatusFilter.value || undefined,
            type_id: typeFilter.value || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
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

function openNewTypeFromCertDialog(): void {
    showCertDialog.value = false;
    openNewType();
}

function openEditType(type: CertificateTypeItem): void {
    isTypeEditMode.value = true;
    editingTypeId.value = type.id;
    typeForm.clearErrors();

    typeForm.name = type.name;
    typeForm.description = type.description || '';
    typeForm.default_charge =
        typeof type.default_charge === 'string'
            ? parseFloat(type.default_charge) || 0
            : Number(type.default_charge || 0);

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
    const count = type.medical_certificates_count ?? 0;
    const warning =
        count > 0
            ? `Are you sure you want to delete "${type.name}"? This will also permanently delete ${count} issued certificate(s) of this type.`
            : `Are you sure you want to delete certificate type "${type.name}"?`;

    confirm.require({
        header: 'Delete Certificate Type',
        message: warning,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: 'Delete Type',
            severity: 'danger',
        },
        accept: () => {
            router.delete(certificateTypesRoute.destroy(type.id).url, {
                preserveScroll: true,
            });
        },
    });
}

// Print Preview
function openPrintPreview(cert: MedicalCertificateItem | { id: number }): void {
    window.open(printRoute(cert.id).url, '_blank');
}

// Formatters
function formatCurrency(amount: string | number): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(num || 0);
}

function getStatusSeverity(status: string): 'success' | 'danger' | 'secondary' {
    return status === 'active' ? 'success' : 'danger';
}
</script>

<template>
    <div class="page-grid certificates-page">
        <Head title="Medical Certificates Management" />

        <!-- Teleport Primary CTA into the Admin Page Header -->
        <Teleport v-if="mounted" to="#admin-header-actions">
            <div class="header-cta-group">
                <Button
                    label="Add Certificate Type"
                    icon="pi pi-sliders-h"
                    severity="secondary"
                    outlined
                    class="header-action-btn"
                    @click="openNewType"
                />
                <Button
                    label="Issue Certificate"
                    icon="pi pi-plus"
                    class="p-button-primary header-action-btn"
                    @click="openNewCert()"
                />
            </div>
        </Teleport>

        <!-- Interactive Summary KPI Strip -->
        <section v-if="props.summary" class="cert-kpi-grid">
            <article
                class="page-card cert-kpi-card cert-kpi-card--interactive"
                :class="{
                    'is-kpi-selected':
                        activeTab === 'certificates' &&
                        activeQuickSegment === 'all' &&
                        !typeFilter,
                }"
                @click="selectQuickSegment('all')"
            >
                <div class="cert-kpi-icon cert-kpi-icon--teal">
                    <i class="pi pi-file-check"></i>
                </div>
                <div class="cert-kpi-copy">
                    <span class="cert-kpi-label">Total Issued</span>
                    <strong class="cert-kpi-value">{{
                        props.summary.total
                    }}</strong>
                    <span class="cert-kpi-hint">All clinical certificates</span>
                </div>
            </article>

            <article
                class="page-card cert-kpi-card cert-kpi-card--interactive"
                :class="{
                    'is-kpi-selected':
                        activeTab === 'certificates' &&
                        activeQuickSegment === 'active',
                }"
                @click="selectQuickSegment('active')"
            >
                <div class="cert-kpi-icon cert-kpi-icon--green">
                    <i class="pi pi-verified"></i>
                </div>
                <div class="cert-kpi-copy">
                    <span class="cert-kpi-label">Active Records</span>
                    <strong class="cert-kpi-value">{{
                        props.summary.active
                    }}</strong>
                    <span class="cert-kpi-hint">Valid &amp; verified</span>
                </div>
            </article>

            <article
                class="page-card cert-kpi-card cert-kpi-card--interactive"
                :class="{
                    'is-kpi-selected':
                        activeTab === 'certificates' &&
                        activeQuickSegment === 'paid',
                }"
                @click="selectQuickSegment('paid')"
            >
                <div class="cert-kpi-icon cert-kpi-icon--blue">
                    <i class="pi pi-indian-rupee"></i>
                </div>
                <div class="cert-kpi-copy">
                    <span class="cert-kpi-label">Collected Fee</span>
                    <strong class="cert-kpi-value">{{
                        formatCurrency(props.summary.paidAmount)
                    }}</strong>
                    <span class="cert-kpi-hint">From paid certificates</span>
                </div>
            </article>

            <article
                class="page-card cert-kpi-card cert-kpi-card--interactive"
                :class="{
                    'is-kpi-selected':
                        activeTab === 'certificates' &&
                        activeQuickSegment === 'unpaid',
                }"
                @click="selectQuickSegment('unpaid')"
            >
                <div class="cert-kpi-icon cert-kpi-icon--amber">
                    <i class="pi pi-clock"></i>
                </div>
                <div class="cert-kpi-copy">
                    <span class="cert-kpi-label">Pending Unpaid</span>
                    <strong class="cert-kpi-value">{{
                        props.summary.unpaidCount
                    }}</strong>
                    <span class="cert-kpi-hint">Click to filter unpaid</span>
                </div>
            </article>
        </section>

        <!-- Main Unified Workspace Card -->
        <article class="page-card admin-table-card workspace-card">
            <!-- Top Workspace Toolbar: Tabs + Context Action -->
            <header class="workspace-toolbar">
                <div class="workspace-toolbar__left">
                    <SelectButton
                        v-model="activeTab"
                        :options="tabOptions"
                        option-label="label"
                        option-value="value"
                        :allow-empty="false"
                        class="tab-switcher"
                        aria-label="Certificate workspace tabs"
                    >
                        <template #option="{ option }">
                            <span class="switch-option">
                                <i :class="option.icon"></i>
                                <span>{{ option.label }}</span>
                                <span class="tab-count-pill">{{
                                    option.count
                                }}</span>
                            </span>
                        </template>
                    </SelectButton>
                </div>

                <div class="workspace-toolbar__right">
                    <div
                        v-if="activeTab === 'certificates'"
                        class="segment-pills"
                    >
                        <button
                            type="button"
                            class="segment-pill"
                            :class="{
                                'is-active': activeQuickSegment === 'all',
                            }"
                            @click="selectQuickSegment('all')"
                        >
                            All Records
                        </button>
                        <button
                            type="button"
                            class="segment-pill"
                            :class="{
                                'is-active': activeQuickSegment === 'active',
                            }"
                            @click="selectQuickSegment('active')"
                        >
                            <span class="dot dot--green"></span>
                            Active
                        </button>
                        <button
                            type="button"
                            class="segment-pill"
                            :class="{
                                'is-active': activeQuickSegment === 'unpaid',
                            }"
                            @click="selectQuickSegment('unpaid')"
                        >
                            <span class="dot dot--amber"></span>
                            Unpaid
                        </button>
                        <button
                            type="button"
                            class="segment-pill"
                            :class="{
                                'is-active': activeQuickSegment === 'paid',
                            }"
                            @click="selectQuickSegment('paid')"
                        >
                            <span class="dot dot--blue"></span>
                            Paid
                        </button>
                        <button
                            type="button"
                            class="segment-pill"
                            :class="{
                                'is-active': activeQuickSegment === 'void',
                            }"
                            @click="selectQuickSegment('void')"
                        >
                            <span class="dot dot--red"></span>
                            Voided
                        </button>
                        <Button
                            v-if="hasActiveFilters"
                            label="Clear"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            size="small"
                            class="reset-filter-btn"
                            @click="clearCertFilters"
                        />
                    </div>
                    <Button
                        v-else
                        label="New Certificate Type"
                        icon="pi pi-plus"
                        size="small"
                        class="p-button-primary toolbar-cta-btn"
                        @click="openNewType"
                    />
                </div>
            </header>

            <!-- TAB 1: Issued Certificates -->
            <div v-if="activeTab === 'certificates'" class="tab-pane-body">
                <!-- Integrated Filter Bar -->
                <div class="cert-filter-bar">
                    <div class="cert-filter-bar__inputs">
                        <div class="search-box-wrap">
                            <i class="pi pi-search search-icon"></i>
                            <InputText
                                id="search-input"
                                v-model="searchFilter"
                                placeholder="Search by patient name, certificate # (e.g. MC-00001), or phone..."
                                class="w-full search-input"
                            />
                            <button
                                v-if="searchFilter"
                                type="button"
                                class="search-clear-btn"
                                title="Clear search"
                                @click="searchFilter = ''"
                            >
                                <i class="pi pi-times"></i>
                            </button>
                        </div>

                        <div class="filter-select-wrap">
                            <Select
                                id="type-filter"
                                v-model="typeFilter"
                                :options="props.certificateTypes"
                                option-label="name"
                                option-value="id"
                                placeholder="All Certificate Types"
                                show-clear
                                fluid
                            />
                        </div>

                        <div class="filter-select-wrap">
                            <Select
                                id="payment-filter"
                                v-model="paymentStatusFilter"
                                :options="paymentStatusOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="All Payment Statuses"
                                show-clear
                                fluid
                            />
                        </div>

                        <div class="filter-select-wrap">
                            <Select
                                id="status-filter"
                                v-model="statusFilter"
                                :options="statusOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="All Record Statuses"
                                show-clear
                                fluid
                            />
                        </div>
                    </div>
                </div>

                <!-- Certificates DataTable -->
                <DataTable
                    v-if="props.certificates.data.length > 0"
                    :value="props.certificates.data"
                    responsive-layout="scroll"
                    row-hover
                    class="admin-table certificates-table"
                >
                    <Column
                        header="Certificate #"
                        style="min-width: 9.5rem; width: 10.5rem"
                    >
                        <template #body="{ data }">
                            <div class="cert-no-cell">
                                <span class="cert-serial-badge font-mono">
                                    {{ data.certificate_number }}
                                </span>
                                <span class="cert-date-sub">
                                    <i class="pi pi-calendar"></i>
                                    {{ formatDisplayDate(data.issue_date) }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <Column header="Patient" style="min-width: 14rem">
                        <template #body="{ data }">
                            <div class="patient-identity-cell">
                                <Avatar
                                    :label="getInitials(data.patient_name)"
                                    shape="circle"
                                    class="patient-cell-avatar"
                                />
                                <div class="patient-cell-copy">
                                    <div class="patient-name-row">
                                        <span class="patient-name font-bold">{{
                                            data.patient_name
                                        }}</span>
                                        <span
                                            v-if="data.patient_id"
                                            class="patient-id-chip"
                                        >
                                            #{{ data.patient_id }}
                                        </span>
                                    </div>
                                    <div class="patient-meta-row">
                                        <span v-if="data.patient_phone">{{
                                            data.patient_phone
                                        }}</span>
                                        <span
                                            v-if="
                                                data.patient_phone &&
                                                (data.patient_age ||
                                                    data.patient_gender)
                                            "
                                            >&bull;</span
                                        >
                                        <span v-if="data.patient_age"
                                            >{{ data.patient_age }}y</span
                                        >
                                        <span v-if="data.patient_gender">{{
                                            data.patient_gender
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column
                        header="Certificate Type &amp; Validity"
                        style="min-width: 17rem"
                    >
                        <template #body="{ data }">
                            <div class="type-cell">
                                <div class="type-header-row">
                                    <span class="type-name-badge">{{
                                        data.certificate_type_name
                                    }}</span>
                                    <span
                                        v-if="data.duration_days"
                                        class="duration-pill"
                                    >
                                        {{ data.duration_days }}
                                        {{
                                            data.duration_days === 1
                                                ? 'Day'
                                                : 'Days'
                                        }}
                                    </span>
                                </div>
                                <span
                                    v-if="data.diagnosis"
                                    class="diagnosis-preview"
                                    :title="data.diagnosis"
                                >
                                    {{ data.diagnosis }}
                                </span>
                                <span
                                    v-if="data.start_date && data.end_date"
                                    class="validity-range-text"
                                >
                                    <i class="pi pi-clock"></i>
                                    {{ formatDisplayDate(data.start_date) }}
                                    &rarr;
                                    {{ formatDisplayDate(data.end_date) }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <Column
                        header="Fee &amp; Payment"
                        style="min-width: 9.5rem; width: 10.5rem"
                    >
                        <template #body="{ data }">
                            <div class="charge-cell">
                                <span class="fee-amount">{{
                                    formatCurrency(data.charge_amount)
                                }}</span>
                                <button
                                    type="button"
                                    class="payment-toggle-btn"
                                    :class="
                                        data.payment_status === 'paid'
                                            ? 'payment-toggle-btn--paid'
                                            : 'payment-toggle-btn--unpaid'
                                    "
                                    v-tooltip.top="
                                        'Click to toggle Paid / Unpaid'
                                    "
                                    title="Click to toggle Paid / Unpaid"
                                    @click="togglePaymentStatus(data)"
                                >
                                    <i
                                        :class="
                                            data.payment_status === 'paid'
                                                ? 'pi pi-check-circle'
                                                : 'pi pi-clock'
                                        "
                                    ></i>
                                    <span>{{
                                        data.payment_status.toUpperCase()
                                    }}</span>
                                </button>
                            </div>
                        </template>
                    </Column>

                    <Column
                        header="Status"
                        style="min-width: 7.5rem; width: 8rem"
                    >
                        <template #body="{ data }">
                            <button
                                type="button"
                                class="status-tag-btn"
                                v-tooltip.top="'Click to toggle Active / Void'"
                                title="Click to toggle Active / Void"
                                @click="toggleRecordStatus(data)"
                            >
                                <Tag
                                    :value="data.status.toUpperCase()"
                                    :severity="getStatusSeverity(data.status)"
                                    rounded
                                />
                            </button>
                        </template>
                    </Column>

                    <Column
                        header="Actions"
                        class="text-right"
                        style="width: 11.5rem; min-width: 11.5rem"
                    >
                        <template #body="{ data }">
                            <div class="actions-cell">
                                <Button
                                    icon="pi pi-print"
                                    severity="info"
                                    outlined
                                    size="small"
                                    v-tooltip.top="'Print / Save PDF'"
                                    title="Print / Save PDF"
                                    class="action-btn"
                                    @click="openPrintPreview(data)"
                                />
                                <Button
                                    icon="pi pi-history"
                                    severity="secondary"
                                    outlined
                                    size="small"
                                    v-tooltip.top="'Patient Clinical History'"
                                    title="Patient Clinical History"
                                    class="action-btn"
                                    @click="viewPatientHistory(data.patient_id)"
                                />
                                <Button
                                    icon="pi pi-pencil"
                                    severity="secondary"
                                    outlined
                                    size="small"
                                    v-tooltip.top="'Edit Certificate'"
                                    title="Edit Certificate"
                                    class="action-btn"
                                    @click="openEditCert(data)"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    outlined
                                    size="small"
                                    v-tooltip.top="'Delete Certificate'"
                                    title="Delete Certificate"
                                    class="action-btn"
                                    @click="deleteCert(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <!-- Empty State -->
                <div v-else class="empty-state">
                    <div class="empty-state__icon">
                        <i class="pi pi-file-check"></i>
                    </div>
                    <h3>No medical certificates found</h3>
                    <p v-if="hasActiveFilters">
                        No issued certificates match your active search or
                        filter criteria.
                    </p>
                    <p v-else>
                        Start by issuing a medical sick leave, fitness
                        clearance, or OPD attendance certificate.
                    </p>
                    <div class="empty-state__actions">
                        <Button
                            v-if="hasActiveFilters"
                            label="Reset Filters"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            size="small"
                            class="empty-action-btn"
                            @click="clearCertFilters"
                        />
                        <Button
                            v-else
                            label="Issue First Certificate"
                            icon="pi pi-plus"
                            size="small"
                            class="p-button-primary empty-action-btn"
                            @click="openNewCert()"
                        />
                    </div>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="props.certificates.data.length > 0"
                    class="admin-pagination-container cert-pagination-footer"
                >
                    <span class="pagination-summary">
                        Showing
                        <strong>{{ props.certificates.data.length }}</strong> of
                        <strong>{{ props.certificates.total }}</strong>
                        certificates
                    </span>

                    <div
                        v-if="
                            props.certificates.links &&
                            props.certificates.links.length > 3
                        "
                        class="admin-pagination"
                    >
                        <button
                            v-for="link in props.certificates.links"
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
            </div>

            <!-- TAB 2: Certificate Types -->
            <div v-else class="tab-pane-body">
                <div class="types-intro-bar">
                    <div>
                        <h4 class="types-intro-bar__title">
                            Configured Certificate Templates &amp; Pricing
                        </h4>
                        <p class="types-intro-bar__subtitle">
                            Define standard certificate types and default fees.
                            Staff can override fees when issuing individual
                            certificates.
                        </p>
                    </div>
                </div>

                <DataTable
                    v-if="props.certificateTypes.length > 0"
                    :value="props.certificateTypes"
                    responsive-layout="scroll"
                    row-hover
                    class="admin-table"
                >
                    <Column header="Certificate Type" style="min-width: 14rem">
                        <template #body="{ data }">
                            <div class="type-title-cell">
                                <span class="type-row-icon">
                                    <i class="pi pi-file-check"></i>
                                </span>
                                <span class="font-bold">{{ data.name }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column
                        header="Clinical Usage Description"
                        style="min-width: 20rem"
                    >
                        <template #body="{ data }">
                            <span class="text-secondary">{{
                                data.description || 'No description provided'
                            }}</span>
                        </template>
                    </Column>

                    <Column
                        header="Default Fee"
                        style="min-width: 9rem; width: 10rem"
                    >
                        <template #body="{ data }">
                            <span class="fee-amount">{{
                                formatCurrency(data.default_charge)
                            }}</span>
                        </template>
                    </Column>

                    <Column
                        header="Issued Count"
                        style="min-width: 8rem; width: 9rem"
                    >
                        <template #body="{ data }">
                            <Tag
                                :value="`${data.medical_certificates_count ?? 0} Issued`"
                                severity="secondary"
                                rounded
                            />
                        </template>
                    </Column>

                    <Column
                        header="Actions"
                        class="text-right"
                        style="width: 14rem; min-width: 14rem"
                    >
                        <template #body="{ data }">
                            <div class="actions-cell">
                                <Button
                                    label="Issue"
                                    icon="pi pi-plus"
                                    severity="primary"
                                    outlined
                                    size="small"
                                    class="issue-type-inline-btn"
                                    @click="openNewCert(data.id)"
                                />
                                <Button
                                    icon="pi pi-pencil"
                                    severity="secondary"
                                    outlined
                                    size="small"
                                    v-tooltip.top="'Edit Type'"
                                    title="Edit Type"
                                    class="action-btn"
                                    @click="openEditType(data)"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    outlined
                                    size="small"
                                    v-tooltip.top="'Delete Type'"
                                    title="Delete Type"
                                    class="action-btn"
                                    @click="deleteType(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <div v-else class="empty-state">
                    <div class="empty-state__icon">
                        <i class="pi pi-sliders-h"></i>
                    </div>
                    <h3>No certificate types defined yet</h3>
                    <p>
                        Create your first certificate type (such as Sick Leave
                        or Medical Fitness) to start issuing certificates.
                    </p>
                    <div class="empty-state__actions">
                        <Button
                            label="Add Certificate Type"
                            icon="pi pi-plus"
                            size="small"
                            class="p-button-primary empty-action-btn"
                            @click="openNewType"
                        />
                    </div>
                </div>
            </div>
        </article>

        <!-- DIALOG: Issue / Edit Certificate -->
        <Dialog
            v-model:visible="showCertDialog"
            :header="
                isCertEditMode
                    ? 'Edit Medical Certificate'
                    : 'Issue Medical Certificate'
            "
            :modal="true"
            :draggable="false"
            dismissable-mask
            class="certificates-dialog"
            :style="{ width: '660px', maxWidth: '95vw' }"
        >
            <form
                @submit.prevent="submitCertForm(false)"
                class="dialog-form"
            >
                <div class="dialog-body">
                    <!-- Section 1: Patient & Certificate Type -->
                    <div class="dialog-grid-2">
                        <div class="field">
                            <div class="field-header-inline">
                                <label
                                    for="patient-search"
                                    class="form-label font-semibold"
                                >
                                    Patient <span class="required">*</span>
                                </label>
                                <button
                                    v-if="selectedPatient && selectedPatient.id"
                                    type="button"
                                    class="inline-link-btn"
                                    @click="
                                        viewPatientHistory(selectedPatient.id)
                                    "
                                >
                                    <i class="pi pi-history"></i> Clinical
                                    History
                                </button>
                            </div>

                            <AutoComplete
                                id="patient-search"
                                v-model="selectedPatient"
                                :suggestions="patientSuggestions"
                                option-label="label"
                                placeholder="Type patient name, phone, or ID..."
                                complete-on-focus
                                fluid
                                :disabled="isCertEditMode"
                                @complete="searchPatients"
                            />
                            <InputError :message="certForm.errors.patient_id" />
                        </div>

                        <div class="field">
                            <div class="field-header-inline">
                                <label
                                    for="cert-type"
                                    class="form-label font-semibold"
                                >
                                    Certificate Type
                                    <span class="required">*</span>
                                </label>
                                <button
                                    type="button"
                                    class="inline-link-btn"
                                    @click="openNewTypeFromCertDialog"
                                >
                                    <i class="pi pi-plus"></i> New Type
                                </button>
                            </div>
                            <Select
                                id="cert-type"
                                v-model="certForm.certificate_type_id"
                                :options="props.certificateTypes"
                                option-label="name"
                                option-value="id"
                                placeholder="Select Certificate Type"
                                fluid
                            />
                            <InputError
                                :message="certForm.errors.certificate_type_id"
                            />
                        </div>
                    </div>

                    <!-- Selected Patient Summary Banner -->
                    <div
                        v-if="
                            selectedPatient &&
                            typeof selectedPatient === 'object' &&
                            selectedPatient.id
                        "
                        class="selected-patient-banner"
                    >
                        <div class="selected-patient-banner__left">
                            <Avatar
                                :label="getInitials(selectedPatient.name)"
                                shape="circle"
                                class="patient-cell-avatar"
                            />
                            <div class="selected-patient-banner__info">
                                <div class="selected-patient-banner__title">
                                    <strong>{{ selectedPatient.name }}</strong>
                                    <span class="patient-id-chip"
                                        >ID #{{ selectedPatient.id }}</span
                                    >
                                </div>
                                <div class="selected-patient-banner__meta">
                                    <span v-if="selectedPatient.phone">
                                        <i class="pi pi-phone"></i>
                                        {{ selectedPatient.phone }}
                                    </span>
                                    <span v-if="selectedPatient.email">
                                        <i class="pi pi-envelope"></i>
                                        {{ selectedPatient.email }}
                                    </span>
                                    <span v-if="selectedPatient.address">
                                        <i class="pi pi-map-marker"></i>
                                        {{ selectedPatient.address }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="selected-patient-banner__actions">
                            <button
                                v-if="!isCertEditMode"
                                type="button"
                                class="banner-action-pill"
                                title="Select a different patient"
                                @click="clearSelectedPatient"
                            >
                                <i class="pi pi-times"></i> Change
                            </button>
                        </div>
                    </div>

                    <!-- Section 2: Dates & Rest Duration -->
                    <div class="validity-group">
                        <div class="validity-group__header">
                            <div class="validity-group__title-wrap">
                                <span class="validity-group__title">
                                    <i class="pi pi-calendar"></i>
                                    Validity &amp; Rest Period
                                </span>
                                <span
                                    v-if="computedFormDuration"
                                    class="duration-inline-tag"
                                >
                                    {{ computedFormDuration.days }}
                                    {{
                                        computedFormDuration.days === 1
                                            ? 'Day'
                                            : 'Days'
                                    }}
                                    &bull; Resume
                                    {{ computedFormDuration.resumeFormatted }}
                                </span>
                            </div>

                            <div class="duration-mini-strip">
                                <span class="duration-mini-strip__label"
                                    >Preset:</span
                                >
                                <button
                                    v-for="preset in durationPresets"
                                    :key="preset.days"
                                    type="button"
                                    class="duration-mini-btn"
                                    :class="{
                                        'is-active':
                                            computedFormDuration?.days ===
                                            preset.days,
                                    }"
                                    @click="applyDurationPreset(preset.days)"
                                >
                                    {{ preset.label }}
                                </button>
                                <button
                                    v-if="
                                        certForm.start_date || certForm.end_date
                                    "
                                    type="button"
                                    class="duration-mini-btn duration-mini-btn--clear"
                                    title="Clear From/To dates (e.g., for OPD attendance)"
                                    @click="clearValidityDates"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        <div class="dialog-grid-3">
                            <div class="field">
                                <label for="issue-date" class="form-label">
                                    Issue Date <span class="required">*</span>
                                </label>
                                <DatePicker
                                    id="issue-date"
                                    v-model="certForm.issue_date"
                                    dateFormat="yy-mm-dd"
                                    placeholder="YYYY-MM-DD"
                                    fluid
                                />
                                <InputError
                                    :message="certForm.errors.issue_date"
                                />
                            </div>
                            <div class="field">
                                <label for="start-date" class="form-label"
                                    >From Date (Optional)</label
                                >
                                <DatePicker
                                    id="start-date"
                                    v-model="certForm.start_date"
                                    dateFormat="yy-mm-dd"
                                    placeholder="Start of rest"
                                    show-clear
                                    fluid
                                />
                                <InputError
                                    :message="certForm.errors.start_date"
                                />
                            </div>
                            <div class="field">
                                <label for="end-date" class="form-label"
                                    >To Date (Inclusive)</label
                                >
                                <DatePicker
                                    id="end-date"
                                    v-model="certForm.end_date"
                                    dateFormat="yy-mm-dd"
                                    placeholder="End of rest"
                                    show-clear
                                    fluid
                                />
                                <InputError
                                    :message="certForm.errors.end_date"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Diagnosis with 1-Click Quick-Fill Chips -->
                    <div class="field">
                        <div class="field-header-inline">
                            <label
                                for="diagnosis"
                                class="form-label font-semibold"
                            >
                                Clinical Diagnosis / Condition
                            </label>
                            <button
                                v-if="certForm.diagnosis"
                                type="button"
                                class="inline-clear-link"
                                @click="certForm.diagnosis = ''"
                            >
                                Clear
                            </button>
                        </div>

                        <div class="quick-preset-chips">
                            <button
                                v-for="preset in diagnosisPresets"
                                :key="preset.label"
                                type="button"
                                class="preset-chip"
                                :class="{
                                    'is-active':
                                        certForm.diagnosis === preset.value,
                                }"
                                @click="certForm.diagnosis = preset.value"
                            >
                                + {{ preset.label }}
                            </button>
                        </div>

                        <Textarea
                            id="diagnosis"
                            v-model="certForm.diagnosis"
                            rows="2"
                            placeholder="Enter clinical diagnosis or select a preset above..."
                            fluid
                        />
                        <InputError :message="certForm.errors.diagnosis" />
                    </div>

                    <!-- Section 4: Medical Advice / Remarks with 1-Click Quick-Fill Chips -->
                    <div class="field">
                        <div class="field-header-inline">
                            <label for="notes" class="form-label font-semibold">
                                Medical Advice &amp; Remarks
                            </label>
                            <button
                                v-if="certForm.notes"
                                type="button"
                                class="inline-clear-link"
                                @click="certForm.notes = ''"
                            >
                                Clear
                            </button>
                        </div>

                        <div class="quick-preset-chips">
                            <button
                                v-for="preset in remarksPresets"
                                :key="preset.label"
                                type="button"
                                class="preset-chip"
                                :class="{
                                    'is-active':
                                        certForm.notes === preset.value,
                                }"
                                @click="certForm.notes = preset.value"
                            >
                                + {{ preset.label }}
                            </button>
                        </div>

                        <Textarea
                            id="notes"
                            v-model="certForm.notes"
                            rows="2"
                            placeholder="Clinical rest instructions, duty modifications, or remarks printed on certificate..."
                            fluid
                        />
                        <InputError :message="certForm.errors.notes" />
                    </div>

                    <!-- Section 5: Billing & Status -->
                    <div
                        :class="
                            isCertEditMode ? 'dialog-grid-3' : 'dialog-grid-2'
                        "
                    >
                        <div class="field">
                            <label
                                for="charge-amount"
                                class="form-label font-semibold"
                            >
                                Certificate Fee (INR)
                                <span class="required">*</span>
                            </label>
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
                            <InputError
                                :message="certForm.errors.charge_amount"
                            />
                        </div>

                        <div class="field">
                            <label
                                for="payment-status"
                                class="form-label font-semibold"
                            >
                                Payment Status <span class="required">*</span>
                            </label>
                            <Select
                                id="payment-status"
                                v-model="certForm.payment_status"
                                :options="paymentStatusOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Select Status"
                                fluid
                            />
                            <InputError
                                :message="certForm.errors.payment_status"
                            />
                        </div>

                        <div v-if="isCertEditMode" class="field">
                            <label
                                for="cert-status"
                                class="form-label font-semibold"
                            >
                                Record Status
                            </label>
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
                </div>

                <div class="dialog-footer">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        outlined
                        class="dialog-footer-btn"
                        @click="showCertDialog = false"
                    />
                    <Button
                        v-if="isCertEditMode && editingCertId"
                        type="button"
                        label="Print Preview"
                        icon="pi pi-print"
                        severity="secondary"
                        outlined
                        class="dialog-footer-btn"
                        @click="openPrintPreview({ id: editingCertId })"
                    />
                    <Button
                        v-if="!isCertEditMode"
                        type="button"
                        label="Issue &amp; Print"
                        icon="pi pi-print"
                        severity="secondary"
                        outlined
                        :loading="certForm.processing && shouldPrintAfterCreate"
                        class="dialog-footer-btn"
                        @click="submitCertForm(true)"
                    />
                    <Button
                        type="submit"
                        :label="
                            isCertEditMode
                                ? 'Save Changes'
                                : 'Issue Certificate'
                        "
                        icon="pi pi-check"
                        :loading="
                            certForm.processing && !shouldPrintAfterCreate
                        "
                        severity="primary"
                        class="dialog-footer-btn"
                    />
                </div>
            </form>
        </Dialog>

        <!-- DIALOG: Add / Edit Certificate Type -->
        <Dialog
            v-model:visible="showTypeDialog"
            :header="
                isTypeEditMode
                    ? 'Edit Certificate Type'
                    : 'Add Certificate Type'
            "
            :modal="true"
            :draggable="false"
            dismissable-mask
            class="types-dialog"
            :style="{ width: '520px', maxWidth: '95vw' }"
        >
            <form @submit.prevent="submitTypeForm" class="dialog-form">
                <div class="dialog-body">
                    <div v-if="!isTypeEditMode" class="field">
                        <span class="form-label font-semibold">
                            Quick-Fill Standard Template
                        </span>
                        <div class="quick-preset-chips">
                            <button
                                v-for="preset in standardTypePresets"
                                :key="preset.name"
                                type="button"
                                class="preset-chip"
                                :class="{
                                    'is-active': typeForm.name === preset.name,
                                }"
                                @click="applyTypePreset(preset)"
                            >
                                + {{ preset.name }}
                            </button>
                        </div>
                    </div>

                    <div class="field">
                        <label
                            for="type-name"
                            class="form-label font-semibold"
                        >
                            Type Name <span class="required">*</span>
                        </label>
                        <InputText
                            id="type-name"
                            v-model="typeForm.name"
                            placeholder="e.g. Sick Leave Certificate"
                            fluid
                        />
                        <InputError :message="typeForm.errors.name" />
                    </div>

                    <div class="field">
                        <label
                            for="type-desc"
                            class="form-label font-semibold"
                        >
                            Description
                        </label>
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
                        <label
                            for="default-charge"
                            class="form-label font-semibold"
                        >
                            Default Fee Amount (INR)
                            <span class="required">*</span>
                        </label>
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

                <div class="dialog-footer">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        outlined
                        class="dialog-footer-btn"
                        @click="showTypeDialog = false"
                    />
                    <Button
                        type="submit"
                        :label="isTypeEditMode ? 'Update Type' : 'Save Type'"
                        icon="pi pi-check"
                        :loading="typeForm.processing"
                        severity="primary"
                        class="dialog-footer-btn"
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
    gap: 1.15rem;
}

/* Header Teleported CTA Buttons */
.header-cta-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.header-cta-group :deep(.p-button),
.header-action-btn {
    width: auto !important;
    white-space: nowrap !important;
}

/* KPI Strip */
.cert-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.cert-kpi-card {
    display: flex;
    align-items: center;
    gap: 0.95rem;
    padding: 1.1rem 1.2rem;
    transition:
        border-color 0.15s ease,
        transform 0.15s ease,
        box-shadow 0.15s ease;
}

.cert-kpi-card--interactive {
    cursor: pointer;
}

.cert-kpi-card--interactive:hover {
    border-color: color-mix(
        in srgb,
        var(--primary-color) 45%,
        var(--surface-border)
    );
    transform: translateY(-1px);
}

.cert-kpi-card.is-kpi-selected {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 1px
        color-mix(in srgb, var(--primary-color) 35%, transparent);
}

.cert-kpi-icon {
    width: 2.85rem;
    height: 2.85rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.cert-kpi-icon--teal {
    background: color-mix(in srgb, #0d9488 12%, transparent);
    color: #0d9488;
}

.cert-kpi-icon--green {
    background: color-mix(in srgb, #16a34a 12%, transparent);
    color: #16a34a;
}

.cert-kpi-icon--blue {
    background: color-mix(in srgb, #2563eb 12%, transparent);
    color: #2563eb;
}

.cert-kpi-icon--amber {
    background: color-mix(in srgb, #d97706 14%, transparent);
    color: #d97706;
}

.cert-kpi-copy {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.cert-kpi-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-color-secondary);
}

.cert-kpi-value {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--text-color);
    line-height: 1.25;
}

.cert-kpi-hint {
    font-size: 0.72rem;
    color: var(--text-color-secondary);
    margin-top: 0.1rem;
}

/* Main Workspace Card */
.workspace-card {
    padding: 0;
    overflow: hidden;
}

.workspace-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--surface-border);
    background: var(--surface-card);
    flex-wrap: wrap;
}

.workspace-toolbar__left :deep(.p-selectbutton) {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.workspace-toolbar__left :deep(.p-togglebutton) {
    width: auto !important;
    border-radius: 8px !important;
    padding: 0.48rem 0.9rem !important;
}

.switch-option {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    font-weight: 700;
    white-space: nowrap;
}

.tab-count-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.45rem;
    height: 1.3rem;
    padding: 0 0.4rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    background: color-mix(in srgb, currentColor 12%, transparent);
}

.workspace-toolbar__right :deep(.p-button),
.toolbar-cta-btn {
    width: auto !important;
    white-space: nowrap !important;
}

/* Filter Bar inside Workspace Card */
.cert-filter-bar {
    padding: 1rem 1.25rem;
    background: color-mix(in srgb, var(--surface-ground) 55%, var(--surface-card));
    border-bottom: 1px solid var(--surface-border);
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.cert-filter-bar__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.segment-pills {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.segment-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.36rem 0.75rem;
    border-radius: 999px;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-color-secondary);
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.segment-pill:hover {
    border-color: var(--primary-color);
    color: var(--text-color);
}

.segment-pill.is-active {
    background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-card));
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.dot {
    width: 0.48rem;
    height: 0.48rem;
    border-radius: 50%;
    display: inline-block;
}

.dot--green {
    background: #16a34a;
}

.dot--amber {
    background: #d97706;
}

.dot--blue {
    background: #2563eb;
}

.dot--red {
    background: #dc2626;
}

.reset-filter-btn {
    width: auto !important;
    white-space: nowrap !important;
}

.cert-filter-bar__inputs {
    display: grid;
    grid-template-columns: 1.6fr repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    align-items: center;
}

.search-box-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.search-icon {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-color-secondary);
    z-index: 1;
    pointer-events: none;
}

:deep(.search-input) {
    padding-left: 2.4rem !important;
    padding-right: 2.2rem !important;
    width: 100%;
}

.search-clear-btn {
    position: absolute;
    right: 0.6rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-color-secondary);
    cursor: pointer;
    padding: 0.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.search-clear-btn:hover {
    color: var(--text-color);
    background: var(--surface-hover);
}

/* Table Cells */
.cert-no-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.cert-serial-badge {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    font-size: 0.82rem;
    font-weight: 800;
    padding: 0.18rem 0.5rem;
    border-radius: 6px;
    background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-card));
    color: var(--primary-color);
    border: 1px solid color-mix(in srgb, var(--primary-color) 24%, transparent);
}

.cert-date-sub {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.74rem;
    color: var(--text-color-secondary);
}

.cert-date-sub i {
    font-size: 0.7rem;
}

.patient-identity-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.patient-cell-avatar {
    width: 2.35rem !important;
    height: 2.35rem !important;
    background: color-mix(
        in srgb,
        var(--primary-color) 15%,
        var(--surface-card)
    ) !important;
    color: var(--primary-color) !important;
    font-weight: 800 !important;
    font-size: 0.82rem !important;
    flex-shrink: 0 !important;
}

.patient-cell-copy {
    display: flex;
    flex-direction: column;
    gap: 0.18rem;
    min-width: 0;
}

.patient-name-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.patient-id-chip {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.08rem 0.4rem;
    border-radius: 4px;
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
    color: var(--text-color-secondary);
}

.patient-meta-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    color: var(--text-color-secondary);
    flex-wrap: wrap;
}

.type-cell,
.charge-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.type-header-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.type-name-badge {
    font-weight: 700;
    color: var(--text-color);
    font-size: 0.88rem;
}

.duration-pill {
    font-size: 0.68rem;
    font-weight: 800;
    background: color-mix(in srgb, #0d9488 12%, var(--surface-card));
    color: #0d9488;
    border: 1px solid color-mix(in srgb, #0d9488 30%, transparent);
    padding: 0.1rem 0.48rem;
    border-radius: 999px;
}

.diagnosis-preview {
    font-size: 0.78rem;
    color: var(--text-color-secondary);
    max-width: 280px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.validity-range-text {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.73rem;
    font-weight: 600;
    color: var(--text-color-secondary);
}

.validity-range-text i {
    font-size: 0.68rem;
}

.fee-amount {
    font-weight: 800;
    font-size: 0.92rem;
    color: var(--text-color);
}

.payment-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    width: fit-content;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.15s ease;
}

.payment-toggle-btn--paid {
    background: color-mix(in srgb, #16a34a 14%, var(--surface-card));
    color: #15803d;
    border-color: color-mix(in srgb, #16a34a 35%, transparent);
}

.payment-toggle-btn--unpaid {
    background: color-mix(in srgb, #d97706 14%, var(--surface-card));
    color: #b45309;
    border-color: color-mix(in srgb, #d97706 35%, transparent);
}

.status-tag-btn {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    display: inline-flex;
    transition: transform 0.12s ease;
}

.status-tag-btn:hover {
    transform: scale(1.04);
}

.actions-cell {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
}

.actions-cell :deep(.action-btn) {
    width: 2.15rem !important;
    height: 2.15rem !important;
    min-width: 2.15rem !important;
    padding: 0 !important;
    flex-shrink: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.actions-cell :deep(.issue-type-inline-btn) {
    width: auto !important;
    padding: 0.35rem 0.65rem !important;
    font-size: 0.78rem !important;
    white-space: nowrap !important;
}

/* Types Tab Bar */
.types-intro-bar {
    padding: 1rem 1.25rem;
    background: color-mix(in srgb, var(--surface-ground) 55%, var(--surface-card));
    border-bottom: 1px solid var(--surface-border);
}

.types-intro-bar__title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text-color);
}

.types-intro-bar__subtitle {
    margin: 0.15rem 0 0;
    font-size: 0.78rem;
    color: var(--text-color-secondary);
}

.type-title-cell {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.type-row-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 6px;
    background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-card));
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.88rem;
    flex-shrink: 0;
}

/* Pagination Footer */
.cert-pagination-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 0.85rem 1.25rem;
}

.pagination-summary {
    font-size: 0.8rem;
    color: var(--text-color-secondary);
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3.5rem 1.5rem;
    text-align: center;
    background: var(--surface-card);
}

.empty-state__icon {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 50%;
    background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-ground));
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 0.75rem;
}

.empty-state h3 {
    margin: 0 0 0.3rem;
    font-size: 1.08rem;
    font-weight: 700;
    color: var(--text-color);
}

.empty-state p {
    color: var(--text-color-secondary);
    font-size: 0.85rem;
    max-width: 360px;
    margin: 0;
}

.empty-state__actions {
    margin-top: 1rem;
    display: flex;
    gap: 0.5rem;
}

.empty-state__actions :deep(.p-button),
.empty-action-btn {
    width: auto !important;
}

/* Dialog Styles */
.dialog-form {
    padding-top: 0.25rem;
}

.dialog-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.dialog-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.dialog-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.field-header-inline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.form-label {
    font-size: 0.8rem;
    color: var(--text-color);
}

.required {
    color: #ef4444;
}

.inline-link-btn,
.inline-clear-link {
    background: none;
    border: none;
    padding: 0;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--primary-color);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.inline-clear-link {
    color: var(--text-color-secondary);
}

.inline-link-btn:hover,
.inline-clear-link:hover {
    text-decoration: underline;
}

/* Selected Patient Banner inside Dialog */
.selected-patient-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.7rem 0.9rem;
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary-color) 6%, var(--surface-ground));
    border: 1px solid
        color-mix(in srgb, var(--primary-color) 24%, var(--surface-border));
}

.selected-patient-banner__left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.selected-patient-banner__info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.selected-patient-banner__title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.88rem;
    color: var(--text-color);
}

.selected-patient-banner__meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    font-size: 0.74rem;
    color: var(--text-color-secondary);
}

.selected-patient-banner__meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.banner-action-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.28rem 0.55rem;
    border-radius: 6px;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-color-secondary);
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    flex-shrink: 0;
}

.banner-action-pill:hover {
    border-color: #ef4444;
    color: #ef4444;
}

/* Validity Box */
.validity-group {
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 0.85rem 0.95rem;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}

.validity-group__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.validity-group__title-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.validity-group__title {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-color);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.duration-inline-tag {
    font-size: 0.7rem;
    font-weight: 800;
    background: color-mix(in srgb, #0d9488 12%, var(--surface-card));
    color: #0d9488;
    border: 1px solid color-mix(in srgb, #0d9488 30%, transparent);
    padding: 0.12rem 0.5rem;
    border-radius: 999px;
}

.duration-mini-strip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    flex-wrap: wrap;
}

.duration-mini-strip__label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-color-secondary);
    margin-right: 0.15rem;
}

.duration-mini-btn {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.48rem;
    border-radius: 5px;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-color);
    cursor: pointer;
    transition: all 0.12s ease;
}

.duration-mini-btn:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.duration-mini-btn.is-active {
    background: var(--primary-color);
    color: var(--primary-contrast-color);
    border-color: var(--primary-color);
}

.duration-mini-btn--clear {
    color: var(--text-color-secondary);
}

/* Quick Preset Chips */
.quick-preset-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.preset-chip {
    font-size: 0.71rem;
    font-weight: 600;
    padding: 0.22rem 0.55rem;
    border-radius: 999px;
    border: 1px solid var(--surface-border);
    background: var(--surface-ground);
    color: var(--text-color-secondary);
    cursor: pointer;
    transition: all 0.12s ease;
}

.preset-chip:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 8%, var(--surface-card));
}

.preset-chip.is-active {
    border-color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 14%, var(--surface-card));
    color: var(--primary-color);
    font-weight: 700;
}

/* Dialog Footer (Overrides global .p-button { width: 100% }) */
.dialog-footer {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 0.55rem !important;
    flex-wrap: wrap;
    border-top: 1px solid var(--surface-border);
    margin-top: 1.15rem;
    padding-top: 1rem;
}

.dialog-footer :deep(.p-button),
.dialog-footer-btn {
    width: auto !important;
    flex: 0 0 auto !important;
    white-space: nowrap !important;
}

@media (max-width: 1024px) {
    .cert-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .cert-filter-bar__inputs {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .cert-kpi-grid,
    .cert-filter-bar__inputs,
    .dialog-grid-2,
    .dialog-grid-3 {
        grid-template-columns: 1fr;
    }

    .workspace-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
