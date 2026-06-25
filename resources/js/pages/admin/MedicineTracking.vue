<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Divider from 'primevue/divider';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';
import { dashboard } from '@/routes';
import { medicineTracking } from '@/routes/admin';
import { update as updateMedicineAction } from '@/routes/admin/medicine-tracking';
import http from '@/lib/http';
import { search as searchMedicinesAction } from '@/routes/admin/medicines';

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
    appointmentType: string;
    status: string;
    medicineStatus: boolean;
    amount: string | null;
    paymentType: string;
    diagnosis: string | null;
    treatment: string | null;
    medicationInstructions: string | null;
};

type PrescribedMedicine = {
    name: string;
    category: string;
    size: string;
    quantity: number;
};

type SelectedAppointmentDetails = AppointmentListItem & {
    medicines: PrescribedMedicine[];
};

type PreviousPrescription = {
    id: number;
    date: string;
    dateLabel: string;
    medicines: PrescribedMedicine[];
    treatment: string | null;
    diagnosis: string | null;
};

type MedicineInventoryItem = {
    id: number;
    medicine_id?: number;
    category_id?: number;
    size_id?: number;
    name: string;
    category: string | null;
    size: string | null;
    quantity: number;
};

const props = defineProps<{
    appointments: AppointmentListItem[];
    selectedId: number | null;
    selectedAppointment: SelectedAppointmentDetails | null;
    previousMedicines: PreviousPrescription[];
    medicinesInventory: MedicineInventoryItem[];
    categories: string[];
    sizes: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Medicine Tracking', href: medicineTracking() },
        ],
    },
});

const toast = useToast();
const activeTab = ref<'current' | 'history'>('current');

const quickFillSelected = ref<any[]>([]);
const quickFillSuggestions = ref<any[]>([]);

async function searchQuickFill(event: { query: string }): Promise<void> {
    const query = event.query.trim();
    if (!query) {
        quickFillSuggestions.value = [];
        return;
    }
    try {
        const { data } = await http.get(searchMedicinesAction({ query: { query } }).url);
        quickFillSuggestions.value = data;
    } catch {
        quickFillSuggestions.value = [];
    }
}

// Setup form using Inertia useForm
const form = useForm({
    medicines: [] as PrescribedMedicine[],
    amount: 0,
    payment_type: 'Cash',
});

// Prefill form when selected appointment changes
watch(
    () => props.selectedAppointment,
    (appointment) => {
        if (appointment) {
            form.medicines = (appointment.medicines || []).map((med: any) => {
                if (med && med.id) {
                    const parts = med.id.split('|').map((s: string) => s.trim());
                    const medicineId = parseInt(parts[0], 10);
                    const categoryId = parts[1] ? parseInt(parts[1], 10) : null;
                    const sizeId = parts[2] ? parseInt(parts[2], 10) : null;
                    const found = props.medicinesInventory.find((m) => {
                        if (m.medicine_id !== undefined && m.category_id !== undefined && m.size_id !== undefined) {
                            return m.medicine_id === medicineId && m.category_id === categoryId && m.size_id === sizeId;
                        }
                        return m.id === medicineId;
                    });
                    if (found) {
                        return {
                            name: found.name,
                            category: found.category || '',
                            size: found.size || '',
                            quantity: med.quantity || 1,
                        };
                    }
                    const labelParts = (med.label || '').split('|').map((s: string) => s.trim());
                    return {
                        name: labelParts[0] || med.label || '',
                        category: labelParts[1] || '',
                        size: '',
                        quantity: med.quantity || 1,
                    };
                }
                return {
                    name: med.name || '',
                    category: med.category || '',
                    size: med.size || '',
                    quantity: med.quantity || 1,
                };
            });
            quickFillSelected.value = (appointment.medicines || []).map((med: any) => {
                if (med) {
                    if (med.label) {
                        return { label: med.label };
                    }
                    if (med.name) {
                        return { label: `${med.name.toUpperCase()} | ${med.category?.toUpperCase() || ''} | ${med.size?.toUpperCase() || ''}`.trim() };
                    }
                }
                return null;
            });
            form.amount = appointment.amount ? Number(appointment.amount) : 0;
            form.payment_type = appointment.paymentType || 'Cash';
        } else {
            form.medicines = [];
            quickFillSelected.value = [];
            form.amount = 0;
            form.payment_type = 'Cash';
        }
    },
    { immediate: true },
);

