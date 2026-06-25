<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Divider from 'primevue/divider';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';
import { dashboard } from '@/routes';
import { vitalsTracking } from '@/routes/admin';
import { update as updateVitalsAction } from '@/routes/admin/vitals-tracking';

type Session = 'Morning' | 'Evening';

type AppointmentListItem = {
    id: number;
    patientId: number | null;
    patientName: string;
    gender: string | null;
    age: number | null;
    phone: string | null;
    session: Session;
    appointmentNumber: string | null;
    appointmentSequence: number;
    status: string;
    hasVitals: boolean;
};

type SelectedAppointmentDetails = AppointmentListItem & {
    vitals: {
        temperature: string;
        weight: string;
        blood_pressure: string;
        pulse_rate: string;
        spo2: string;
        notes: string;
    };
};

const props = defineProps<{
    appointments: AppointmentListItem[];
    selectedId: number | null;
    selectedAppointment: SelectedAppointmentDetails | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Vitals Tracking', href: vitalsTracking() },
        ],
    },
});

const toast = useToast();
const searchFilter = ref('');
const sessionFilter = ref<'All' | 'Morning' | 'Evening'>('All');

// Filter appointments list based on search term and session
const filteredAppointments = computed(() => {
    return props.appointments.filter((apt) => {
        const matchesSearch = apt.patientName
            .toLowerCase()
            .includes(searchFilter.value.toLowerCase()) ||
            (apt.appointmentNumber || '').toLowerCase().includes(searchFilter.value.toLowerCase());
        
        const matchesSession = sessionFilter.value === 'All' || apt.session === sessionFilter.value;
        
        return matchesSearch && matchesSession;
    });
});

// Setup form using Inertia useForm
const form = useForm({
    temperature: '',
    weight: '',
    blood_pressure: '',
    pulse_rate: '',
    spo2: '',
    notes: '',
});

// Prefill form when selected appointment changes
watch(
    () => props.selectedAppointment,
    (appointment) => {
        if (appointment && appointment.vitals) {
            form.temperature = appointment.vitals.temperature || '';
            form.weight = appointment.vitals.weight || '';
            form.blood_pressure = appointment.vitals.blood_pressure || '';
            form.pulse_rate = appointment.vitals.pulse_rate || '';
            form.spo2 = appointment.vitals.spo2 || '';
            form.notes = appointment.vitals.notes || '';
        } else {
            form.reset();
        }
    },
    { immediate: true },
);

function formatToken(sequence: number): string {
    return sequence.toString().padStart(2, '0');
}

function selectAppointment(id: number): void {
    router.get(
        vitalsTracking({ query: { selected: id } }).url,
        undefined,
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

// Form submission
function submitForm(): void {
    if (!props.selectedAppointment) {
        return;
    }

    form.patch(updateVitalsAction(props.selectedAppointment.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: 'Patient vitals updated successfully.',
                life: 3000,
            });
        },
    });
}
</script>

