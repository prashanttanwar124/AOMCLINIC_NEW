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
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PatientHistorySidebar from '@/components/PatientHistorySidebar.vue';
import http from '@/lib/http';
import { dashboard } from '@/routes';
import { courierParcels } from '@/routes/admin';
import PatientController from '@/actions/App/Http/Controllers/Admin/PatientController';

type PatientDetails = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    label: string;
};

type CourierParcelItem = {
    id: number;
    patient_id: number;
    patient_name: string;
    patient_phone: string | null;
    patient_email: string | null;
    parcel_status: string;
    parcel_date: string;
    amount: string | number;
    payment_status: string;
    medicines: string[];
    address: string | null;
    notes: string | null;
    delivered_date: string | null;
    instructions_given: boolean;
    instruction_note: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedParcels = {
    data: CourierParcelItem[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
    total: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Courier Parcels', href: '#' },
        ],
    },
});

const props = defineProps<{
    parcels: PaginatedParcels;
    filters: {
        search: string | null;
        status: string | null;
        payment_status: string | null;
    };
    medicinesInventory: string[];
}>();

// Filter values
const searchFilter = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const paymentStatusFilter = ref(props.filters.payment_status ?? '');

// Dialog state
const showDialog = ref(false);
const isEditMode = ref(false);
const editingParcelId = ref<number | null>(null);

const showHistorySidebar = ref(false);
const historyPatientId = ref<number | null>(null);

// Selected patient for autocomplete
const selectedPatient = ref<PatientDetails | null>(null);
const patientSuggestions = ref<PatientDetails[]>([]);

function viewPatientHistory(patientId: number | null): void {
    if (!patientId) return;
    historyPatientId.value = patientId;
    showHistorySidebar.value = true;
}

// Form setup
const form = useForm({
    patient_id: null as number | null,
    parcel_status: 'order_received',
    parcel_date: new Date() as Date | string,
    amount: 0,
    payment_status: 'unpaid',
    medicines: [] as string[],
    address: '',
    notes: '',
    delivered_date: null as Date | string | null,
    instructions_given: false,
    instruction_note: '',
});

// Dropdown options
const statusOptions = [
    { label: 'Order Received', value: 'order_received' },
    { label: 'Packed', value: 'packed' },
    { label: 'Dispatched', value: 'dispatched' },
    { label: 'In Transit', value: 'in_transit' },
    { label: 'Delivered', value: 'delivered' },
    { label: 'Returned', value: 'returned' },
];

const paymentStatusOptions = [
    { label: 'Unpaid', value: 'unpaid' },
    { label: 'Paid', value: 'paid' },
];

const instructionsOptions = [
    { label: 'No', value: false },
    { label: 'Yes', value: true },
];

