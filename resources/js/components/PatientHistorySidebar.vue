<script setup lang="ts">
import Drawer from 'primevue/drawer';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { ref, watch } from 'vue';
import http from '@/lib/http';
import PatientController from '@/actions/App/Http/Controllers/Admin/PatientController';

type TimelineItem = {
    type: 'appointment' | 'parcel';
    id: number;
    date: string | null;
    title: string;
    appointment_type?: string | null;
    status?: string | null;
    amount?: string | number | null;
    slot?: string | null;
    parcel_status?: string | null;
    payment_status?: string | null;
    purpose?: string | null;
    complaint?: string | null;
    presenting_complaint?: string | null;
    associated_complaint?: string | null;
    past_history?: string | null;
    diagnosis?: string | null;
    treatment?: string | null;
    medication_instructions?: string | null;
    medicines: any[];
    vitals?: {
        weight?: string | number | null;
        temp?: string | number | null;
        bp?: string | null;
        pulse?: string | number | null;
        spo2?: string | number | null;
    } | null;
    address?: string | null;
    notes?: string | null;
    delivered_date?: string | null;
    instructions_given?: boolean;
    instruction_note?: string | null;
};

type PatientInfo = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
};

const props = defineProps<{
    patientId: number | null;
}>();

const visible = defineModel<boolean>('visible', { default: false });

const loading = ref(false);
const patientInfo = ref<PatientInfo | null>(null);
const timelineData = ref<TimelineItem[]>([]);

async function fetchHistory() {
    if (!props.patientId) return;
    loading.value = true;
    try {
        const { data } = await http.get(PatientController.history({ patient: props.patientId }).url);
        patientInfo.value = data.patient || null;
        timelineData.value = data.timeline || [];
    } catch (error) {
        console.error('Error fetching patient history:', error);
    } finally {
        loading.value = false;
    }
}

watch([visible, () => props.patientId], ([newVisible, newId]) => {
    if (newVisible && newId) {
        fetchHistory();
    } else if (!newVisible) {
        // Reset state on close
        patientInfo.value = null;
        timelineData.value = [];
    }
}, { immediate: true });