<template>
    <Head title="Vitals & Basic Info Tracking" />

    <div class="page-grid vitals-tracking-page">
        <!-- Left panel: Today's Appointments -->
        <div class="appointments-list-panel">
            <Card class="sakai-card list-card">
                <template #title>
                    <div class="panel-header">
                        <h3 class="panel-title">Queue Vitals Entry</h3>
                        <p class="panel-subtitle">Select an appointment to record basic patient details.</p>
                    </div>
                </template>
                <template #content>
                    <!-- Search & Session filters -->
                    <div class="filters-container">
                        <span class="p-input-icon-left w-full search-input-wrapper">
                            <i class="pi pi-search search-icon"></i>
                            <InputText
                                v-model="searchFilter"
                                placeholder="Search by patient name..."
                                class="w-full search-input"
                            />
                        </span>
                        
                        <div class="session-filters">
                            <button
                                type="button"
                                class="filter-chip-btn"
                                :class="{ 'is-active': sessionFilter === 'All' }"
                                @click="sessionFilter = 'All'"
                            >
                                All Sessions
                            </button>
                            <button
                                type="button"
                                class="filter-chip-btn"
                                :class="{ 'is-active': sessionFilter === 'Morning' }"
                                @click="sessionFilter = 'Morning'"
                            >
                                Morning
                            </button>
                            <button
                                type="button"
                                class="filter-chip-btn"
                                :class="{ 'is-active': sessionFilter === 'Evening' }"
                                @click="sessionFilter = 'Evening'"
                            >
                                Evening
                            </button>
                        </div>
                    </div>

                    <Divider />

                    <div v-if="filteredAppointments.length === 0" class="empty-list-state">
                        <i class="pi pi-search-minus"></i>
                        <p>No matching appointments found.</p>
                    </div>
                    <ul v-else class="queue-list">
                        <li
                            v-for="apt in filteredAppointments"
                            :key="apt.id"
                            class="queue-item"
                            :class="{ 'is-selected': selectedId === apt.id }"
                            @click="selectAppointment(apt.id)"
                        >
                            <div class="queue-item__header">
                                <div class="queue-token">
                                    Token {{ formatToken(apt.appointmentSequence) }}
                                    <span class="muted-text">· {{ apt.session }}</span>
                                </div>
                                <div class="queue-badges">
                                    <Tag
                                        :value="apt.hasVitals ? 'Vitals Recorded' : 'Needs Vitals'"
                                        :severity="apt.hasVitals ? 'success' : 'warn'"
                                        class="status-tag"
                                    />
                                    <Tag
                                        v-if="apt.status === 'complete'"
                                        value="Complete"
                                        severity="secondary"
                                        class="status-tag"
                                    />
                                </div>
                            </div>
                            <h4 class="queue-patient-name">{{ apt.patientName }}</h4>
                            <p class="queue-meta-text">
                                <span v-if="apt.age">{{ apt.age }} yrs</span>
                                <span v-if="apt.gender"> · {{ apt.gender }}</span>
                                <span class="badge-status-dot ml-2" :class="`status-${apt.status}`"></span>
                                <span class="capitalize ml-1 text-xs text-secondary">{{ apt.status }}</span>
                            </p>
                        </li>
                    </ul>
                </template>
            </Card>
        </div>

        <!-- Right panel: Details & Form -->
        <div class="details-process-panel">
            <Card v-if="selectedAppointment" class="sakai-card details-card">
                <template #content>
                    <div class="selected-patient-header">
                        <div class="patient-identity">
                            <div class="patient-token-badge">
                                Token {{ formatToken(selectedAppointment.appointmentSequence) }}
                            </div>
                            <div>
                                <h3 class="patient-name">{{ selectedAppointment.patientName }}</h3>
                                <p class="patient-sub">
                                    {{ selectedAppointment.session }} Session · {{ selectedAppointment.status }}
                                    <span v-if="selectedAppointment.age">· {{ selectedAppointment.age }}y</span>
                                    <span v-if="selectedAppointment.gender">· {{ selectedAppointment.gender }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="patient-contact">
                            <span v-if="selectedAppointment.phone"><i class="pi pi-phone mr-1"></i> {{ selectedAppointment.phone }}</span>
                        </div>
                    </div>

                    <Divider />

                    <form @submit.prevent="submitForm" class="vitals-form">
                        <h4 class="section-title"><i class="vital-icon icon-heart pi pi-heart-fill"></i>Record Patient Vitals</h4>
                        
                        <div class="vitals-input-grid">
                            <!-- Temperature -->
                            <div class="vitals-field">
                                <label for="vital-temp">
                                    <i class="vital-icon icon-temp pi pi-sun"></i> Temperature
                                </label>
                                <div class="p-inputgroup">
                                    <InputText
                                        id="vital-temp"
                                        v-model="form.temperature"
                                        placeholder="e.g. 98.6"
                                        class="w-full input-field-rounded"
                                    />
                                    <span class="inputgroup-addon">°F</span>
                                </div>
                                <span class="help-text">Normal: 97.8°F - 99°F</span>
                            </div>

                            <!-- Weight -->
                            <div class="vitals-field">
                                <label for="vital-weight">
                                    <i class="vital-icon icon-weight pi pi-gauge"></i> Weight
                                </label>
                                <div class="p-inputgroup">
                                    <InputText
                                        id="vital-weight"
                                        v-model="form.weight"
                                        placeholder="e.g. 70"
                                        class="w-full input-field-rounded"
                                    />
                                    <span class="inputgroup-addon">kg</span>
                                </div>
                                <span class="help-text">Measure without heavy clothing</span>
                            </div>

                            <!-- Blood Pressure -->
                            <div class="vitals-field">
                                <label for="vital-bp">
                                    <i class="vital-icon icon-bp pi pi-activity"></i> Blood Pressure
                                </label>
                                <div class="p-inputgroup">
                                    <InputText
                                        id="vital-bp"
                                        v-model="form.blood_pressure"
                                        placeholder="e.g. 120/80"
                                        class="w-full input-field-rounded"
                                    />
                                    <span class="inputgroup-addon">mmHg</span>
                                </div>
                                <span class="help-text">Normal: &lt; 120/80 mmHg</span>
                            </div>

                            <!-- Pulse Rate -->
                            <div class="vitals-field">
                                <label for="vital-pulse">
                                    <i class="vital-icon icon-pulse pi pi-heart-fill"></i> Heart / Pulse Rate
                                </label>
                                <div class="p-inputgroup">
                                    <InputText
                                        id="vital-pulse"
                                        v-model="form.pulse_rate"
                                        placeholder="e.g. 72"
                                        class="w-full input-field-rounded"
                                    />
                                    <span class="inputgroup-addon">bpm</span>
                                </div>
                                <span class="help-text">Normal: 60 - 100 bpm</span>
                            </div>

                            <!-- SpO2 -->
                            <div class="vitals-field">
                                <label for="vital-spo2">
                                    <i class="vital-icon icon-spo2 pi pi-percentage"></i> Oxygen Level (SpO2)
                                </label>
                                <div class="p-inputgroup">
                                    <InputText
                                        id="vital-spo2"
                                        v-model="form.spo2"
                                        placeholder="e.g. 98"
                                        class="w-full input-field-rounded"
                                    />
                                    <span class="inputgroup-addon">%</span>
                                </div>
                                <span class="help-text">Normal: 95% - 100%</span>
                            </div>
                        </div>

                        <Divider class="my-4" />

                        <!-- Vitals Notes -->
                        <div class="vitals-field full-width">
                            <label for="vital-notes">
                                <i class="vital-icon icon-notes pi pi-comment"></i> Vitals / Basic Info Notes
                            </label>
                            <textarea
                                id="vital-notes"
                                v-model="form.notes"
                                rows="4"
                                placeholder="Add comments regarding patient's general appearance, physical distress, active complaints, or special requirements..."
                                class="p-inputtext w-full notes-textarea"
                            ></textarea>
                            <span class="help-text">Will be visible to the doctor at the booking desk details view</span>
                        </div>

                        <div class="form-submit-actions">
                            <Button
                                type="submit"
                                icon="pi pi-save"
                                label="Save Patient Vitals"
                                severity="primary"
                                :loading="form.processing"
                                class="submit-btn"
                            />
                        </div>
                    </form>
                </template>
            </Card>

            <Card v-else class="sakai-card empty-details-card">
                <template #content>
                    <div class="empty-state-workspace">
                        <i class="pi pi-heart"></i>
                        <h3>No patient selected</h3>
                        <p>Choose an appointment from the queue on the left to start recording or updating vitals.</p>
                    </div>
                </template>
            </Card>
        </div>
    </div>
</template>

<style scoped>
.vitals-tracking-page {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
    gap: 1.5rem;
    align-items: stretch;
}

@media (max-width: 1024px) {
    .vitals-tracking-page {
        grid-template-columns: 1fr;
    }
}

/* Sakai Theme Rounded Style overrides */
:deep(.p-inputtext),
:deep(.p-button),
:deep(.p-tag),
:deep(.p-card),
.sakai-card,
.list-card,
.details-card,
.empty-details-card,
.queue-item,
.patient-token-badge,
.notes-textarea,
.p-inputgroup,
.input-field-rounded,
.inputgroup-addon {
    border-radius: 6px !important;
}

.list-card, .details-card, .empty-details-card {
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    height: 100%;
}

.panel-header {
    margin-bottom: 0.5rem;
}

.panel-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
}

