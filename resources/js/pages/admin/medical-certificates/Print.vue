<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed, ref } from 'vue';
import { medicalCertificates } from '@/routes/admin';

type Props = {
    certificate: {
        id: number;
        patient_id?: number;
        patient_name: string;
        patient_phone: string | null;
        patient_age?: number | null;
        patient_gender?: string | null;
        patient_address?: string | null;
        patient_city?: string | null;
        certificate_type_name?: string;
        certificate_number: string;
        issue_date: string;
        start_date: string | null;
        end_date: string | null;
        resume_date?: string | null;
        duration_days?: number | null;
        diagnosis: string | null;
        notes: string | null;
        charge_amount?: string | number | null;
        payment_status?: 'paid' | 'unpaid';
        status?: 'active' | 'void';
    };
    clinic: {
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

type TemplateMode =
    | 'auto'
    | 'sick_leave'
    | 'fitness'
    | 'attendance'
    | 'light_duty';

const templateMode = ref<TemplateMode>('auto');
const recipientText = ref('TO WHOM IT MAY CONCERN');
const showPatientGrid = ref(true);
const showPeriodSummary = ref(true);
const showPatientSignature = ref(true);
const showFeeReceipt = ref(false);
const showSalutationEditor = ref(false);
const logoLoadError = ref(false);

const resolvedLogoUrl = computed<string | null>(() => {
    const raw = props.clinic.logo_url;
    if (!raw || logoLoadError.value) {
        return null;
    }
    const storageIdx = raw.indexOf('/storage/');
    if (storageIdx !== -1) {
        return raw.slice(storageIdx);
    }
    return raw;
});

const doctorQualificationsLine = computed<string>(() => {
    return [props.clinic.doctor_qualifications, props.clinic.doctor_title]
        .map((item) => item?.trim())
        .filter(Boolean)
        .join(' • ');
});

const registrationLine = computed<string>(() => {
    const parts: string[] = [];

    if (props.clinic.doctor_registration_no?.trim()) {
        const raw = props.clinic.doctor_registration_no.trim();
        parts.push(/reg/i.test(raw) ? raw : `Reg. No.: ${raw}`);
    }

    if (props.clinic.clinic_registration_no?.trim()) {
        const raw = props.clinic.clinic_registration_no.trim();
        parts.push(/reg/i.test(raw) ? raw : `Clinic Reg.: ${raw}`);
    }

    return parts.join(' • ');
});

const clinicContactLine = computed<string>(() => {
    const parts: string[] = [];
    if (props.clinic.phone?.trim()) {
        parts.push(`Tel: ${props.clinic.phone.trim()}`);
    }
    if (props.clinic.email?.trim()) {
        parts.push(props.clinic.email.trim());
    }
    return parts.join(' • ');
});

const templateOptions: { label: string; value: TemplateMode }[] = [
    { label: 'Auto Wording', value: 'auto' },
    { label: 'Sick Leave & Rest', value: 'sick_leave' },
    { label: 'Fitness Clearance', value: 'fitness' },
    { label: 'OPD Attendance', value: 'attendance' },
    { label: 'Light Duty Advice', value: 'light_duty' },
];

const resolvedMode = computed<Exclude<TemplateMode, 'auto'>>(() => {
    if (templateMode.value !== 'auto') {
        return templateMode.value;
    }

    const typeName = (
        props.certificate.certificate_type_name ?? ''
    ).toLowerCase();
    if (typeName.includes('fit')) {
        return 'fitness';
    }
    if (
        typeName.includes('attend') ||
        typeName.includes('opd') ||
        typeName.includes('visit')
    ) {
        return 'attendance';
    }
    if (typeName.includes('light') || typeName.includes('duty')) {
        return 'light_duty';
    }

    return 'sick_leave';
});

const documentTitle = computed(() => {
    if (
        templateMode.value === 'auto' &&
        props.certificate.certificate_type_name
    ) {
        const raw = props.certificate.certificate_type_name.toUpperCase();
        return raw.includes('CERTIFICATE') ? raw : `${raw} CERTIFICATE`;
    }

    switch (resolvedMode.value) {
        case 'fitness':
            return 'MEDICAL FITNESS CERTIFICATE';
        case 'attendance':
            return 'OPD ATTENDANCE CERTIFICATE';
        case 'light_duty':
            return 'MEDICAL ADVICE & LIGHT DUTY CERTIFICATE';
        default:
            return 'MEDICAL SICK LEAVE CERTIFICATE';
    }
});

const patientAgeGenderLabel = computed(() => {
    const parts: string[] = [];
    if (
        props.certificate.patient_age !== null &&
        props.certificate.patient_age !== undefined
    ) {
        parts.push(`${props.certificate.patient_age} Yrs`);
    }
    if (props.certificate.patient_gender) {
        parts.push(props.certificate.patient_gender);
    }
    return parts.length > 0 ? parts.join(' / ') : 'On Clinical Record';
});

const patientLocationLabel = computed(() => {
    const parts = [
        props.certificate.patient_address,
        props.certificate.patient_city,
    ].filter(Boolean);
    return parts.length > 0 ? parts.join(', ') : 'On Clinical Record';
});

const formattedFee = computed(() => {
    const val = Number(props.certificate.charge_amount ?? 0);
    return `Rs. ${val.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
});

function handlePrint(): void {
    window.print();
}

function handleClose(): void {
    if (typeof window !== 'undefined' && window.opener) {
        window.close();
        return;
    }
    router.visit(medicalCertificates().url);
}
</script>

<template>
    <div class="cert-print-page">
        <Head
            :title="`Medical Certificate - ${props.certificate.certificate_number}`"
        />

        <!-- Ultra-Compact Single-Row Print Toolbar (Hidden when printing) -->
        <div class="print-toolbar no-print">
            <div class="print-toolbar__inner">
                <div class="print-toolbar__left">
                    <span class="toolbar-cert-badge font-mono">
                        <i class="pi pi-file-check"></i>
                        {{ props.certificate.certificate_number }}
                    </span>

                    <Select
                        id="wording-template"
                        v-model="templateMode"
                        :options="templateOptions"
                        optionLabel="label"
                        optionValue="value"
                        size="small"
                        class="toolbar-template-select"
                    />

                    <div class="section-toggle-pills">
                        <button
                            type="button"
                            class="section-pill"
                            :class="{ 'is-active': showPatientGrid }"
                            @click="showPatientGrid = !showPatientGrid"
                        >
                            <i
                                :class="
                                    showPatientGrid
                                        ? 'pi pi-check-circle'
                                        : 'pi pi-circle'
                                "
                            ></i>
                            <span>Patient Grid</span>
                        </button>
                        <button
                            type="button"
                            class="section-pill"
                            :class="{ 'is-active': showPeriodSummary }"
                            @click="showPeriodSummary = !showPeriodSummary"
                        >
                            <i
                                :class="
                                    showPeriodSummary
                                        ? 'pi pi-check-circle'
                                        : 'pi pi-circle'
                                "
                            ></i>
                            <span>Summary</span>
                        </button>
                        <button
                            type="button"
                            class="section-pill"
                            :class="{ 'is-active': showPatientSignature }"
                            @click="
                                showPatientSignature = !showPatientSignature
                            "
                        >
                            <i
                                :class="
                                    showPatientSignature
                                        ? 'pi pi-check-circle'
                                        : 'pi pi-circle'
                                "
                            ></i>
                            <span>Sign &amp; Seal</span>
                        </button>
                        <button
                            type="button"
                            class="section-pill"
                            :class="{ 'is-active': showFeeReceipt }"
                            @click="showFeeReceipt = !showFeeReceipt"
                        >
                            <i
                                :class="
                                    showFeeReceipt
                                        ? 'pi pi-check-circle'
                                        : 'pi pi-circle'
                                "
                            ></i>
                            <span>Fee Stub</span>
                        </button>
                        <button
                            type="button"
                            class="section-pill"
                            :class="{ 'is-active': showSalutationEditor }"
                            @click="
                                showSalutationEditor = !showSalutationEditor
                            "
                        >
                            <i class="pi pi-pencil"></i>
                            <span>Salutation</span>
                        </button>
                    </div>

                    <InputText
                        v-if="showSalutationEditor"
                        id="salutation-input"
                        v-model="recipientText"
                        size="small"
                        placeholder="TO WHOM IT MAY CONCERN"
                        class="toolbar-salutation-input"
                    />
                </div>

                <div class="print-toolbar__actions">
                    <Button
                        label="Print / Save PDF"
                        icon="pi pi-print"
                        size="small"
                        class="p-button-primary toolbar-btn"
                        @click="handlePrint"
                    />
                    <Button
                        label="Close"
                        icon="pi pi-times"
                        severity="secondary"
                        outlined
                        size="small"
                        class="toolbar-btn"
                        @click="handleClose"
                    />
                </div>
            </div>
        </div>

        <!-- Certificate Printable Sheet (Crisp white A4 paper) -->
        <div class="cert-outer-container">
            <div class="cert-card">
                <!-- VOID Watermark if voided -->
                <div
                    v-if="props.certificate.status === 'void'"
                    class="void-watermark"
                >
                    VOID / CANCELLED
                </div>

                <!-- Compact Official Clinic Letterhead -->
                <header class="cert-header">
                    <div class="cert-header__top">
                        <div class="cert-header__brand">
                            <img
                                v-if="resolvedLogoUrl"
                                :src="resolvedLogoUrl"
                                alt="AOM Clinic Logo"
                                class="clinic-logo-banner"
                                @error="logoLoadError = true"
                            />
                            <div v-else class="clinic-brand-fallback">
                                <div class="clinic-logo-placeholder">
                                    <i class="pi pi-building"></i>
                                </div>
                                <div class="clinic-brand-details">
                                    <h1 class="clinic-title">
                                        {{
                                            props.clinic.clinic_name &&
                                            props.clinic.clinic_name.length > 2
                                                ? props.clinic.clinic_name
                                                : 'AOM CLINIC'
                                        }}
                                    </h1>
                                    <span class="clinic-tagline">
                                        WE TAKE CARE OF YOUR HEALTH
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="cert-header__doctor">
                            <h2 class="doctor-name">
                                {{
                                    props.clinic.doctor_name ||
                                    'Dr. Appasaheb S. Waghmare'
                                }}
                            </h2>
                            <p
                                v-if="doctorQualificationsLine"
                                class="doctor-credentials"
                            >
                                {{ doctorQualificationsLine }}
                            </p>
                            <p v-if="registrationLine" class="doctor-reg-line">
                                {{ registrationLine }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="props.clinic.address || clinicContactLine"
                        class="cert-header__contact-bar"
                    >
                        <div class="contact-bar__address">
                            <span v-if="props.clinic.address">
                                <strong>Address:</strong>
                                {{ props.clinic.address }}
                            </span>
                        </div>
                        <div
                            v-if="clinicContactLine"
                            class="contact-bar__numbers"
                        >
                            {{ clinicContactLine }}
                        </div>
                    </div>
                </header>

                <div class="letterhead-divider"></div>

                <!-- Main Certificate Content -->
                <main class="cert-body">
                    <!-- Single-Line Document Meta + Title Bar -->
                    <div class="cert-doc-bar">
                        <div class="cert-doc-meta">
                            <span class="cert-doc-meta__label"
                                >CERTIFICATE NO:</span
                            >
                            <strong class="cert-doc-meta__value font-mono">{{
                                props.certificate.certificate_number
                            }}</strong>
                        </div>

                        <div class="cert-title-banner">
                            <h2 class="certificate-main-title">
                                {{ documentTitle }}
                            </h2>
                        </div>

                        <div class="cert-doc-meta cert-doc-meta--right">
                            <span class="cert-doc-meta__label"
                                >ISSUE DATE:</span
                            >
                            <strong class="cert-doc-meta__value">{{
                                props.certificate.issue_date
                            }}</strong>
                        </div>
                    </div>

                    <!-- Structured Patient Details Table -->
                    <section
                        v-if="showPatientGrid"
                        class="patient-details-table"
                    >
                        <div class="patient-detail-item">
                            <span class="detail-label">Patient Name:</span>
                            <strong class="detail-value">
                                {{ props.certificate.patient_name }}
                                <span
                                    v-if="props.certificate.patient_id"
                                    class="detail-sub"
                                >
                                    (#{{ props.certificate.patient_id }})
                                </span>
                            </strong>
                        </div>
                        <div class="patient-detail-item">
                            <span class="detail-label">Contact No:</span>
                            <strong class="detail-value">{{
                                props.certificate.patient_phone || '-'
                            }}</strong>
                        </div>
                        <div class="patient-detail-item">
                            <span class="detail-label">Age / Gender:</span>
                            <strong class="detail-value">{{
                                patientAgeGenderLabel
                            }}</strong>
                        </div>
                        <div class="patient-detail-item">
                            <span class="detail-label">Address:</span>
                            <strong class="detail-value">{{
                                patientLocationLabel
                            }}</strong>
                        </div>
                    </section>

                    <!-- Salutation -->
                    <div v-if="recipientText.trim()" class="cert-salutation">
                        {{ recipientText.trim() }}
                    </div>

                    <!-- Formal Medical Statement -->
                    <div class="certificate-statement-text">
                        <template v-if="resolvedMode === 'sick_leave'">
                            <p>
                                This is to certify that I have clinically
                                examined
                                <strong>{{
                                    props.certificate.patient_name
                                }}</strong>
                                at
                                <strong>AOM Clinic</strong>
                                on
                                <strong>{{
                                    props.certificate.issue_date
                                }}</strong
                                >.
                            </p>
                            <p v-if="props.certificate.diagnosis">
                                Based on clinical evaluation, the patient is
                                suffering from / diagnosed with
                                <strong>{{
                                    props.certificate.diagnosis
                                }}</strong
                                >.
                            </p>
                            <p
                                v-if="
                                    props.certificate.start_date &&
                                    props.certificate.end_date
                                "
                            >
                                I consider that a period of medical rest and
                                absence from regular duty / studies is essential
                                for recovery from
                                <strong>{{
                                    props.certificate.start_date
                                }}</strong>
                                to
                                <strong>{{
                                    props.certificate.end_date
                                }}</strong>
                                <span v-if="props.certificate.duration_days">
                                    (<strong>{{
                                        props.certificate.duration_days
                                    }}
                                    {{
                                        props.certificate.duration_days === 1
                                            ? 'Day'
                                            : 'Days'
                                    }}</strong
                                    >, both days inclusive)</span
                                >.
                            </p>
                            <p v-if="props.certificate.resume_date">
                                The patient is advised to resume normal duties /
                                classes from
                                <strong>{{
                                    props.certificate.resume_date
                                }}</strong
                                >.
                            </p>
                        </template>

                        <template v-else-if="resolvedMode === 'fitness'">
                            <p>
                                This is to certify that I have clinically
                                examined
                                <strong>{{
                                    props.certificate.patient_name
                                }}</strong>
                                on
                                <strong>{{
                                    props.certificate.issue_date
                                }}</strong>
                                at
                                <strong>AOM Clinic</strong>.
                            </p>
                            <p v-if="props.certificate.diagnosis">
                                The patient was under medical care for
                                <strong>{{
                                    props.certificate.diagnosis
                                }}</strong>
                                <span
                                    v-if="
                                        props.certificate.start_date &&
                                        props.certificate.end_date
                                    "
                                >
                                    from
                                    <strong>{{
                                        props.certificate.start_date
                                    }}</strong>
                                    to
                                    <strong>{{
                                        props.certificate.end_date
                                    }}</strong></span
                                >.
                            </p>
                            <p>
                                Following clinical assessment today, I certify
                                that
                                <strong>{{
                                    props.certificate.patient_name
                                }}</strong>
                                has recovered satisfactorily and is
                                <strong
                                    >medically fit to resume normal duties /
                                    studies</strong
                                >
                                with effect from
                                <strong>{{
                                    props.certificate.resume_date ||
                                    props.certificate.end_date ||
                                    props.certificate.issue_date
                                }}</strong
                                >.
                            </p>
                        </template>

                        <template v-else-if="resolvedMode === 'attendance'">
                            <p>
                                This is to certify that
                                <strong>{{
                                    props.certificate.patient_name
                                }}</strong>
                                attended the Outpatient Department (OPD) at
                                <strong>AOM Clinic</strong>
                                on
                                <strong>{{
                                    props.certificate.issue_date
                                }}</strong>
                                for clinical consultation and medical care.
                            </p>
                            <p v-if="props.certificate.diagnosis">
                                <strong
                                    >Clinical Condition / Purpose of
                                    Visit:</strong
                                >
                                {{ props.certificate.diagnosis }}.
                            </p>
                            <p
                                v-if="
                                    props.certificate.start_date &&
                                    props.certificate.end_date
                                "
                            >
                                Follow-up / observation period:
                                <strong>{{
                                    props.certificate.start_date
                                }}</strong>
                                to
                                <strong>{{
                                    props.certificate.end_date
                                }}</strong
                                >.
                            </p>
                        </template>

                        <template v-else-if="resolvedMode === 'light_duty'">
                            <p>
                                This is to certify that
                                <strong>{{
                                    props.certificate.patient_name
                                }}</strong>
                                was examined at
                                <strong>AOM Clinic</strong>
                                on
                                <strong>{{
                                    props.certificate.issue_date
                                }}</strong
                                >.
                            </p>
                            <p v-if="props.certificate.diagnosis">
                                The patient is currently recovering from
                                <strong>{{
                                    props.certificate.diagnosis
                                }}</strong
                                >.
                            </p>
                            <p>
                                It is medically advised that the patient be
                                assigned
                                <strong>light / modified duties only</strong>,
                                avoiding strenuous physical exertion or heavy
                                lifting
                                <span
                                    v-if="
                                        props.certificate.start_date &&
                                        props.certificate.end_date
                                    "
                                >
                                    from
                                    <strong>{{
                                        props.certificate.start_date
                                    }}</strong>
                                    to
                                    <strong>{{
                                        props.certificate.end_date
                                    }}</strong></span
                                >.
                            </p>
                        </template>
                    </div>

                    <!-- Highlighted Summary Card -->
                    <section
                        v-if="
                            showPeriodSummary &&
                            (props.certificate.start_date ||
                                props.certificate.end_date ||
                                props.certificate.diagnosis)
                        "
                        class="period-summary-card"
                    >
                        <div
                            v-if="props.certificate.diagnosis"
                            class="period-summary-diagnosis"
                        >
                            <span class="period-summary-label"
                                >CLINICAL DIAGNOSIS / ASSESSMENT</span
                            >
                            <strong class="period-summary-text">{{
                                props.certificate.diagnosis
                            }}</strong>
                        </div>

                        <div
                            v-if="
                                props.certificate.start_date &&
                                props.certificate.end_date
                            "
                            class="period-summary-metrics"
                        >
                            <div class="period-metric-item">
                                <span class="period-summary-label"
                                    >FROM DATE</span
                                >
                                <strong>{{
                                    props.certificate.start_date
                                }}</strong>
                            </div>
                            <div class="period-metric-item">
                                <span class="period-summary-label"
                                    >TO DATE (INCLUSIVE)</span
                                >
                                <strong>{{
                                    props.certificate.end_date
                                }}</strong>
                            </div>
                            <div
                                v-if="props.certificate.duration_days"
                                class="period-metric-item period-metric-item--accent"
                            >
                                <span class="period-summary-label"
                                    >DURATION</span
                                >
                                <strong
                                    >{{ props.certificate.duration_days }}
                                    {{
                                        props.certificate.duration_days === 1
                                            ? 'Day'
                                            : 'Days'
                                    }}</strong
                                >
                            </div>
                            <div
                                v-if="props.certificate.resume_date"
                                class="period-metric-item"
                            >
                                <span class="period-summary-label"
                                    >FIT TO RESUME</span
                                >
                                <strong>{{
                                    props.certificate.resume_date
                                }}</strong>
                            </div>
                        </div>
                    </section>

                    <!-- Remarks / Advice -->
                    <section
                        v-if="props.certificate.notes"
                        class="remarks-card"
                    >
                        <span class="period-summary-label"
                            >MEDICAL ADVICE &amp; REMARKS</span
                        >
                        <p class="remarks-text">
                            {{ props.certificate.notes }}
                        </p>
                    </section>

                    <!-- Signatures Footer -->
                    <footer
                        class="certificate-signatures"
                        :class="{
                            'certificate-signatures--single':
                                !showPatientSignature,
                        }"
                    >
                        <div
                            v-if="showPatientSignature"
                            class="signature-block signature-block--left"
                        >
                            <div class="sig-line"></div>
                            <p class="sig-title">Patient's Signature</p>
                            <p class="sig-subtitle">
                                {{ props.certificate.patient_name }}
                            </p>
                        </div>

                        <div
                            v-if="showPatientSignature"
                            class="clinic-seal-box"
                        >
                            <span>CLINIC SEAL</span>
                        </div>

                        <div class="signature-block signature-block--right">
                            <div class="sig-line"></div>
                            <p class="sig-title">
                                {{
                                    props.clinic.doctor_name ||
                                    'Authorized Medical Officer'
                                }}
                            </p>
                            <p
                                v-if="props.clinic.doctor_qualifications"
                                class="sig-subtitle"
                            >
                                {{ props.clinic.doctor_qualifications }}
                            </p>
                            <p v-if="registrationLine" class="sig-subtitle">
                                {{ registrationLine }}
                            </p>
                        </div>
                    </footer>

                    <!-- Optional Fee Receipt Stub -->
                    <section v-if="showFeeReceipt" class="fee-receipt-stub">
                        <div class="stub-divider">
                            <span>CERTIFICATE FEE RECEIPT</span>
                        </div>
                        <div class="stub-row">
                            <div>
                                <span class="stub-label">Certificate No</span>
                                <strong class="stub-value">{{
                                    props.certificate.certificate_number
                                }}</strong>
                            </div>
                            <div>
                                <span class="stub-label">Received From</span>
                                <strong class="stub-value">{{
                                    props.certificate.patient_name
                                }}</strong>
                            </div>
                            <div>
                                <span class="stub-label">Certificate Type</span>
                                <strong class="stub-value">{{
                                    props.certificate.certificate_type_name ||
                                    'Medical Certificate'
                                }}</strong>
                            </div>
                            <div>
                                <span class="stub-label">Amount</span>
                                <strong class="stub-value"
                                    >{{ formattedFee }} ({{
                                        (
                                            props.certificate.payment_status ||
                                            'unpaid'
                                        ).toUpperCase()
                                    }})</strong
                                >
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
.cert-print-page {
    min-height: 100vh;
    background: var(--surface-ground, #f1f5f9);
}

/* Ultra-Compact Single-Row Sticky Control Toolbar */
.print-toolbar {
    background: var(--surface-card, #ffffff);
    border-bottom: 1px solid var(--surface-border, #e2e8f0);
    padding: 0.5rem 1.25rem;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 1px 6px rgba(15, 23, 42, 0.05);
}

.print-toolbar__inner {
    max-width: 960px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.print-toolbar__left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.toolbar-cert-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    font-weight: 800;
    padding: 0.32rem 0.65rem;
    border-radius: 6px;
    background: color-mix(in srgb, #0d9488 12%, transparent);
    color: #0d9488;
    border: 1px solid color-mix(in srgb, #0d9488 28%, transparent);
    white-space: nowrap;
}

.toolbar-template-select {
    width: 11.5rem;
}

.toolbar-salutation-input {
    width: 13rem;
}

.print-toolbar__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.print-toolbar__actions :deep(.p-button),
.toolbar-btn {
    white-space: nowrap !important;
    width: auto !important;
}

.section-toggle-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.section-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.32rem 0.55rem;
    border-radius: 6px;
    border: 1px solid var(--surface-border, #cbd5e1);
    background: var(--surface-ground, #f8fafc);
    color: var(--text-color-secondary, #475569);
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.section-pill:hover {
    border-color: var(--primary-color, #0d9488);
    color: var(--text-color, #0f172a);
}

.section-pill.is-active {
    background: color-mix(in srgb, #0d9488 12%, var(--surface-card, #ffffff));
    border-color: #0d9488;
    color: #0d9488;
}

/* Printable Sheet (Explicitly locked to light paper colors even in Dark Mode) */
.cert-outer-container {
    padding: 1.25rem 1rem 2.5rem;
    display: flex;
    justify-content: center;
}

.cert-card {
    position: relative;
    background: #ffffff;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    width: 100%;
    max-width: 840px;
    padding: 1.75rem 2.5rem 2.25rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
    overflow: hidden;
}

.void-watermark {
    position: absolute;
    top: 48%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-28deg);
    font-size: 4rem;
    font-weight: 900;
    color: rgba(220, 38, 38, 0.11);
    border: 5px solid rgba(220, 38, 38, 0.15);
    padding: 0.4rem 1.75rem;
    border-radius: 10px;
    pointer-events: none;
    white-space: nowrap;
    letter-spacing: 0.1em;
}

/* Compact Horizontal Letterhead Header */
.cert-header {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.cert-header__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
}

.cert-header__brand {
    display: flex;
    align-items: center;
    min-width: 0;
}

.clinic-logo-banner {
    height: 3.85rem;
    width: auto;
    max-width: 270px;
    object-fit: contain;
    object-position: left center;
    display: block;
    margin-left: -0.45rem;
}

.clinic-brand-fallback {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.clinic-logo-placeholder {
    width: 3rem;
    height: 3rem;
    background: #f0fdfa;
    color: #0d9488;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.clinic-brand-details {
    display: flex;
    flex-direction: column;
}

.clinic-title {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f766e;
    letter-spacing: -0.02em;
    line-height: 1.15;
}

.clinic-tagline {
    font-size: 0.65rem;
    font-weight: 700;
    color: #0d9488;
    letter-spacing: 0.14em;
    margin-top: 0.1rem;
    text-transform: uppercase;
}

.cert-header__doctor {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    text-align: right;
    gap: 0.14rem;
    flex-shrink: 0;
}

.doctor-name {
    margin: 0;
    font-size: 1.12rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}

.doctor-credentials {
    margin: 0;
    font-size: 0.8rem;
    font-weight: 700;
    color: #0f766e;
}

.doctor-reg-line {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
}

.cert-header__contact-bar {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1.25rem;
    margin-top: 0.45rem;
    padding-top: 0.45rem;
    border-top: 1px dashed #e2e8f0;
    font-size: 0.76rem;
    line-height: 1.4;
    color: #475569;
}

.contact-bar__address {
    flex: 1;
    min-width: 0;
    text-align: left;
}

.contact-bar__address strong {
    color: #334155;
    font-weight: 700;
}

.contact-bar__numbers {
    flex-shrink: 0;
    text-align: right;
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
}

.letterhead-divider {
    height: 1.5px;
    background: #0f172a;
    margin: 0.6rem 0 0.85rem;
}

/* Body */
.cert-body {
    display: flex;
    flex-direction: column;
    gap: 1.05rem;
}

/* Unified Single-Row Document Meta + Title Bar */
.cert-doc-bar {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 0.15rem;
}

.cert-doc-meta {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    font-size: 0.8rem;
}

.cert-doc-meta--right {
    justify-content: flex-end;
}

.cert-doc-meta__label {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.05em;
}

.cert-doc-meta__value {
    font-size: 0.88rem;
    font-weight: 800;
    color: #0f172a;
}

.cert-title-banner {
    text-align: center;
}

.certificate-main-title {
    display: inline-block;
    font-size: 1rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    margin: 0;
    color: #0f766e;
    background: #f0fdfa;
    border: 1px solid #99f6e4;
    padding: 0.3rem 0.9rem;
    border-radius: 6px;
}

/* Patient Details Table */
.patient-details-table {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem 1.5rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 0.7rem 1rem;
}

.patient-detail-item {
    display: grid;
    grid-template-columns: 6.75rem 1fr;
    align-items: baseline;
    gap: 0.5rem;
    min-width: 0;
}

.detail-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}

.detail-value {
    font-size: 0.86rem;
    color: #0f172a;
    word-break: break-word;
}

.detail-sub {
    font-weight: 600;
    color: #64748b;
    font-size: 0.76rem;
}

.cert-salutation {
    font-size: 0.84rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    color: #0f172a;
    text-transform: uppercase;
}

.certificate-statement-text {
    font-size: 0.96rem;
    line-height: 1.7;
    color: #334155;
    text-align: justify;
}

.certificate-statement-text strong {
    color: #0f172a;
}

.certificate-statement-text p {
    margin: 0 0 0.75rem;
}

.certificate-statement-text p:last-child {
    margin-bottom: 0;
}

/* Highlighted Period Card */
.period-summary-card {
    background: #f0fdfa;
    border: 1px solid #ccfbf1;
    border-radius: 6px;
    padding: 0.85rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.period-summary-label {
    display: block;
    font-size: 0.65rem;
    font-weight: 700;
    color: #0d9488;
    letter-spacing: 0.05em;
    margin-bottom: 0.1rem;
}

.period-summary-text {
    font-size: 0.94rem;
    color: #0f172a;
}

.period-summary-metrics {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    padding-top: 0.55rem;
    border-top: 1px dashed #99f6e4;
}

.period-metric-item {
    flex: 1;
    min-width: 0;
}

.period-metric-item strong {
    font-size: 0.9rem;
    color: #0f172a;
}

.period-metric-item--accent strong {
    color: #0f766e;
    font-size: 0.98rem;
}

.remarks-card {
    background: #f8fafc;
    border-left: 3px solid #0d9488;
    padding: 0.65rem 0.95rem;
    border-radius: 0 6px 6px 0;
}

.remarks-text {
    margin: 0;
    font-size: 0.88rem;
    color: #334155;
    line-height: 1.5;
}

/* Signatures */
.certificate-signatures {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: end;
    gap: 1.5rem;
    margin-top: 2rem;
}

.certificate-signatures--single {
    display: flex;
    justify-content: flex-end;
}

.signature-block {
    width: 220px;
}

.signature-block--left {
    text-align: left;
}

.signature-block--right {
    text-align: right;
    margin-left: auto;
}

.sig-line {
    height: 1px;
    background: #94a3b8;
    margin-bottom: 0.4rem;
}

.sig-title {
    font-weight: 700;
    margin: 0;
    font-size: 0.85rem;
    color: #1e293b;
}

.sig-subtitle {
    margin: 0.1rem 0 0;
    font-size: 0.74rem;
    color: #64748b;
}

.clinic-seal-box {
    width: 72px;
    height: 72px;
    border: 1px dashed #cbd5e1;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.56rem;
    font-weight: 700;
    color: #94a3b8;
    letter-spacing: 0.06em;
    text-align: center;
}

/* Fee Stub */
.fee-receipt-stub {
    margin-top: 0.75rem;
}

.stub-divider {
    border-top: 1px dashed #cbd5e1;
    text-align: center;
    margin-bottom: 0.4rem;
}

.stub-divider span {
    background: #ffffff;
    padding: 0 0.65rem;
    position: relative;
    top: -0.6rem;
    font-size: 0.65rem;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.05em;
}

.stub-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 0.55rem 0.85rem;
    font-size: 0.8rem;
    color: #0f172a;
}

.stub-label {
    display: block;
    font-size: 0.64rem;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 700;
}

.stub-value {
    color: #0f172a;
}

@media screen and (max-width: 600px) {
    .patient-details-table,
    .stub-row,
    .certificate-signatures,
    .cert-doc-bar {
        grid-template-columns: 1fr;
    }

    .period-summary-metrics {
        flex-wrap: wrap;
    }

    .cert-header__top,
    .cert-header__contact-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.4rem;
    }

    .cert-header__doctor,
    .contact-bar__numbers {
        align-items: flex-start;
        text-align: left;
    }

    .cert-doc-meta--right {
        justify-content: flex-start;
    }
}

@media print {
    @page {
        size: A4 portrait;
        margin: 10mm 12mm;
    }

    .no-print {
        display: none !important;
    }

    .cert-print-page,
    .cert-outer-container {
        background: #ffffff !important;
        padding: 0 !important;
        min-height: auto !important;
        display: block !important;
    }

    .cert-card {
        border: none !important;
        padding: 0 !important;
        box-shadow: none !important;
        max-width: 100% !important;
        width: 100% !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .cert-header__top {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: center !important;
    }

    .cert-header__doctor {
        align-items: flex-end !important;
        text-align: right !important;
    }

    .cert-header__contact-bar {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: baseline !important;
    }

    .contact-bar__numbers {
        text-align: right !important;
    }

    .cert-doc-bar {
        display: grid !important;
        grid-template-columns: 1fr auto 1fr !important;
        align-items: center !important;
    }

    .cert-doc-meta--right {
        justify-content: flex-end !important;
    }

    .patient-details-table {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
    }

    .period-summary-metrics {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
    }

    .certificate-signatures {
        display: grid !important;
        grid-template-columns: 1fr auto 1fr !important;
        align-items: end !important;
    }

    .certificate-signatures--single {
        display: flex !important;
        justify-content: flex-end !important;
    }

    .stub-row {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
    }
}
</style>