// Date formatter helper
function toDateString(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Search patients logic
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

// Prefill address when patient is selected
watch(selectedPatient, (newVal) => {
    if (newVal && typeof newVal === 'object' && newVal.id) {
        form.patient_id = newVal.id;
        if (!form.address) {
            form.address = newVal.address || '';
        }
    } else {
        form.patient_id = null;
    }
});

// Open dialog for new parcel
function openNewParcel(): void {
    isEditMode.value = false;
    editingParcelId.value = null;
    selectedPatient.value = null;
    form.reset();
    form.clearErrors();
    form.parcel_date = new Date();
    form.parcel_status = 'order_received';
    form.payment_status = 'unpaid';
    form.amount = 0;
    form.delivered_date = null;
    form.instructions_given = false;
    form.instruction_note = '';
    showDialog.value = true;
}

// Open dialog for editing parcel
function openEditParcel(parcel: CourierParcelItem): void {
    isEditMode.value = true;
    editingParcelId.value = parcel.id;
    form.clearErrors();

    // Setup autocomplete patient
    selectedPatient.value = {
        id: parcel.patient_id,
        name: parcel.patient_name,
        email: parcel.patient_email,
        phone: parcel.patient_phone,
        address: parcel.address,
        label: `${parcel.patient_name} (#${parcel.patient_id})`,
    };

    form.patient_id = parcel.patient_id;
    form.parcel_status = parcel.parcel_status;
    form.parcel_date = parcel.parcel_date ? new Date(parcel.parcel_date) : new Date();
    form.amount = typeof parcel.amount === 'string' ? parseFloat(parcel.amount) : parcel.amount;
    form.payment_status = parcel.payment_status;
    form.medicines = [...(parcel.medicines || [])];
    form.address = parcel.address || '';
    form.notes = parcel.notes || '';
    form.delivered_date = parcel.delivered_date ? new Date(parcel.delivered_date) : null;
    form.instructions_given = parcel.instructions_given || false;
    form.instruction_note = parcel.instruction_note || '';

    showDialog.value = true;
}

// Apply searches & filters
function applyFilters(): void {
    router.get(
        courierParcels().url,
        {
            search: searchFilter.value || undefined,
            status: statusFilter.value || undefined,
            payment_status: paymentStatusFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
}

function clearFilters(): void {
    searchFilter.value = '';
    statusFilter.value = '';
    paymentStatusFilter.value = '';
    applyFilters();
}

// Navigate pages
function goToPage(url: string | null): void {
    if (!url) return;
    router.get(
        url,
        {
            search: searchFilter.value || undefined,
            status: statusFilter.value || undefined,
            payment_status: paymentStatusFilter.value || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
}

// Submit Form
function submitForm(): void {
    form.transform((data) => ({
        ...data,
        parcel_date: data.parcel_date instanceof Date ? toDateString(data.parcel_date) : data.parcel_date,
        delivered_date: data.delivered_date instanceof Date ? toDateString(data.delivered_date) : data.delivered_date,
    }));

    if (isEditMode.value && editingParcelId.value !== null) {
        form.patch(courierParcels.update(editingParcelId.value).url, {
            preserveScroll: true,
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
                selectedPatient.value = null;
            },
        });
    } else {
        form.post(courierParcels.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
                selectedPatient.value = null;
            },
        });
    }
}

// Delete parcel record
function deleteParcel(parcel: CourierParcelItem): void {
    if (confirm(`Are you sure you want to delete the courier parcel for ${parcel.patient_name}?`)) {
        router.delete(courierParcels.destroy(parcel.id).url, {
            preserveScroll: true,
        });
    }
}

// Formatters for status labels & severity colors
function getStatusLabel(status: string): string {
    const found = statusOptions.find(o => o.value === status);
    return found ? found.label : status;
}

function getStatusSeverity(status: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' {
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

function getPaymentStatusLabel(status: string): string {
    const found = paymentStatusOptions.find(o => o.value === status);
    return found ? found.label : status;
}

function getPaymentSeverity(status: string): 'success' | 'danger' | 'secondary' {
    return status === 'paid' ? 'success' : 'danger';
}

function formatCurrency(amount: string | number): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'INR' }).format(num);
}
</script>