const parsedPreviousMedicines = computed(() => {
    return (props.previousMedicines || []).map((historyItem) => {
        const parsedMeds = (historyItem.medicines || []).map((med: any) => {
            if (med && med.id) {
                const parts = med.id.split('|').map((s: string) => s.trim());
                const medicineId = parseInt(parts[0], 10);
                const categoryId = parts[1] ? parseInt(parts[1], 10) : null;
                const sizeId = parts[2] ? parseInt(parts[2], 10) : null;
                const found = props.medicinesInventory.find((m) => {
                    if (m.medicine_id !== undefined && m.category_id !== undefined && m.size_id !== undefined) {
                        return m.medicine_id === medicineId && m.category_id === categoryId && m.size_id === sizeId;
                    }
                    return m.id === medicineId;
                });
                if (found) {
                    return {
                        name: found.name,
                        category: found.category || '',
                        size: found.size || '',
                        quantity: med.quantity || 1,
                    };
                }
                const labelParts = (med.label || '').split('|').map((s: string) => s.trim());
                return {
                    name: labelParts[0] || med.label || '',
                    category: labelParts[1] || '',
                    size: '',
                    quantity: med.quantity || 1,
                };
            }
            return {
                name: med.name || '',
                category: med.category || '',
                size: med.size || '',
                quantity: med.quantity || 1,
            };
        });
        return {
            ...historyItem,
            medicines: parsedMeds,
        };
    });
});

function formatToken(sequence: number): string {
    return sequence.toString().padStart(2, '0');
}

function selectAppointment(id: number): void {
    router.get(
        medicineTracking({ query: { selected: id } }).url,
        undefined,
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

// Medicine rows operations
function addMedicineRow(): void {
    form.medicines.push({
        name: '',
        category: '',
        size: '',
        quantity: 1,
    });
    quickFillSelected.value.push(null);
}

function removeMedicineRow(index: number): void {
    form.medicines.splice(index, 1);
    quickFillSelected.value.splice(index, 1);
}

function onInventoryMedSelected(option: any, index: number): void {
    if (!option) {
        return;
    }
    const parts = option.label.split('|').map((s: string) => s.trim());
    form.medicines[index] = {
        name: parts[0] || option.name || '',
        category: parts[1] || option.category || '',
        size: parts[2] || option.sizeName || option.size || '',
        quantity: form.medicines[index]?.quantity || 1,
    };
}

function copyPastPrescription(medicines: PrescribedMedicine[]): void {
    if (!medicines || medicines.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'Prescription History',
            detail: 'No medicines to copy.',
            life: 3000,
        });
        return;
    }
    
    // Merge or overwrite? Let's append them to existing current medicines list
    medicines.forEach((med) => {
        form.medicines.push({
            name: med.name,
            category: med.category,
            size: med.size,
            quantity: med.quantity,
        });
        quickFillSelected.value.push({
            label: `${med.name.toUpperCase()} | ${med.category?.toUpperCase() || ''} | ${med.size?.toUpperCase() || ''}`.trim()
        });
    });

    toast.add({
        severity: 'success',
        summary: 'Copied',
        detail: `Added ${medicines.length} medicines to current prescription.`,
        life: 3000,
    });
}