.panel-subtitle {
    margin: 0.25rem 0 0;
    color: var(--text-color-secondary);
    font-size: 0.85rem;
}

.filters-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 0.25rem;
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    color: var(--text-color-secondary);
}

.search-input {
    padding-left: 2.25rem !important;
}

.session-filters {
    display: flex;
    gap: 0.5rem;
}

.filter-chip-btn {
    border: 1px solid var(--surface-border);
    background: var(--surface-50);
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--text-color);
}

.filter-chip-btn:hover {
    background: var(--surface-100);
    border-color: var(--p-primary-300);
}

.filter-chip-btn.is-active {
    background: var(--p-primary-500);
    border-color: var(--p-primary-500);
    color: white;
}

.empty-list-state, .empty-state-workspace {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 3rem 1.5rem;
    color: var(--text-color-secondary);
}

.empty-list-state i, .empty-state-workspace i {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.empty-state-workspace h3 {
    margin: 0 0 0.5rem;
    font-size: 1.4rem;
    color: var(--text-color);
}

.queue-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-height: 600px;
    overflow-y: auto;
}

.queue-item {
    padding: 1rem;
    border: 1px solid var(--surface-border);
    background: var(--surface-50);
    cursor: pointer;
    transition: all 0.2s ease;
}

.queue-item:hover {
    border-color: var(--p-primary-400);
    background: var(--surface-100);
}