<template>
    <Head title="Courier Parcels Management" />

    <div class="page-grid courier-parcels-page">
        <!-- Hero Header Card -->
        <section class="page-card courier-hero">
            <div class="courier-hero__copy">
                <p class="stat-label">Operations Registry</p>
                <h2 class="courier-hero__title">Courier Parcels Dispatch</h2>
                <p class="panel-subtitle courier-hero__subtitle">
                    Record parcel contents, manage delivery statuses, pre-fill addresses, and log notes.
                </p>
            </div>
            <div class="courier-hero__actions">
                <Button
                    label="Add Courier Parcel"
                    icon="pi pi-plus"
                    class="p-button-primary flat-btn"
                    @click="openNewParcel"
                />
            </div>
        </section>

        <!-- Filters Toolbar Card -->
        <article class="page-card filter-card">
            <div class="filter-grid">
                <div class="filter-field">
                    <label for="search-input" class="filter-label">Patient Details</label>
                    <span class="p-input-icon-left w-full search-input-wrapper">
                        <i class="pi pi-search search-icon"></i>
                        <InputText
                            id="search-input"
                            v-model="searchFilter"
                            placeholder="Name, email, phone..."
                            class="w-full search-input"
                            @keyup.enter="applyFilters"
                        />
                    </span>
                </div>

                <div class="filter-field">
                    <label for="status-filter" class="filter-label">Parcel Status</label>
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

                <div class="filter-field">
                    <label for="payment-filter" class="filter-label">Payment Status</label>
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

                <div class="filter-buttons">
                    <Button
                        label="Apply"
                        icon="pi pi-filter"
                        class="p-button-secondary flat-btn w-full"
                        @click="applyFilters"
                    />
                    <Button
                        label="Clear"
                        icon="pi pi-filter-slash"
                        severity="secondary"
                        outlined
                        class="flat-btn w-full"
                        @click="clearFilters"
                    />
                </div>
            </div>
        </article>

        <!-- Table Listing Card -->
        <article class="page-card admin-table-card">
            <DataTable
                :value="props.parcels.data"
                responsive-layout="scroll"
                class="parcels-table"
            >
                <Column header="Patient" style="min-width: 12rem">
                    <template #body="{ data }">
                        <span class="patient-name font-bold">{{ data.patient_name }}</span>
                    </template>
                </Column>

                <Column header="Date" style="min-width: 8rem">
                    <template #body="{ data }">
                        <span class="parcel-date">{{ data.parcel_date }}</span>
                    </template>
                </Column>

                <Column header="Status" style="min-width: 9rem">
                    <template #body="{ data }">
                        <Tag
                            :value="getStatusLabel(data.parcel_status)"
                            :severity="getStatusSeverity(data.parcel_status)"
                            rounded
                        />
                    </template>
                </Column>

                <Column header="Medicines" style="min-width: 12rem">
                    <template #body="{ data }">
                        <div class="medicines-cell">
                            <template v-if="data.medicines && data.medicines.length">
                                <div class="medicines-tag-list">
                                    <Tag
                                        v-for="med in data.medicines"
                                        :key="med"
                                        :value="med"
                                        severity="secondary"
                                        class="med-chip"
                                    />
                                </div>
                            </template>
                            <span v-else class="text-secondary italic text-xs">None</span>
                        </div>
                    </template>
                </Column>

                <Column header="Amount" style="min-width: 8rem">
                    <template #body="{ data }">
                        <span class="parcel-amount font-semibold">{{ formatCurrency(data.amount) }}</span>
                    </template>
                </Column>

                <Column header="Payment" style="min-width: 8rem">
                    <template #body="{ data }">
                        <Tag
                            :value="getPaymentStatusLabel(data.payment_status)"
                            :severity="getPaymentSeverity(data.payment_status)"
                            rounded
                        />
                    </template>
                </Column>

                <Column header="Address" style="min-width: 12rem">
                    <template #body="{ data }">
                        <span class="address-cell text-xs">{{ data.address || 'N/A' }}</span>
                    </template>
                </Column>

                <Column header="Actions" style="width: 10rem" class="text-right">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <Button
                                icon="pi pi-history"
                                severity="info"
                                outlined
                                rounded
                                size="small"
                                class="action-btn"
                                @click="viewPatientHistory(data.patient_id)"
                            />
                            <Button
                                icon="pi pi-pencil"
                                severity="secondary"
                                outlined
                                rounded
                                size="small"
                                class="action-btn"
                                @click="openEditParcel(data)"
                            />
                            <Button
                                icon="pi pi-trash"
                                severity="danger"
                                outlined
                                rounded
                                size="small"
                                class="action-btn"
                                @click="deleteParcel(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <!-- Empty State -->
            <div v-if="props.parcels.data.length === 0" class="empty-state">
                <i class="pi pi-send text-4xl opacity-50 mb-2"></i>
                <h3>No courier parcels found</h3>
                <p>No parcels records match the selected filters or search queries.</p>
            </div>

            <!-- Pagination Grid -->
            <div
                v-if="props.parcels.links && props.parcels.links.length > 3"
                class="admin-pagination-container"
            >
                <div class="admin-pagination">
                    <button
                        v-for="link in props.parcels.links"
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

        <!-- CRUD Dialog Form -->
        <Dialog
            v-model:visible="showDialog"
            :header="isEditMode ? 'Edit Courier Parcel' : 'Add Courier Parcel'"
            :modal="true"
            class="courier-dialog"
            :style="{ width: '550px' }"
            fluid
        >
            <form @submit.prevent="submitForm" class="dialog-form">
                <div class="dialog-body">
                    <!-- Patient Autocomplete Lookup -->
                    <div class="field">
                        <label for="patient-search" class="form-label font-semibold">Select Patient <span class="required">*</span></label>
                        <AutoComplete
                            id="patient-search"
                            v-model="selectedPatient"
                            :suggestions="patientSuggestions"
                            option-label="label"
                            placeholder="Search by name, email or phone..."
                            fluid
                            @complete="searchPatients"
                            :disabled="isEditMode"
                        />
                        <InputError :message="form.errors.patient_id" />
                    </div>

                    <!-- Selected Patient Profile Details -->
                    <div v-if="selectedPatient && typeof selectedPatient === 'object' && selectedPatient.id" class="patient-detail-box">
                        <div class="p-detail-header mb-1">
                            <div class="p-detail-title flex items-center gap-sm font-bold">
                                <i class="pi pi-user-check"></i>
                                <span>Selected Patient Details</span>
                            </div>
                            <Button
                                type="button"
                                label="View History"
                                icon="pi pi-history"
                                class="p-button-secondary p-button-sm flat-btn text-xxs px-2 py-1 w-auto"
                                style="font-size: 0.7rem; padding: 0.15rem 0.4rem; height: auto;"
                                @click="viewPatientHistory(selectedPatient.id)"
                            />
                        </div>
                        <div class="p-detail-grid text-xs">
                            <div><strong>Name:</strong> {{ selectedPatient.name }}</div>
                            <div><strong>Patient ID:</strong> #{{ selectedPatient.id }}</div>
                            <div v-if="selectedPatient.phone"><strong>Phone:</strong> {{ selectedPatient.phone }}</div>
                            <div v-if="selectedPatient.email"><strong>Email:</strong> {{ selectedPatient.email }}</div>
                        </div>
                    </div>

                    <!-- Row: Status & Date -->
                    <div class="form-row">
                        <div class="field col-6">
                            <label for="parcel-status" class="form-label font-semibold">Status <span class="required">*</span></label>
                            <Select
                                id="parcel-status"
                                v-model="form.parcel_status"
                                :options="statusOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Select Status"
                                fluid
                            />
                            <InputError :message="form.errors.parcel_status" />
                        </div>

                        <div class="field col-6">
                            <label for="parcel-date" class="form-label font-semibold">Date <span class="required">*</span></label>
                            <DatePicker
                                id="parcel-date"
                                v-model="form.parcel_date"
                                dateFormat="yy-mm-dd"
                                placeholder="Select Date"
                                fluid
                            />
                            <InputError :message="form.errors.parcel_date" />
                        </div>
                    </div>

                    <!-- Row: Amount & Payment Status -->
                    <div class="form-row">
                        <div class="field col-6">
                            <label for="parcel-amount" class="form-label font-semibold">Amount <span class="required">*</span></label>
                            <InputNumber
                                id="parcel-amount"
                                v-model="form.amount"
                                mode="decimal"
                                :minFractionDigits="2"
                                :maxFractionDigits="2"
                                :min="0"
                                placeholder="0.00"
                                fluid
                            />
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div class="field col-6">
                            <label for="payment-status" class="form-label font-semibold">Payment Status <span class="required">*</span></label>
                            <Select
                                id="payment-status"
                                v-model="form.payment_status"
                                :options="paymentStatusOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Select Payment Status"
                                fluid
                            />
                            <InputError :message="form.errors.payment_status" />
                        </div>
                    </div>

                    <!-- Multi-Select Medicines -->
                    <div class="field">
                        <label for="parcel-medicines" class="form-label font-semibold">Medicines</label>
                        <MultiSelect
                            id="parcel-medicines"
                            v-model="form.medicines"
                            :options="props.medicinesInventory"
                            placeholder="Add medicines..."
                            display="chip"
                            fluid
                        />
                        <InputError :message="form.errors.medicines" />
                        <span class="text-xxs text-secondary">Prepare or update the medicines assigned to this parcel delivery.</span>
                    </div>

                    <!-- Address Textarea -->
                    <div class="field">
                        <label for="parcel-address" class="form-label font-semibold">Delivery Address</label>
                        <Textarea
                            id="parcel-address"
                            v-model="form.address"
                            rows="3"
                            placeholder="Defaults to patient's address if available..."
                            fluid
                        />
                        <InputError :message="form.errors.address" />
                    </div>

                    <!-- Delivery Completion Section (Only visible on status = 'delivered') -->
                    <div v-if="form.parcel_status === 'delivered'" class="delivery-completion-section">
                        <div class="section-divider-title font-bold mb-3">Delivery Completion</div>
                        <div class="form-row">
                            <div class="field col-6">
                                <label for="delivered-date" class="form-label font-semibold">Delivered Date</label>
                                <DatePicker
                                    id="delivered-date"
                                    v-model="form.delivered_date"
                                    dateFormat="yy-mm-dd"
                                    placeholder="yyyy-mm-dd"
                                    fluid
                                />
                                <InputError :message="form.errors.delivered_date" />
                            </div>

                            <div class="field col-6">
                                <label for="instructions-given" class="form-label font-semibold">Instructions Given</label>
                                <Select
                                    id="instructions-given"
                                    v-model="form.instructions_given"
                                    :options="instructionsOptions"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Select"
                                    fluid
                                />
                                <InputError :message="form.errors.instructions_given" />
                            </div>
                        </div>

                        <div class="field mt-3">
                            <label for="instruction-note" class="form-label font-semibold">Instruction Note</label>
                            <Textarea
                                id="instruction-note"
                                v-model="form.instruction_note"
                                rows="3"
                                placeholder="How to take medicine instruction"
                                fluid
                            />
                            <InputError :message="form.errors.instruction_note" />
                        </div>
                    </div>

                    <!-- Internal Notes -->
                    <div class="field">
                        <label for="parcel-notes" class="form-label font-semibold">Internal Notes</label>
                        <Textarea
                            id="parcel-notes"
                            v-model="form.notes"
                            rows="2"
                            placeholder="Internal shipping comments, tracking codes, or special instructions..."
                            fluid
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>

                <div class="dialog-footer flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="showDialog = false"
                    />
                    <Button
                        type="submit"
                        :label="isEditMode ? 'Update' : 'Save'"
                        :loading="form.processing"
                        severity="primary"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Patient History Timeline Sidebar -->
        <PatientHistorySidebar
            v-model:visible="showHistorySidebar"
            :patient-id="historyPatientId"
        />
    </div>