function copyPastMedicine(med: PrescribedMedicine): void {
    form.medicines.push({
        name: med.name,
        category: med.category,
        size: med.size,
        quantity: med.quantity,
    });
    quickFillSelected.value.push({
        label: `${med.name.toUpperCase()} | ${med.category?.toUpperCase() || ''} | ${med.size?.toUpperCase() || ''}`.trim()
    });

    toast.add({
        severity: 'success',
        summary: 'Copied',
        detail: `Added ${med.name} to current prescription.`,
        life: 3000,
    });
}

// Form submission
function submitForm(): void {
    if (!props.selectedAppointment) {
        return;
    }

    form.patch(updateMedicineAction(props.selectedAppointment.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Completed',
                detail: 'Prescription & Payment updated successfully.',
                life: 3500,
            });
        },
    });
}
</script>

<template>
    <Head title="Medicine Tracking & Billing" />

    <div class="page-grid medicine-tracking-page">
        <!-- Left panel: Today's Appointments -->
        <div class="appointments-list-panel">
            <Card class="sakai-card list-card">
                <template #title>
                    <div class="panel-header">
                        <h3 class="panel-title">Today's Dispatch Queue</h3>
                        <p class="panel-subtitle">Select an appointment to process medicines and bill.</p>
                    </div>
                </template>
                <template #content>
                    <div v-if="appointments.length === 0" class="empty-list-state">
                        <i class="pi pi-calendar-times"></i>
                        <p>No appointments scheduled for today.</p>
                    </div>
                    <ul v-else class="queue-list">
                        <li
                            v-for="apt in appointments"
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
                                        :value="apt.medicineStatus ? 'Dispatched' : 'Pending'"
                                        :severity="apt.medicineStatus ? 'success' : 'warn'"
                                        class="status-tag"
                                    />
                                    <Tag
                                        v-if="apt.amount"
                                        :value="`$${apt.amount} (${apt.paymentType})`"
                                        severity="info"
                                        class="status-tag"
                                    />
                                    <Tag
                                        v-else
                                        value="Unpaid"
                                        severity="danger"
                                        class="status-tag"
                                    />
                                </div>
                            </div>
                            <h4 class="queue-patient-name">{{ apt.patientName }}</h4>
                            <p class="queue-meta-text">
                                {{ apt.appointmentType }}
                                <span v-if="apt.age">· {{ apt.age }} yrs</span>
                                <span v-if="apt.gender">· {{ apt.gender }}</span>
                            </p>
                        </li>
                    </ul>
                </template>
            </Card>
        </div>

        <!-- Right panel: Details & Processing -->
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
                                    {{ selectedAppointment.session }} Session · {{ selectedAppointment.appointmentType }}
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

                    <!-- Doctor's clinical notes -->
                    <div class="clinical-notes-section">
                        <h4 class="section-title"><i class="pi pi-file-edit mr-2"></i>Doctor's Notes & Instructions</h4>
                        <div class="notes-grid">
                            <div class="note-box">
                                <span class="note-label">Diagnosis</span>
                                <p class="note-content">{{ selectedAppointment.diagnosis || 'Not specified' }}</p>
                            </div>
                            <div class="note-box">
                                <span class="note-label">Treatment Plan</span>
                                <p class="note-content">{{ selectedAppointment.treatment || 'Not specified' }}</p>
                            </div>
                            <div class="note-box note-box--wide">
                                <span class="note-label">Medication Instructions</span>
                                <p class="note-content instructions-content">{{ selectedAppointment.medicationInstructions || 'No instructions provided' }}</p>
                            </div>
                        </div>
                    </div>

                    <Divider />

                    <!-- Subtabs for dispatch / previous history -->
                    <div class="tabs-header">
                        <button
                            type="button"
                            class="tab-btn"
                            :class="{ 'is-active': activeTab === 'current' }"
                            @click="activeTab = 'current'"
                        >
                            <i class="pi pi-box mr-2"></i>Dispense Medicines
                        </button>
                        <button
                            type="button"
                            class="tab-btn"
                            :class="{ 'is-active': activeTab === 'history' }"
                            @click="activeTab = 'history'"
                        >
                            <i class="pi pi-history mr-2"></i>Previous Prescriptions History ({{ parsedPreviousMedicines.length }})
                        </button>
                    </div>

                    <!-- Tab: Dispense Medicines -->
                    <div v-show="activeTab === 'current'" class="tab-pane dispense-pane">
                        <form @submit.prevent="submitForm" class="dispense-form">
                            <div class="prescription-editor-header">
                                <h4 class="section-title">Current Prescription</h4>
                                <Button
                                    type="button"
                                    icon="pi pi-plus"
                                    label="Add Medicine Row"
                                    size="small"
                                    outlined
                                    @click="addMedicineRow"
                                />
                            </div>

                            <div v-if="form.medicines.length === 0" class="no-medicines-alert">
                                <p>No medicines added to this prescription yet. Click "Add Medicine Row" or copy from past history.</p>
                            </div>
                            <div v-else class="medicine-table-container">
                                <table class="medicine-edit-table">
                                    <thead>
                                        <tr>
                                            <th>Quick-Fill from Inventory</th>
                                            <th>Medicine Name</th>
                                            <th>Category</th>
                                            <th>Size</th>
                                            <th style="width: 7rem">Qty</th>
                                            <th style="width: 4rem"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(med, idx) in form.medicines" :key="idx">
                                            <td>
                                                <AutoComplete
                                                    v-model="quickFillSelected[idx]"
                                                    :suggestions="quickFillSuggestions"
                                                    option-label="label"
                                                    placeholder="Search store..."
                                                    forceSelection
                                                    fluid
                                                    class="inventory-select"
                                                    @complete="searchQuickFill"
                                                    @option-select="(e) => onInventoryMedSelected(e.value, idx)"
                                                />
                                            </td>
                                            <td>
                                                <InputText v-model="med.name" placeholder="Name" class="w-full" disabled required />
                                            </td>
                                            <td>
                                                <InputText v-model="med.category" placeholder="Category" class="w-full" disabled required />
                                            </td>
                                            <td>
                                                <InputText v-model="med.size" placeholder="Size" class="w-full" disabled required />
                                            </td>
                                            <td>
                                                <InputNumber v-model="med.quantity" :min="1" show-buttons class="qty-input" />
                                            </td>
                                            <td class="text-center">
                                                <Button
                                                    type="button"
                                                    icon="pi pi-trash"
                                                    severity="danger"
                                                    text
                                                    @click="removeMedicineRow(idx)"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <Divider />

                            <!-- Payment details section -->
                            <div class="billing-details-section">
                                <h4 class="section-title"><i class="pi pi-money-bill mr-2"></i>Payment Details</h4>
                                <div class="billing-form-grid">
                                    <div class="billing-field">
                                        <label for="payment-amount">Amount Due ($)</label>
                                        <InputNumber
                                            id="payment-amount"
                                            v-model="form.amount"
                                            mode="decimal"
                                            :min="0"
                                            placeholder="Enter fee amount"
                                            class="w-full"
                                            required
                                        />
                                    </div>
                                    <div class="billing-field">
                                        <label for="payment-type">Payment Type</label>
                                        <Select
                                            id="payment-type"
                                            v-model="form.payment_type"
                                            :options="['Cash', 'Card', 'UPI', 'Bank Transfer']"
                                            placeholder="Select payment type"
                                            class="w-full"
                                            required
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="form-submit-actions">
                                <Button
                                    type="submit"
                                    icon="pi pi-check-circle"
                                    label="Complete Dispatch & Save Payment"
                                    severity="success"
                                    :loading="form.processing"
                                    class="submit-btn"
                                />
                            </div>
                        </form>
                    </div>

                    <!-- Tab: Previous Prescription History -->
                    <div v-show="activeTab === 'history'" class="tab-pane history-pane">
                        <div v-if="parsedPreviousMedicines.length === 0" class="empty-history-state">
                            <i class="pi pi-history"></i>
                            <p>No completed past appointments found for this patient.</p>
                        </div>
                        <div v-else class="history-timeline">
                            <div v-for="historyItem in parsedPreviousMedicines" :key="historyItem.id" class="history-record-card">
                                <div class="history-record-header">
                                    <div class="history-date">
                                        <i class="pi pi-calendar mr-2"></i>{{ historyItem.dateLabel }}
                                    </div>
                                    <Button
                                        type="button"
                                        icon="pi pi-copy"
                                        label="Re-prescribe All"
                                        size="small"
                                        outlined
                                        @click="copyPastPrescription(historyItem.medicines)"
                                    />
                                </div>
                                <div class="history-record-body">
                                    <div v-if="historyItem.diagnosis || historyItem.treatment" class="history-notes-summary">
                                        <span v-if="historyItem.diagnosis"><strong>Diag:</strong> {{ historyItem.diagnosis }}</span>
                                        <span v-if="historyItem.treatment" class="ml-4"><strong>Rx:</strong> {{ historyItem.treatment }}</span>
                                    </div>
                                    
                                    <table class="history-meds-table">
                                        <thead>
                                            <tr>
                                                <th>Medicine</th>
                                                <th>Category</th>
                                                <th>Size</th>
                                                <th>Qty</th>
                                                <th style="width: 5rem"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(med, mIdx) in historyItem.medicines" :key="mIdx">
                                                <td><strong>{{ med.name }}</strong></td>
                                                <td>{{ med.category }}</td>
                                                <td>{{ med.size }}</td>
                                                <td>{{ med.quantity }}</td>
                                                <td class="text-right">
                                                    <Button
                                                        type="button"
                                                        icon="pi pi-plus"
                                                        label="Add"
                                                        size="small"
                                                        text
                                                        @click="copyPastMedicine(med)"
                                                    />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <Card v-else class="sakai-card empty-details-card">
                <template #content>
                    <div class="empty-state-workspace">
                        <i class="pi pi-inbox"></i>
                        <h3>No patient selected</h3>
                        <p>Choose an appointment from the queue on the left to start dispensing medicines and recording payment details.</p>
                    </div>
                </template>
            </Card>
        </div>
    </div>