.queue-item.is-selected {
    border-color: var(--p-primary-500);
    background: color-mix(in srgb, var(--p-primary-500) 8%, var(--surface-card));
    box-shadow: inset 4px 0 0 0 var(--p-primary-500);
}

.queue-item__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.queue-token {
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--p-primary-600);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.muted-text {
    color: var(--text-color-secondary);
    font-weight: normal;
}

.queue-badges {
    display: flex;
    gap: 0.25rem;
}

.status-tag {
    font-size: 0.7rem;
}

.queue-patient-name {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-color);
}

.queue-meta-text {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-color-secondary);
    display: flex;
    align-items: center;
}

.badge-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.status-pending {
    background-color: var(--p-amber-500);
}

.status-complete {
    background-color: var(--p-green-500);
}

.status-cancelled {
    background-color: var(--p-red-500);
}

.selected-patient-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.patient-identity {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.patient-token-badge {
    background: var(--p-primary-50);
    color: var(--p-primary-700);
    border: 1px solid var(--p-primary-200);
    padding: 0.5rem 0.75rem;
    font-weight: 800;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.patient-name {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-color);
}

.patient-sub {
    margin: 0.15rem 0 0;
    font-size: 0.85rem;
    color: var(--text-color-secondary);
    text-transform: capitalize;
}

.patient-contact {
    font-size: 0.9rem;
    color: var(--text-color-secondary);
    font-weight: 500;
}

.section-title {
    margin: 0 0 1rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-color);
    display: flex;
    align-items: center;
}

.vitals-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.vitals-input-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
}

.vitals-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.vitals-field label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-color);
    display: flex;
    align-items: center;
}

.p-inputgroup {
    display: flex;
    width: 100%;
}

.inputgroup-addon {
    background: var(--surface-100);
    border: 1px solid var(--surface-border);
    border-left: 0;
    padding: 0.5rem 0.75rem;
    color: var(--text-color-secondary);
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    white-space: nowrap;
}

.help-text {
    font-size: 0.75rem;
    color: var(--text-color-secondary);
    margin-top: 0.1rem;
}

.full-width {
    grid-column: 1 / -1;
}

.notes-textarea {
    resize: vertical;
    padding: 0.75rem;
    font-family: inherit;
    font-size: 0.95rem;
    line-height: 1.5;
}

.form-submit-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 0.5rem;
}

.submit-btn {
    padding: 0.75rem 1.5rem !important;
    font-weight: 600;
}

.vital-icon {
    margin-right: 0.5rem;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    vertical-align: middle;
}

.vital-icon.icon-heart {
    color: #ef4444; /* Red */
    font-size: 1.15rem;
}

.vital-icon.icon-temp {
    color: #f97316; /* Orange */
}

.vital-icon.icon-weight {
    color: #3b82f6; /* Blue */
}

.vital-icon.icon-bp {
    color: #a855f7; /* Purple */
}

.vital-icon.icon-pulse {
    color: #ef4444; /* Red */
}

.vital-icon.icon-spo2 {
    color: #14b8a6; /* Teal */
}

.vital-icon.icon-notes {
    color: #6b7280; /* Gray */
}
</style>