</template>

<style scoped>
.courier-parcels-page {
    gap: 1.5rem;
}

.courier-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
}

@media (max-width: 768px) {
    .courier-hero {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}

.courier-hero__title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.filter-card {
    padding: 1.25rem;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
    align-items: flex-end;
    gap: 1.25rem;
}

@media (max-width: 1024px) {
    .filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--text-color);
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

.filter-buttons {
    display: flex;
    gap: 0.5rem;
}

/* Table overrides and helper styles */
.patient-cell {
    display: flex;
    flex-direction: column;
}

.patient-id {
    font-size: 0.775rem;
    opacity: 0.85;
}

.medicines-tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    max-width: 18rem;
}

.med-chip {
    font-size: 0.675rem;
    padding: 0.15rem 0.4rem;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 1.5rem;
    text-align: center;
}

/* Dialog and form styles */
.dialog-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.dialog-body {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.form-label {
    display: block;
    font-size: 0.825rem;
    color: var(--text-color);
    margin-bottom: 0.35rem;
}

.required {
    color: #ef4444;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.patient-detail-box {
    padding: 0.85rem;
    background: var(--surface-hover);
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    margin-top: -0.5rem;
}

.p-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.w-auto {
    width: auto !important;
}

.p-detail-title {
    color: var(--primary-color);
    font-size: 0.825rem;
}

.justify-between {
    justify-content: space-between;
}

.p-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.35rem 0.75rem;
}

.dialog-footer {
    border-top: 1px solid var(--surface-border);
    margin-top: 1.5rem;
    padding-top: 1.25rem;
}

.text-xxs {
    font-size: 0.65rem;
}

.text-secondary {
    color: var(--text-color-secondary);
}

.flex {
    display: flex;
}

.items-center {
    align-items: center;
}

.gap-sm {
    gap: 0.375rem;
}

.gap-2 {
    gap: 0.5rem;
}

.justify-end {
    justify-content: flex-end;
}

.delivery-completion-section {
    padding: 1.15rem;
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    background: var(--surface-hover);
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

.section-divider-title {
    font-size: 0.95rem;
    color: var(--text-color);
    border-bottom: 1px solid var(--surface-border);
    padding-bottom: 0.5rem;
}
</style>