</template>

<style scoped>
.medicine-tracking-page {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
    gap: 1.5rem;
    align-items: stretch;
}

@media (max-width: 1024px) {
    .medicine-tracking-page {
        grid-template-columns: 1fr;
    }
}

/* Sakai Theme Rounded Style overrides */
:deep(.p-inputtext),
:deep(.p-inputnumber),
:deep(.p-inputnumber-input),
:deep(.p-inputnumber-button),
:deep(.p-button),
:deep(.p-select),
:deep(.p-select-label),
:deep(.p-select-dropdown),
:deep(.p-tag),
:deep(.p-card),
.sakai-card,
.list-card,
.details-card,
.empty-details-card,
.queue-item,
.patient-token-badge,
.clinical-notes-section,
.note-content,
.no-medicines-alert,
.medicine-table-container,
.billing-details-section,
.history-record-card,
.history-notes-summary {
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

.empty-list-state, .empty-state-workspace, .empty-history-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 3rem 1.5rem;
    color: var(--text-color-secondary);
}

.empty-list-state i, .empty-state-workspace i, .empty-history-state i {
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
    font-weight: 700;
}

.queue-patient-name {
    margin: 0 0 0.25rem;
    font-size: 1.05rem;
    font-weight: 600;
}

.queue-meta-text {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-color-secondary);
}