function getStatusLabel(status: string): string {
    return status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function getParcelSeverity(status: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' {
    switch (status) {
        case 'delivered':
            return 'success';
        case 'order_received':
            return 'info';
        case 'packed':
        case 'dispatched':
        case 'in_transit':
            return 'warn';
        case 'returned':
            return 'danger';
        default:
            return 'secondary';
    }
}
</script>

<template>
    <Drawer
        v-model:visible="visible"
        position="right"
        header="Patient Timeline History"
        class="patient-history-drawer"
        :style="{ width: '480px' }"
    >
        <template #header>
            <div class="drawer-header-custom">
                <div class="header-title-row">
                    <i class="pi pi-history header-icon"></i>
                    <span class="header-title">Patient Timeline History</span>
                </div>
                <div v-if="patientInfo" class="header-patient-info">
                    <span class="p-name font-bold">{{ patientInfo.name }}</span>
                    <span class="p-id text-secondary">#{{ patientInfo.id }}</span>
                </div>
            </div>
        </template>

        <div class="drawer-content-container">
            <!-- Loading Skeleton -->
            <div v-if="loading" class="skeleton-container">
                <div v-for="n in 3" :key="n" class="skeleton-item">
                    <div class="skeleton-circle animate-pulse"></div>
                    <div class="skeleton-lines">
                        <div class="skeleton-line h-4 w-3/4 animate-pulse"></div>
                        <div class="skeleton-line h-3 w-1/2 animate-pulse"></div>
                        <div class="skeleton-line h-6 w-full animate-pulse mt-2"></div>
                    </div>
                </div>
            </div>

            <!-- Content loaded -->
            <template v-else>
                <!-- Quick stats / Contact box -->
                <div v-if="patientInfo" class="patient-contact-card">
                    <div v-if="patientInfo.phone" class="contact-item">
                        <i class="pi pi-phone text-xs"></i>
                        <span>{{ patientInfo.phone }}</span>
                    </div>
                    <div v-if="patientInfo.email" class="contact-item">
                        <i class="pi pi-envelope text-xs"></i>
                        <span>{{ patientInfo.email }}</span>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="timelineData.length === 0" class="empty-timeline">
                    <i class="pi pi-calendar-times empty-icon"></i>
                    <h4>No history recorded yet</h4>
                    <p class="text-xs text-secondary">This patient does not have any recorded appointments or courier parcels.</p>
                </div>

                <!-- Timeline List -->
                <div v-else class="custom-timeline">
                    <div
                        v-for="item in timelineData"
                        :key="item.type + '-' + item.id"
                        class="timeline-node"
                        :class="item.type"
                    >
                        <!-- Left Dot Indicator with icon -->
                        <div class="node-indicator">
                            <div class="node-icon-wrapper">
                                <i :class="item.type === 'appointment' ? 'pi pi-calendar' : 'pi pi-send'"></i>
                            </div>
                        </div>

                        <!-- Main Content Card -->
                        <div class="node-card">
                            <div class="node-header">
                                <h4 class="node-title font-bold">{{ item.title }}</h4>
                                <span class="node-date text-xxs text-secondary">
                                    <i class="pi pi-clock text-xxs mr-1"></i>
                                    {{ item.date || 'No Date' }}
                                </span>
                            </div>

                            <!-- Mini Tags Row -->
                            <div class="timeline-tags-row">
                                <!-- Appointment specific tags -->
                                <template v-if="item.type === 'appointment'">
                                    <Tag
                                        v-if="item.appointment_type"
                                        :value="item.appointment_type"
                                        :severity="item.appointment_type === 'New' ? 'primary' : 'info'"
                                        class="mini-tag"
                                        rounded
                                    />
                                    <Tag
                                        v-if="item.slot"
                                        :value="item.slot === 'M' ? 'Morning' : 'Evening'"
                                        severity="secondary"
                                        class="mini-tag"
                                        rounded
                                    >
                                        <template #icon>
                                            <i :class="item.slot === 'M' ? 'pi pi-sun text-yellow-500 mr-1' : 'pi pi-moon text-indigo-400 mr-1'" style="font-size: 0.65rem;"></i>
                                        </template>
                                    </Tag>
                                    <Tag
                                        v-if="item.status"
                                        :value="item.status.toUpperCase()"
                                        :severity="item.status.toLowerCase() === 'complete' ? 'success' : 'warn'"
                                        class="mini-tag"
                                        rounded
                                    />
                                    <Tag
                                        v-if="item.amount"
                                        :value="'₹' + item.amount"
                                        severity="secondary"
                                        class="mini-tag font-semibold"
                                        rounded
                                    />
                                </template>

                                <!-- Parcel specific tags -->
                                <template v-else-if="item.type === 'parcel'">
                                    <Tag
                                        v-if="item.parcel_status"
                                        :value="getStatusLabel(item.parcel_status)"
                                        :severity="getParcelSeverity(item.parcel_status)"
                                        class="mini-tag"
                                        rounded
                                    />
                                    <Tag
                                        v-if="item.payment_status"
                                        :value="item.payment_status.toUpperCase()"
                                        :severity="item.payment_status === 'paid' ? 'success' : 'danger'"
                                        class="mini-tag"
                                        rounded
                                    />
                                    <Tag
                                        v-if="item.amount"
                                        :value="'₹' + item.amount"
                                        severity="secondary"
                                        class="mini-tag font-semibold"
                                        rounded
                                    />
                                </template>
                            </div>

                            <div class="node-body">
                                <!-- Appointment detail items -->
                                <template v-if="item.type === 'appointment'">
                                    <div v-if="item.purpose" class="detail-field">
                                        <span class="detail-label">Purpose of Visit:</span>
                                        <p class="detail-val">{{ item.purpose }}</p>
                                    </div>
                                    <div v-if="item.complaint" class="detail-field">
                                        <span class="detail-label">Chief Complaint:</span>
                                        <p class="detail-val complaint-text">{{ item.complaint }}</p>
                                    </div>
                                    <div v-if="item.presenting_complaint" class="detail-field">
                                        <span class="detail-label">Presenting Complaint:</span>
                                        <p class="detail-val">{{ item.presenting_complaint }}</p>
                                    </div>
                                    <div v-if="item.associated_complaint" class="detail-field">
                                        <span class="detail-label">Associated Complaint:</span>
                                        <p class="detail-val">{{ item.associated_complaint }}</p>
                                    </div>
                                    <div v-if="item.past_history" class="detail-field">
                                        <span class="detail-label">Past History:</span>
                                        <p class="detail-val text-xs text-secondary">{{ item.past_history }}</p>
                                    </div>
                                    <div v-if="item.vitals" class="detail-field vitals-summary-box">
                                        <span class="detail-label flex items-center gap-sm" style="color: #ef4444; font-size: 0.65rem;">
                                            <i class="pi pi-heart-fill mr-1 text-xxs"></i>
                                            Vitals Metrics
                                        </span>
                                        <div class="vitals-grid text-xxs">
                                            <span v-if="item.vitals.weight"><strong>Weight:</strong> {{ item.vitals.weight }} kg</span>
                                            <span v-if="item.vitals.temp"><strong>Temp:</strong> {{ item.vitals.temp }}°F</span>
                                            <span v-if="item.vitals.bp"><strong>BP:</strong> {{ item.vitals.bp }}</span>
                                            <span v-if="item.vitals.pulse"><strong>Pulse:</strong> {{ item.vitals.pulse }} bpm</span>
                                            <span v-if="item.vitals.spo2"><strong>SpO2:</strong> {{ item.vitals.spo2 }}%</span>
                                        </div>
                                    </div>
                                    <div v-if="item.diagnosis" class="detail-field">
                                        <span class="detail-label">Diagnosis:</span>
                                        <p class="detail-val font-semibold">{{ item.diagnosis }}</p>
                                    </div>
                                    <div v-if="item.treatment" class="detail-field">
                                        <span class="detail-label">Treatment Notes:</span>
                                        <p class="detail-val">{{ item.treatment }}</p>
                                    </div>
                                    <div v-if="item.medication_instructions" class="detail-field italic-notes mt-1">
                                        <span class="detail-label" style="font-size: 0.65rem;">Medication Instructions:</span>
                                        <p class="detail-val text-xs italic">{{ item.medication_instructions }}</p>
                                    </div>
                                    <div v-if="!item.purpose && !item.complaint && !item.presenting_complaint && !item.associated_complaint && !item.past_history && !item.vitals && !item.diagnosis && !item.treatment && !item.medication_instructions && (!item.medicines || !item.medicines.length)" class="detail-field">
                                        <p class="detail-val text-xs text-secondary italic">No clinical details recorded for this appointment.</p>
                                    </div>
                                </template>

                                <!-- Parcel detail items -->
                                <template v-else-if="item.type === 'parcel'">
                                    <div v-if="item.address" class="detail-field">
                                        <span class="detail-label">Delivery Address:</span>
                                        <p class="detail-val text-xs text-secondary address-text">{{ item.address }}</p>
                                    </div>
                                    <div v-if="item.delivered_date" class="detail-field">
                                        <span class="detail-label">Delivered On:</span>
                                        <p class="detail-val text-xs text-bold">{{ item.delivered_date }}</p>
                                    </div>
                                    <!-- Instructions given if true -->
                                    <div v-if="item.instructions_given" class="detail-field instruction-highlight">
                                        <div class="instruction-title font-semibold text-xxs">
                                            <i class="pi pi-info-circle mr-1"></i>
                                            Instructions Given:
                                        </div>
                                        <p class="instruction-content text-xs italic">{{ item.instruction_note || 'Given verbally' }}</p>
                                    </div>
                                    <div v-if="item.notes" class="detail-field">
                                        <span class="detail-label">Internal Parcel Notes:</span>
                                        <p class="detail-val text-xs italic">{{ item.notes }}</p>
                                    </div>
                                    <div v-if="!item.address && !item.notes && (!item.medicines || !item.medicines.length)" class="detail-field">
                                        <p class="detail-val text-xs text-secondary italic">No delivery details recorded.</p>
                                    </div>
                                </template>

                                <!-- Shared: Prescribed Medicines -->
                                <div v-if="item.medicines && item.medicines.length" class="detail-field">
                                    <span class="detail-label">Medicines:</span>
                                    <div class="med-chips-list">
                                        <Tag
                                            v-for="(med, idx) in item.medicines"
                                            :key="idx"
                                            :value="typeof med === 'string' ? med : (med.label || med.name || med.id || '')"
                                            severity="secondary"
                                            class="timeline-med-chip"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </Drawer>
</template>

<style scoped>
.drawer-header-custom {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.header-title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.header-icon {
    color: var(--primary-color);
    font-size: 1.25rem;
}

.header-title {
    font-weight: 700;
    font-size: 1.15rem;
    color: var(--text-color);
}

.header-patient-info {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-left: 1.75rem;
    font-size: 0.825rem;
}

.drawer-content-container {
    padding: 0.25rem 0;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.patient-contact-card {
    display: flex;
    gap: 1rem;
    background: var(--surface-hover);
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.775rem;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--text-color-secondary);
}

.empty-timeline {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 4rem 1.5rem;
    gap: 0.5rem;
}

.empty-icon {
    font-size: 2.5rem;
    opacity: 0.4;
    color: var(--text-color-secondary);
}

.custom-timeline {
    position: relative;
    padding-left: 1.5rem;
    margin-left: 0.5rem;
    border-left: 2px solid var(--surface-border);
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.timeline-node {
    position: relative;
}

.node-indicator {
    position: absolute;
    left: -2.35rem;
    top: 0.5rem;
    z-index: 2;
}

.node-icon-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--surface-card);
    border: 2px solid var(--surface-border);
    font-size: 0.75rem;
    transition: all 0.2s ease;
}

.timeline-node.appointment .node-icon-wrapper {
    border-color: #0d9488;
    color: #0d9488;
    background: #f0fdfa;
}

.timeline-node.parcel .node-icon-wrapper {
    border-color: #7c3aed;
    color: #7c3aed;
    background: #f5f3ff;
}

.node-card {
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 1rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.node-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.node-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid var(--surface-border);
    padding-bottom: 0.5rem;
    margin-bottom: 0.65rem;
    gap: 0.5rem;
}

.node-title {
    font-size: 0.875rem;
    margin: 0;
    color: var(--text-color);
}

.node-date {
    white-space: nowrap;
    display: flex;
    align-items: center;
}

.node-body {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.detail-field {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.detail-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-color-secondary);
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.detail-val {
    font-size: 0.8rem;
    color: var(--text-color);
    margin: 0;
    line-height: 1.35;
}

.complaint-text {
    font-weight: 500;
}

.address-text {
    background: var(--surface-hover);
    padding: 0.4rem 0.5rem;
    border-radius: 4px;
}

.instruction-highlight {
    background: rgba(13, 148, 136, 0.06);
    border: 1px dashed rgba(13, 148, 136, 0.25);
    border-radius: 6px;
    padding: 0.5rem;
}

.instruction-title {
    color: #0d9488;
    display: flex;
    align-items: center;
}

.instruction-content {
    margin: 0.15rem 0 0 0;
    color: var(--text-color);
}

.med-chips-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-top: 0.15rem;
}

.timeline-med-chip {
    font-size: 0.65rem;
    padding: 0.15rem 0.35rem;
}

.skeleton-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding-left: 0.5rem;
}

