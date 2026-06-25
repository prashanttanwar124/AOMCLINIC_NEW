<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';

type Props = {
    appointment: {
        id: number;
        appointment_date_label: string;
        appointment_time_label: string;
        patient_name: string;
        diagnosis: string;
        days_prescription: number;
        amount: string;
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
    <Head :title="`Receipt - Appointment #${props.appointment.id}`" />

    <!-- Print control bar: visible on screen only, hidden during printing -->
    <div class="print-control-bar no-print">
        <div class="print-control-bar__content">
            <span class="print-control-bar__title">Receipt Preview</span>
            <div class="print-control-bar__actions">
                <Button
                    label="Print Receipt"
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

    <!-- Receipt Container -->
    <div class="receipt-outer-container">
        <div class="receipt-card">
            <!-- Header section -->
            <header class="receipt-header">
                <div class="receipt-header__left">
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

                <div class="receipt-header__right">
                    <div class="receipt-badge">APPOINTMENT RECEIPT</div>
                    
                    <div class="receipt-meta-box mt-4">
                        <div class="receipt-meta-item">
                            <span class="receipt-meta-label">RECEIPT DATE</span>
                            <span class="receipt-meta-value">{{ props.appointment.appointment_date_label }}</span>
                        </div>
                        <div class="receipt-meta-item mt-3">
                            <span class="receipt-meta-label">RECEIPT TIME</span>
                            <span class="receipt-meta-value">{{ props.appointment.appointment_time_label }}</span>
                        </div>
                    </div>
                </div>
            </header>

            <Divider class="my-4" />

            <!-- Payment details section -->
            <main class="receipt-body">
                <div class="payment-details-layout">
                    <!-- Left side: details list -->
                    <div class="payment-details-list">
                        <h3 class="section-title">PAYMENT DETAILS</h3>
                        
                        <div class="details-table">
                            <div class="details-row">
                                <span class="details-label">Received From</span>
                                <strong class="details-value">{{ props.appointment.patient_name }}</strong>
                            </div>
                            <div class="details-row">
                                <span class="details-label">Patient Name</span>
                                <strong class="details-value">{{ props.appointment.patient_name }}</strong>
                            </div>
                            <div class="details-row">
                                <span class="details-label">Diagnosis</span>
                                <span class="details-value">{{ props.appointment.diagnosis }}</span>
                            </div>
                            <div class="details-row">
                                <span class="details-label">Duration</span>
                                <span class="details-value">
                                    {{ props.appointment.days_prescription > 0 ? `${props.appointment.days_prescription} Days` : '-' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right side: amount received box -->
                    <div class="amount-received-card">
                        <span class="amount-label">AMOUNT RECEIVED</span>
                        <h2 class="amount-value">Rs. {{ props.appointment.amount }}</h2>
                        <p class="amount-description">
                            This receipt confirms payment collected for the appointment and medicine handover process.
                        </p>
                    </div>
                </div>
            </main>

            <Divider class="my-5" />

            <!-- Footer section -->
            <footer class="receipt-footer">
                <p class="footer-note">This is a computer-generated receipt for clinic records.</p>
            </footer>
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

.receipt-outer-container {
    background: #f1f5f9;
    padding: 2rem 1rem;
    min-height: 100vh;
    display: flex;
    justify-content: center;
}

.receipt-card {
    background: #fff;
    border: 1px solid var(--surface-border, #e2e8f0);
    border-radius: 8px;
    width: 100%;
    max-width: 840px;
    padding: 3rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
}

.receipt-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
}

.receipt-header__left {
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

.receipt-header__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.receipt-badge {
    border: 1px solid #0d9488;
    background: #f0fdfa;
    color: #0d9488;
    font-weight: 700;
    font-size: 0.78rem;
    padding: 0.4rem 0.8rem;
    border-radius: 4px;
    letter-spacing: 0.02em;
}

.receipt-meta-box {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    text-align: right;
    margin-top: 1.5rem;
}

.receipt-meta-item {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.receipt-meta-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: #94a3b8;
    letter-spacing: 0.05em;
}

.receipt-meta-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
}

.payment-details-layout {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 2.5rem;
    align-items: start;
}

.section-title {
    margin: 0 0 1rem;
    font-size: 0.88rem;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.05em;
}

.details-table {
    display: flex;
    flex-direction: column;
    border-top: 1px solid #f1f5f9;
}

.details-row {
    display: flex;
    justify-content: space-between;
    padding: 0.85rem 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.95rem;
}

.details-label {
    color: #64748b;
    width: 9rem;
    flex-shrink: 0;
}

.details-value {
    color: #1e293b;
    text-align: left;
    flex-grow: 1;
}

.amount-received-card {
    background: #f0fdfa;
    border: 1px solid #ccfbf1;
    border-radius: 6px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.amount-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #0d9488;
    letter-spacing: 0.05em;
}

.amount-value {
    margin: 0;
    font-size: 2.25rem;
    font-weight: 800;
    color: #0f766e;
}

.amount-description {
    margin: 0;
    font-size: 0.84rem;
    color: #0d9488;
    line-height: 1.5;
}

.receipt-footer {
    display: flex;
    justify-content: center;
}

.footer-note {
    margin: 0;
    font-size: 0.8rem;
    color: #94a3b8;
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
    
    .receipt-outer-container {
        background: #fff !important;
        padding: 0 !important;
        min-height: auto !important;
    }
    
    .receipt-card {
        border: none !important;
        padding: 0 !important;
        box-shadow: none !important;
        max-width: 100% !important;
    }
}
</style>