/* Right Panel Styles */
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
    padding: 0.5rem 0.8rem;
    background: color-mix(in srgb, var(--p-primary-500) 12%, white);
    color: var(--p-primary-700);
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-radius: 4px;
}

.patient-name {
    margin: 0 0 0.15rem;
    font-size: 1.4rem;
    font-weight: 700;
}

.patient-sub {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-color-secondary);
}

.patient-contact {
    font-size: 0.9rem;
    color: var(--text-color-secondary);
    font-weight: 600;
}

.patient-contact span {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.section-title {
    margin: 0 0 1rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-color);
    display: flex;
    align-items: center;
}

.section-title i,
.tab-btn i,
.history-date i,
.patient-contact i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    vertical-align: middle;
    margin-right: 0.5rem !important;
}

/* Clinical notes section */
.clinical-notes-section {
    background: var(--surface-50);
    border: 1px solid var(--surface-border);
    padding: 1.25rem;
    margin-bottom: 1rem;
}

.notes-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

@media (max-width: 768px) {
    .notes-grid {
        grid-template-columns: 1fr;
    }
}

.note-box {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.note-box--wide {
    grid-column: span 2;
}

@media (max-width: 768px) {
    .note-box--wide {
        grid-column: span 1;
    }
}

.note-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--text-color-secondary);
    letter-spacing: 0.05em;
}

