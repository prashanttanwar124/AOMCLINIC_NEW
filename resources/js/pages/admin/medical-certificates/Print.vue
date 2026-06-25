<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';

type Props = {
    certificate: {
        id: number;
        patient_name: string;
        patient_phone: string | null;
        certificate_number: string;
        issue_date: string;
        start_date: string | null;
        end_date: string | null;
        diagnosis: string | null;
        notes: string | null;
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

function handlePrint(): void {
    window.print();
}

function handleClose(): void {
    window.close();
}
</script>

<template>
    <Head :title="`Medical Certificate - ${props.certificate.certificate_number}`" />

    <!-- Print control bar: visible on screen only, hidden during printing -->
    <div class="print-control-bar no-print">
        <div class="print-control-bar__content">
            <span class="print-control-bar__title">Medical Certificate Preview</span>
            <div class="print-control-bar__actions">
                <Button
                    label="Print Certificate"
                    icon="pi pi-print"
                    class="p-button-primary"
                    @click="handlePrint"
                />
                <Button
                    label="Close"
                    icon="pi pi-times"
                    severity="secondary"
                    outlined
                    @click="handleClose"
                />
            </div>
        </div>
    </div>

    <!-- Certificate outer container -->
    <div class="cert-outer-container">
        <div class="cert-card">
            <!-- Header section / Letterhead -->
            <header class="cert-header">
                <div class="cert-header__left">
                    <div class="clinic-brand-row">
                        <img
                            v-if="props.clinic.logo_url"
                            :src="props.clinic.logo_url"
                            alt="Clinic Logo"
                            class="clinic-logo"
                        />
                        <div v-else class="clinic-logo-placeholder">
                            <i class="pi pi-building"></i>
                        </div>
                        <div class="clinic-brand-details">
                            <h1 class="clinic-title">{{ props.clinic.clinic_name || 'AOM CLINIC' }}</h1>
                            <span class="clinic-tagline">HEALING CONSCIOUSLY</span>
                        </div>
                    </div>

                    <div class="doctor-details">
                        <h2 class="doctor-name">{{ props.clinic.doctor_name || 'Dr. Appasaheb S. Waghmare' }}</h2>
                        <div class="doctor-meta">
                            <span v-if="props.clinic.doctor_qualifications" class="doctor-qualifications">
                                {{ props.clinic.doctor_qualifications }}
                            </span>
                            <span v-if="props.clinic.doctor_title" class="doctor-title">
                                {{ props.clinic.doctor_title }}
                            </span>
                            <span v-if="props.clinic.doctor_registration_no" class="doctor-reg">
                                Reg. No.: {{ props.clinic.doctor_registration_no }}
                            </span>
                            <span v-if="props.clinic.clinic_registration_no" class="clinic-reg">
                                Clinic Reg.: {{ props.clinic.clinic_registration_no }}
                            </span>
                            <span v-if="props.clinic.address" class="clinic-address">
                                {{ props.clinic.address }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="cert-header__right">
                    <div class="cert-badge">OFFICIAL RECORD</div>
                    <div class="cert-contact-info mt-4 text-right">
                        <p v-if="props.clinic.phone">Phone: {{ props.clinic.phone }}</p>
                        <p v-if="props.clinic.email">Email: {{ props.clinic.email }}</p>
                    </div>
                </div>
            </header>

            <div class="letterhead-divider"></div>

            <!-- Certificate main body -->
            <main class="cert-body">
                <h1 class="certificate-main-title">MEDICAL CERTIFICATE</h1>

                <div class="cert-serial-info">
                    <span class="cert-no">Certificate No: <strong>{{ props.certificate.certificate_number }}</strong></span>
                    <span class="cert-date">Date of Issue: <strong>{{ props.certificate.issue_date }}</strong></span>
                </div>

                <div class="certificate-statement-text">
                    <p>
                        This is to certify that Mr./Ms. <strong>{{ props.certificate.patient_name }}</strong>
                        <span v-if="props.certificate.patient_phone"> (Phone: {{ props.certificate.patient_phone }})</span>
                        was examined at this clinic on <strong>{{ props.certificate.issue_date }}</strong>.
                    </p>

                    <p v-if="props.certificate.diagnosis">
                        Based on clinical evaluation, the patient is diagnosed with <strong>{{ props.certificate.diagnosis }}</strong>.
                    </p>

                    <p v-if="props.certificate.start_date && props.certificate.end_date">
                        He/She has been advised medical rest and leave from duty/studies starting from
                        <strong>{{ props.certificate.start_date }}</strong> to <strong>{{ props.certificate.end_date }}</strong> (inclusive).
                    </p>

                    <p v-if="props.certificate.notes">
                        <strong>Remarks:</strong> {{ props.certificate.notes }}
                    </p>
                </div>

                <div class="certificate-signatures">
                    <div class="signature-block">
                        <div class="sig-line"></div>
                        <p class="sig-title">Authorized Medical Officer</p>
                        <p class="sig-subtitle">{{ props.clinic.clinic_name || 'AOM Clinic' }}</p>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
.print-control-bar {
    background: #1e293b;
    color: #fff;
    padding: 0.75rem 1.5rem;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.print-control-bar__content {
    max-width: 840px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.print-control-bar__title {
    font-weight: 700;
    font-size: 1.05rem;
}

.print-control-bar__actions {
    display: flex;
    gap: 0.75rem;
}

.cert-outer-container {
    background: #f1f5f9;
    padding: 2rem 1rem;
    min-height: 100vh;
    display: flex;
    justify-content: center;
}

.cert-card {
    background: #fff;
    border: 1px solid var(--surface-border, #e2e8f0);
    border-radius: 8px;
    width: 100%;
    max-width: 840px;
    padding: 4rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
}

.cert-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
}

.cert-header__left {
    flex: 1;
}

.clinic-brand-row {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.clinic-logo {
    width: 5.5rem;
    height: 5.5rem;
    object-fit: contain;
    flex-shrink: 0;
}

.clinic-logo-placeholder {
    width: 5.5rem;
    height: 5.5rem;
    background: color-mix(in srgb, var(--primary-color) 8%, transparent);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 2.2rem;
    flex-shrink: 0;
}

.clinic-brand-details {
    display: flex;
    flex-direction: column;
}

.clinic-title {
    margin: 0;
    font-size: 1.65rem;
    font-weight: 800;
    color: #0f766e;
    letter-spacing: -0.02em;
    line-height: 1.2;
}

.clinic-tagline {
    font-size: 0.72rem;
    font-weight: 700;
    color: #0d9488;
    letter-spacing: 0.15em;
    margin-top: 0.15rem;
    text-transform: uppercase;
}

.doctor-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 1.5rem;
}

.doctor-name {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
}

.doctor-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.88rem;
    color: #475569;
}

.doctor-qualifications {
    font-weight: 700;
}

.doctor-title {
    font-weight: 600;
}

.clinic-address {
    margin-top: 0.35rem;
    font-size: 0.88rem;
    line-height: 1.45;
    color: #64748b;
}

.cert-header__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.cert-badge {
    border: 1px solid #0d9488;
    background: #f0fdfa;
    color: #0d9488;
    font-weight: 700;
    font-size: 0.78rem;
    padding: 0.4rem 0.8rem;
    border-radius: 4px;
    letter-spacing: 0.02em;
}

.cert-contact-info p {
    margin: 0 0 0.25rem;
    font-size: 0.85rem;
    color: #64748b;
}

.letterhead-divider {
    height: 2px;
    background: #0f172a;
    margin: 2rem 0;
}

.cert-body {
    display: flex;
    flex-direction: column;
    gap: 1.75rem;
}

.certificate-main-title {
    font-size: 1.85rem;
    font-weight: 800;
    text-align: center;
    letter-spacing: 0.15em;
    margin: 0 0 1rem;
    color: #0f172a;
}

.cert-serial-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    color: #475569;
    border-bottom: 1px dashed #cbd5e1;
    padding-bottom: 0.5rem;
}

.certificate-statement-text {
    font-size: 1.05rem;
    line-height: 1.8;
    color: #334155;
    text-align: justify;
}

.certificate-statement-text p {
    margin: 0 0 1.25rem;
}

.certificate-signatures {
    display: flex;
    justify-content: flex-end;
    margin-top: 4rem;
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
    font-size: 0.9rem;
    color: #1e293b;
}

.sig-subtitle {
    margin: 0;
    font-size: 0.8rem;
    color: #64748b;
}

.print-control-bar__actions :deep(.p-button) {
    white-space: nowrap !important;
    width: auto !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

/* Print Specific Rules */
@media print {
    .no-print {
        display: none !important;
    }
    
    .cert-outer-container {
        background: #fff !important;
        padding: 0 !important;
        min-height: auto !important;
    }
    
    .cert-card {
        border: none !important;
        padding: 0 !important;
        box-shadow: none !important;
        max-width: 100% !important;
    }
}
</style>