.skeleton-item {
    display: flex;
    gap: 1rem;
}

.skeleton-circle {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--surface-border);
    flex-shrink: 0;
}

.skeleton-lines {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.skeleton-line {
    background: var(--surface-border);
    border-radius: 4px;
}

.h-4 {
    height: 1rem;
}

.h-3 {
    height: 0.75rem;
}

.h-6 {
    height: 1.5rem;
}

.w-3\/4 {
    width: 75%;
}

.w-1\/2 {
    width: 50%;
}

.w-full {
    width: 100%;
}

.mt-2 {
    margin-top: 0.5rem;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .4;
    }
}

.animate-pulse {
    animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.text-xxs {
    font-size: 0.675rem;
}

.mr-1 {
    margin-right: 0.25rem;
}

.vitals-summary-box {
    background: rgba(239, 68, 68, 0.04);
    border: 1px solid rgba(239, 68, 68, 0.1);
    border-radius: 6px;
    padding: 0.5rem;
    margin: 0.25rem 0;
}

.vitals-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.25rem 0.5rem;
    margin-top: 0.25rem;
}

.italic-notes {
    background: var(--surface-hover);
    padding: 0.4rem 0.5rem;
    border-radius: 4px;
    border-left: 3px solid var(--primary-color);
}

.timeline-tags-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-bottom: 0.65rem;
}

.mini-tag {
    font-size: 0.65rem !important;
    padding: 0.15rem 0.4rem !important;
}
</style>