.note-content {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.5;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
}

.instructions-content {
    background: color-mix(in srgb, var(--p-primary-500) 3%, var(--surface-card));
    border-left: 3px solid var(--p-primary-500);
}

/* Tabs */
.tabs-header {
    display: flex;
    border-bottom: 1px solid var(--surface-border);
    margin-bottom: 1.25rem;
    gap: 0.5rem;
}

.tab-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.75rem 1.25rem;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-weight: 600;
    color: var(--text-color-secondary);
    transition: all 0.2s ease;
}

.tab-btn:hover {
    color: var(--text-color);
    border-bottom-color: var(--surface-300);
}

.tab-btn.is-active {
    color: var(--p-primary-500);
    border-bottom-color: var(--p-primary-500);
}

/* Prescription editor */
.prescription-editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.no-medicines-alert {
    padding: 2rem;
    border: 1px dashed var(--surface-border);
    background: var(--surface-50);
    text-align: center;
    color: var(--text-color-secondary);
}

.medicine-table-container {
    overflow-x: auto;
    border: 1px solid var(--surface-border);
}

.medicine-edit-table {
    width: 100%;
    border-collapse: collapse;
}

.medicine-edit-table th, .medicine-edit-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--surface-border);
}

.medicine-edit-table th {
    background: var(--surface-100);
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--text-color-secondary);
}

.inventory-select {
    width: 13rem;
}

.qty-input :deep(.p-inputnumber-input) {
    width: 4.5rem !important;
}

/* Billing Details */
.billing-details-section {
    background: var(--surface-50);
    border: 1px solid var(--surface-border);
    padding: 1.25rem;
    margin-top: 1rem;
}

.billing-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

@media (max-width: 640px) {
    .billing-form-grid {
        grid-template-columns: 1fr;
    }
}

.billing-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.billing-field label {
    font-size: 0.85rem;
    font-weight: 600;
}

.form-submit-actions {
    margin-top: 1.5rem;
    display: flex;
    justify-content: flex-end;
}

.submit-btn {
    width: 100%;
    padding: 0.75rem;
    font-size: 1.1rem;
    font-weight: 700;
}

@media (min-width: 640px) {
    .submit-btn {
        width: auto;
    }
}

/* History tab styles */
.history-timeline {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.history-record-card {
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
}

.history-record-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    background: var(--surface-50);
    border-bottom: 1px solid var(--surface-border);
}

.history-date {
    display: inline-flex;
    align-items: center;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text-color);
}

.history-record-body {
    padding: 1rem;
}

.history-notes-summary {
    font-size: 0.85rem;
    background: var(--surface-100);
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.75rem;
    color: var(--text-color-secondary);
}

.history-meds-table {
    width: 100%;
    border-collapse: collapse;
}

.history-meds-table th, .history-meds-table td {
    padding: 0.5rem 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--surface-border);
    font-size: 0.9rem;
}

.history-meds-table th {
    background: var(--surface-50);
    font-weight: 600;
    color: var(--text-color-secondary);
}

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}
</style>
