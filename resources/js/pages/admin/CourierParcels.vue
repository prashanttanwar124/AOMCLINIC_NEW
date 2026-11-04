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
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PatientController from '@/actions/App/Http/Controllers/Admin/PatientController';
import InputError from '@/components/InputError.vue';
import PatientHistorySidebar from '@/components/PatientHistorySidebar.vue';
import http from '@/lib/http';
import { dashboard } from '@/routes';
import { courierParcels } from '@/routes/admin';

type PatientDetails = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    delivery_address?: string | null;
    parent_name?: string | null;
    children?: Array<{
        id: number;
        name: string;
        phone: string | null;
        address: string | null;
        parent_id: number;
        parent_name: string;
    }>;
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
    complaints: string | null;
    days_prescription: number | null;
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
    from?: number | null;
    to?: number | null;
};

type ParcelsSummary = {
    total: number;
    delivered: number;
    in_progress: number;
    unpaid_count: number;
    unpaid_amount: number;
    paid_amount: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Courier Parcels', href: courierParcels() },
        ],
    },
});

const props = defineProps<{
    parcels: PaginatedParcels;
    summary?: ParcelsSummary;
    filters: {
        search: string | null;
        status: string | null;
        payment_status: string | null;
    };
    medicinesInventory: string[];
}>();

const canTeleportActions = ref(false);
onMounted(() => {
    canTeleportActions.value = !!document.getElementById('admin-header-actions');
});

// Filter values
const searchFilter = ref(props.filters.search ?? '');
const statusFilter = ref<string | null>(props.filters.status || null);
const paymentStatusFilter = ref<string | null>(props.filters.payment_status || null);

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
    if (!patientId) {
        return;
    }
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
    complaints: '',
    days_prescription: null as number | null,
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

const hasActiveFilters = computed(() => {
    return Boolean(
        searchFilter.value.trim() ||
            statusFilter.value ||
            paymentStatusFilter.value,
    );
});

const computedSummary = computed<ParcelsSummary>(() => {
    if (props.summary) {
        return props.summary;
    }
    const rows = props.parcels.data;
    return {
        total: props.parcels.total,
        delivered: rows.filter((r) => r.parcel_status === 'delivered').length,
        in_progress: rows.filter((r) =>
            ['order_received', 'packed', 'dispatched', 'in_transit'].includes(
                r.parcel_status,
            ),
        ).length,
        unpaid_count: rows.filter((r) => r.payment_status === 'unpaid').length,
        unpaid_amount: rows
            .filter((r) => r.payment_status === 'unpaid')
            .reduce((sum, r) => sum + Number(r.amount || 0), 0),
        paid_amount: rows
            .filter((r) => r.payment_status === 'paid')
            .reduce((sum, r) => sum + Number(r.amount || 0), 0),
    };
});

// Local Date Parsing (avoids UTC midnight off-by-one bug)
function parseLocalDate(dateStr: string | null | undefined): Date | null {
    if (!dateStr) {
        return null;
    }
    const clean = dateStr.split('T')[0];
    const parts = clean.split('-').map(Number);
    if (parts.length === 3 && !parts.some(Number.isNaN)) {
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }
    const fallback = new Date(dateStr);
    return Number.isNaN(fallback.getTime()) ? null : fallback;
}

function toDateString(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatDisplayDate(dateStr: string | null | undefined): string {
    const parsed = parseLocalDate(dateStr);
    if (!parsed) {
        return dateStr || '—';
    }
    return new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
}

function getInitials(name: string | null | undefined): string {
    if (!name) {
        return 'PT';
    }
    return (
        name
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || 'PT'
    );
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

// Prefill address automatically whenever a patient is selected or changed
watch(selectedPatient, (newVal, oldVal) => {
    if (newVal && typeof newVal === 'object' && newVal.id) {
        const isChangedPatient = !oldVal || oldVal.id !== newVal.id;
        form.patient_id = newVal.id;
        const autoAddress = newVal.delivery_address || newVal.address || '';
        if (!isEditMode.value || !form.address || isChangedPatient) {
            if (autoAddress || !isEditMode.value) {
                form.address = autoAddress;
            }
        }
    } else {
        form.patient_id = null;
    }
});

function selectDependentForParcel(child: {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
    parent_name: string;
}): void {
    selectedPatient.value = {
        id: child.id,
        name: child.name,
        email: selectedPatient.value?.email ?? null,
        phone: child.phone ?? selectedPatient.value?.phone ?? null,
        address: child.address ?? selectedPatient.value?.address ?? null,
        delivery_address: child.address ?? selectedPatient.value?.delivery_address ?? selectedPatient.value?.address ?? null,
        parent_name: child.parent_name,
        children: [],
        label: `${child.name} (#${child.id}) [Dependent of ${child.parent_name}]`,
    };
}

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
    form.address = '';
    form.complaints = '';
    form.days_prescription = null;
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

    form.patient_id = parcel.patient_id;
    form.parcel_status = parcel.parcel_status;
    form.parcel_date = parseLocalDate(parcel.parcel_date) ?? new Date();
    form.amount =
        typeof parcel.amount === 'string'
            ? parseFloat(parcel.amount)
            : parcel.amount;
    form.payment_status = parcel.payment_status;
    form.medicines = [...(parcel.medicines || [])];
    form.address = parcel.address || '';
    form.complaints = parcel.complaints || '';
    form.days_prescription = parcel.days_prescription ?? null;
    form.notes = parcel.notes || '';
    form.delivered_date = parseLocalDate(parcel.delivered_date);
    form.instructions_given = parcel.instructions_given || false;
    form.instruction_note = parcel.instruction_note || '';

    selectedPatient.value = {
        id: parcel.patient_id,
        name: parcel.patient_name,
        email: parcel.patient_email,
        phone: parcel.patient_phone,
        address: parcel.address,
        delivery_address: parcel.address,
        label: `${parcel.patient_name} (#${parcel.patient_id})`,
    };

    showDialog.value = true;
}

// Debounced search & filter application
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters(): void {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = null;
    }

    router.get(
        courierParcels().url,
        {
            search: searchFilter.value.trim() || undefined,
            status: statusFilter.value || undefined,
            payment_status: paymentStatusFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch(searchFilter, (val) => {
    if (val.trim() === (props.filters.search ?? '').trim()) {
        return;
    }
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    searchDebounceTimer = setTimeout(() => {
        applyFilters();
    }, 320);
});

onBeforeUnmount(() => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});

function clearFilters(): void {
    searchFilter.value = '';
    statusFilter.value = null;
    paymentStatusFilter.value = null;
    applyFilters();
}

function quickFilterBy(
    type: 'all' | 'order_received' | 'dispatched' | 'in_transit' | 'delivered' | 'unpaid',
): void {
    if (type === 'all') {
        statusFilter.value = null;
        paymentStatusFilter.value = null;
    } else if (type === 'unpaid') {
        statusFilter.value = null;
        paymentStatusFilter.value =
            paymentStatusFilter.value === 'unpaid' ? null : 'unpaid';
    } else {
        paymentStatusFilter.value = null;
        statusFilter.value = statusFilter.value === type ? null : type;
    }
    applyFilters();
}

const activeQuickSegment = computed(() => {
    if (paymentStatusFilter.value === 'unpaid' && !statusFilter.value) {
        return 'unpaid';
    }
    if (statusFilter.value && !paymentStatusFilter.value) {
        return statusFilter.value;
    }
    if (!statusFilter.value && !paymentStatusFilter.value) {
        return 'all';
    }
    return '';
});

// Navigate pages
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
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

// Submit Form
function submitForm(): void {
    form.transform((data) => ({
        ...data,
        parcel_date:
            data.parcel_date instanceof Date
                ? toDateString(data.parcel_date)
                : data.parcel_date,
        delivered_date:
            data.delivered_date instanceof Date
                ? toDateString(data.delivered_date)
                : data.delivered_date,
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

const confirm = useConfirm();

// Delete parcel record
function deleteParcel(parcel: CourierParcelItem): void {
    confirm.require({
        header: 'Delete Courier Parcel',
        message: `Are you sure you want to delete the courier parcel for "${parcel.patient_name}"?`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: 'Delete Parcel',
            severity: 'danger',
        },
        accept: () => {
            router.delete(courierParcels.destroy(parcel.id).url, {
                preserveScroll: true,
            });
        },
    });
}

// Formatters for status labels & severity colors
function getStatusLabel(status: string): string {
    const found = statusOptions.find((o) => o.value === status);
    return found ? found.label : status;
}

function getStatusSeverity(
    status: string,
): 'success' | 'info' | 'warn' | 'danger' | 'secondary' {
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
    const found = paymentStatusOptions.find((o) => o.value === status);
    return found ? found.label : status;
}

function getPaymentSeverity(
    status: string,
): 'success' | 'danger' | 'secondary' {
    return status === 'paid' ? 'success' : 'danger';
}

function formatCurrency(amount: string | number): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(Number.isNaN(num) ? 0 : num);
}
</script>

<template>
    <Head title="Courier Parcels Management" />

    <div class="page-grid courier-workspace">
        <!-- Teleport Primary Action into Layout Header -->
        <Teleport v-if="canTeleportActions" to="#admin-header-actions">
            <div class="header-action-group">
                <Button
                    label="Add Courier Parcel"
                    icon="pi pi-plus"
                    severity="primary"
                    size="small"
                    class="btn-auto"
                    @click="openNewParcel"
                />
            </div>
        </Teleport>

        <!-- Interactive KPI Summary Strip -->
        <section class="summary-grid" aria-label="Courier parcels summary">
            <button
                type="button"
                class="summary-card"
                :class="{ 'is-active': activeQuickSegment === 'all' }"
                @click="quickFilterBy('all')"
            >
                <div class="summary-card__icon summary-card__icon--blue">
                    <i class="pi pi-box"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Total Parcels</span>
                    <strong class="summary-card__value">{{
                        computedSummary.total
                    }}</strong>
                    <span class="summary-card__sub"
                        >{{ computedSummary.in_progress }} active in
                        pipeline</span
                    >
                </div>
            </button>

            <button
                type="button"
                class="summary-card"
                :class="{ 'is-active': activeQuickSegment === 'in_transit' }"
                @click="quickFilterBy('in_transit')"
            >
                <div class="summary-card__icon summary-card__icon--amber">
                    <i class="pi pi-truck"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Active / In Transit</span>
                    <strong class="summary-card__value">{{
                        computedSummary.in_progress
                    }}</strong>
                    <span class="summary-card__sub"
                        >Click to filter in-transit</span
                    >
                </div>
            </button>

            <button
                type="button"
                class="summary-card"
                :class="{ 'is-active': activeQuickSegment === 'delivered' }"
                @click="quickFilterBy('delivered')"
            >
                <div class="summary-card__icon summary-card__icon--green">
                    <i class="pi pi-check-circle"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Delivered Parcels</span>
                    <strong class="summary-card__value">{{
                        computedSummary.delivered
                    }}</strong>
                    <span class="summary-card__sub"
                        >Collected:
                        {{ formatCurrency(computedSummary.paid_amount) }}</span
                    >
                </div>
            </button>

            <button
                type="button"
                class="summary-card"
                :class="{ 'is-active': activeQuickSegment === 'unpaid' }"
                @click="quickFilterBy('unpaid')"
            >
                <div class="summary-card__icon summary-card__icon--rose">
                    <i class="pi pi-clock"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Pending Unpaid</span>
                    <strong class="summary-card__value">{{
                        formatCurrency(computedSummary.unpaid_amount)
                    }}</strong>
                    <span class="summary-card__sub"
                        >{{ computedSummary.unpaid_count }} unpaid
                        parcel(s)</span
                    >
                </div>
            </button>
        </section>

        <!-- Unified Workspace Table Card -->
        <section class="page-card admin-table-card workspace-card">
            <!-- Top Bar with Quick Status Pills & CTA -->
            <div class="workspace-header">
                <div class="status-segments" role="group" aria-label="Quick status filter">
                    <button
                        type="button"
                        class="segment-pill"
                        :class="{ 'is-selected': activeQuickSegment === 'all' }"
                        @click="quickFilterBy('all')"
                    >
                        All ({{ computedSummary.total }})
                    </button>
                    <button
                        type="button"
                        class="segment-pill"
                        :class="{
                            'is-selected':
                                activeQuickSegment === 'order_received',
                        }"
                        @click="quickFilterBy('order_received')"
                    >
                        Order Received
                    </button>
                    <button
                        type="button"
                        class="segment-pill"
                        :class="{
                            'is-selected': activeQuickSegment === 'dispatched',
                        }"
                        @click="quickFilterBy('dispatched')"
                    >
                        Dispatched
                    </button>
                    <button
                        type="button"
                        class="segment-pill"
                        :class="{
                            'is-selected': activeQuickSegment === 'in_transit',
                        }"
                        @click="quickFilterBy('in_transit')"
                    >
                        In Transit
                    </button>
                    <button
                        type="button"
                        class="segment-pill"
                        :class="{
                            'is-selected': activeQuickSegment === 'delivered',
                        }"
                        @click="quickFilterBy('delivered')"
                    >
                        Delivered ({{ computedSummary.delivered }})
                    </button>
                    <button
                        type="button"
                        class="segment-pill segment-pill--warn"
                        :class="{
                            'is-selected': activeQuickSegment === 'unpaid',
                        }"
                        @click="quickFilterBy('unpaid')"
                    >
                        Unpaid ({{ computedSummary.unpaid_count }})
                    </button>
                </div>

                <div class="workspace-header__right">
                    <Button
                        label="Add Courier Parcel"
                        icon="pi pi-plus"
                        severity="primary"
                        size="small"
                        class="btn-auto"
                        @click="openNewParcel"
                    />
                </div>
            </div>

            <!-- Integrated Search & Dropdown Filters -->
            <div class="filter-bar">
                <div class="filter-bar__search">
                    <i class="pi pi-search filter-bar__search-icon"></i>
                    <InputText
                        id="search-input"
                        v-model="searchFilter"
                        placeholder="Search by patient name, phone, or email..."
                        class="filter-bar__search-input"
                        @keyup.enter="applyFilters"
                    />
                    <button
                        v-if="searchFilter"
                        type="button"
                        class="filter-bar__clear-input"
                        title="Clear search"
                        @click="
                            searchFilter = '';
                            applyFilters();
                        "
                    >
                        <i class="pi pi-times"></i>
                    </button>
                </div>

                <div class="filter-bar__controls">
                    <Select
                        id="status-filter"
                        v-model="statusFilter"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="All Parcel Statuses"
                        show-clear
                        class="filter-select"
                        @change="applyFilters"
                    />

                    <Select
                        id="payment-filter"
                        v-model="paymentStatusFilter"
                        :options="paymentStatusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="All Payment Statuses"
                        show-clear
                        class="filter-select filter-select--sm"
                        @change="applyFilters"
                    />

                    <Button
                        v-if="hasActiveFilters"
                        label="Reset"
                        icon="pi pi-filter-slash"
                        severity="secondary"
                        outlined
                        size="small"
                        class="btn-auto"
                        @click="clearFilters"
                    />
                </div>
            </div>

            <!-- Parcels DataTable -->
            <DataTable
                v-if="props.parcels.data.length > 0"
                :value="props.parcels.data"
                responsive-layout="scroll"
                row-hover
                striped-rows
                data-key="id"
                class="parcels-table"
            >
                <Column header="Parcel / Date" style="min-width: 10.5rem">
                    <template #body="{ data }">
                        <div class="parcel-id-cell">
                            <span class="admin-token-chip">#CP-{{ String(data.id).padStart(4, '0') }}</span>
                            <span class="parcel-date-meta">
                                <i class="pi pi-calendar"></i>
                                {{ formatDisplayDate(data.parcel_date) }}
                            </span>
                        </div>
                    </template>
                </Column>

                <Column header="Patient" style="min-width: 14rem">
                    <template #body="{ data }">
                        <div class="admin-person-cell">
                            <Avatar
                                :label="getInitials(data.patient_name)"
                                shape="circle"
                                class="admin-person-cell__avatar"
                            />
                            <div class="patient-cell__info">
                                <button
                                    type="button"
                                    class="patient-cell__name-btn"
                                    title="Open patient clinical history"
                                    @click="viewPatientHistory(data.patient_id)"
                                >
                                    {{ data.patient_name }}
                                </button>
                                <span class="patient-cell__sub">
                                    ID #{{ data.patient_id }}
                                    <template v-if="data.patient_phone">
                                        · {{ data.patient_phone }}
                                    </template>
                                </span>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column header="Dispatch Status" style="min-width: 10.5rem">
                    <template #body="{ data }">
                        <div class="status-cell">
                            <Tag
                                :value="getStatusLabel(data.parcel_status)"
                                :severity="getStatusSeverity(data.parcel_status)"
                                rounded
                            />
                            <span
                                v-if="data.delivered_date"
                                class="delivered-sub"
                            >
                                <i class="pi pi-check"></i>
                                {{ formatDisplayDate(data.delivered_date) }}
                            </span>
                        </div>
                    </template>
                </Column>

                <Column header="Medicines & Prescription" style="min-width: 15.5rem">
                    <template #body="{ data }">
                        <div class="medicines-cell">
                            <template
                                v-if="data.medicines && data.medicines.length"
                            >
                                <div class="medicines-tag-list">
                                    <span
                                        v-for="med in data.medicines.slice(0, 3)"
                                        :key="med"
                                        class="med-chip"
                                    >
                                        {{ med }}
                                    </span>
                                    <span
                                        v-if="data.medicines.length > 3"
                                        v-tooltip.top="data.medicines.slice(3).join(', ')"
                                        class="med-chip med-chip--more"
                                    >
                                        +{{ data.medicines.length - 3 }} more
                                    </span>
                                </div>
                            </template>
                            <span v-else class="text-muted-italic"
                                >No items listed</span
                            >

                            <div
                                v-if="data.days_prescription || data.complaints"
                                class="parcel-clinical-meta"
                            >
                                <span
                                    v-if="data.days_prescription"
                                    class="rx-days-pill"
                                >
                                    <i class="pi pi-calendar-clock"></i>
                                    {{ data.days_prescription }} {{ data.days_prescription === 1 ? 'Day' : 'Days' }} Rx
                                </span>
                                <span
                                    v-if="data.complaints"
                                    class="parcel-complaint-preview"
                                    :title="data.complaints"
                                >
                                    <strong>Complaint:</strong> {{ data.complaints }}
                                </span>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column header="Delivery Address" style="min-width: 13rem">
                    <template #body="{ data }">
                        <div v-if="data.address" class="address-cell">
                            <p
                                class="address-truncate"
                                :title="data.address"
                            >
                                {{ data.address }}
                            </p>
                            <span
                                v-if="data.notes"
                                class="notes-preview"
                                :title="data.notes"
                            >
                                <i class="pi pi-comment"></i> {{ data.notes }}
                            </span>
                        </div>
                        <span v-else class="text-muted-italic">Not specified</span>
                    </template>
                </Column>

                <Column header="Fee & Payment" style="min-width: 9.5rem">
                    <template #body="{ data }">
                        <div class="billing-cell">
                            <strong class="billing-cell__amount">{{
                                formatCurrency(data.amount)
                            }}</strong>
                            <Tag
                                :value="
                                    getPaymentStatusLabel(data.payment_status)
                                "
                                :severity="
                                    getPaymentSeverity(data.payment_status)
                                "
                                rounded
                                class="tag-xs"
                            />
                        </div>
                    </template>
                </Column>

                <Column
                    header="Actions"
                    header-class="text-right"
                    class="text-right"
                    style="width: 9rem; min-width: 9rem"
                >
                    <template #body="{ data }">
                        <div class="row-actions">
                            <Button
                                v-tooltip.top="'Patient Clinical History'"
                                icon="pi pi-history"
                                severity="info"
                                text
                                rounded
                                size="small"
                                class="action-icon-btn"
                                @click="viewPatientHistory(data.patient_id)"
                            />
                            <Button
                                v-tooltip.top="'Edit Parcel'"
                                icon="pi pi-pencil"
                                severity="secondary"
                                text
                                rounded
                                size="small"
                                class="action-icon-btn"
                                @click="openEditParcel(data)"
                            />
                            <Button
                                v-tooltip.top="'Delete Parcel'"
                                icon="pi pi-trash"
                                severity="danger"
                                text
                                rounded
                                size="small"
                                class="action-icon-btn"
                                @click="deleteParcel(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <!-- Empty State -->
            <div
                v-else
                class="admin-empty-state admin-empty-state--soft"
            >
                <div class="empty-state__icon-ring">
                    <i class="pi pi-truck"></i>
                </div>
                <h3>No courier parcels found</h3>
                <p v-if="hasActiveFilters">
                    No parcel records match your active search or filter
                    criteria.
                </p>
                <p v-else>
                    Start logging medicine dispatches and tracking courier
                    deliveries for your patients.
                </p>
                <div class="empty-state__actions">
                    <Button
                        v-if="hasActiveFilters"
                        label="Reset Filters"
                        icon="pi pi-filter-slash"
                        severity="secondary"
                        outlined
                        size="small"
                        class="btn-auto"
                        @click="clearFilters"
                    />
                    <Button
                        label="Add First Parcel"
                        icon="pi pi-plus"
                        severity="primary"
                        size="small"
                        class="btn-auto"
                        @click="openNewParcel"
                    />
                </div>
            </div>

            <!-- Split Pagination Footer -->
            <div
                v-if="props.parcels.data.length > 0"
                class="admin-pagination-container"
            >
                <span class="pagination-summary">
                    Showing
                    <strong>{{
                        props.parcels.from ?? 1
                    }}</strong>–<strong>{{
                        props.parcels.to ?? props.parcels.data.length
                    }}</strong>
                    of <strong>{{ props.parcels.total }}</strong> parcels
                </span>

                <div
                    v-if="
                        props.parcels.links && props.parcels.links.length > 3
                    "
                    class="admin-pagination"
                >
                    <button
                        v-for="link in props.parcels.links"
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
        </section>

        <!-- CRUD Dialog Form -->
        <Dialog
            v-model:visible="showDialog"
            :header="isEditMode ? 'Edit Courier Parcel' : 'Add Courier Parcel'"
            :modal="true"
            class="courier-dialog"
            :style="{ width: '38rem', maxWidth: '95vw' }"
            :breakpoints="{ '640px': '96vw' }"
        >
            <form class="dialog-form" @submit.prevent="submitForm">
                <div class="dialog-body">
                    <!-- Patient Autocomplete Lookup -->
                    <div class="field">
                        <label for="patient-search" class="form-label">
                            Select Patient <span class="required">*</span>
                        </label>
                        <AutoComplete
                            v-if="!selectedPatient || typeof selectedPatient !== 'object' || !selectedPatient.id"
                            id="patient-search"
                            v-model="selectedPatient"
                            :suggestions="patientSuggestions"
                            option-label="label"
                            placeholder="Search by patient name, email, or phone..."
                            fluid
                            :disabled="isEditMode"
                            @complete="searchPatients"
                        />

                        <!-- Selected Patient Banner -->
                        <div
                            v-else
                            class="selected-patient-banner"
                        >
                            <div class="selected-patient-banner__left">
                                <Avatar
                                    :label="getInitials(selectedPatient.name)"
                                    shape="circle"
                                    class="selected-patient-banner__avatar"
                                />
                                <div class="selected-patient-banner__info">
                                    <div class="selected-patient-banner__title">
                                        <strong>{{ selectedPatient.name }}</strong>
                                        <span class="patient-id-badge"
                                            >#{{ selectedPatient.id }}</span
                                        >
                                        <Tag
                                            v-if="selectedPatient.parent_name"
                                            :value="`Dependent of ${selectedPatient.parent_name}`"
                                            severity="info"
                                        />
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
                                    </div>
                                    <div
                                        v-if="!isEditMode && selectedPatient.children && selectedPatient.children.length > 0"
                                        class="parcel-dependents-row"
                                    >
                                        <span class="parcel-dependents-label">Switch to dependent:</span>
                                        <button
                                            v-for="child in selectedPatient.children"
                                            :key="child.id"
                                            type="button"
                                            class="parcel-dependent-chip"
                                            @click="selectDependentForParcel(child)"
                                        >
                                            <i class="pi pi-user"></i>
                                            {{ child.name }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="selected-patient-banner__actions">
                                <Button
                                    type="button"
                                    label="Clinical History"
                                    icon="pi pi-history"
                                    severity="secondary"
                                    outlined
                                    size="small"
                                    class="btn-auto"
                                    @click="viewPatientHistory(selectedPatient.id)"
                                />
                                <Button
                                    v-if="!isEditMode"
                                    type="button"
                                    label="Change"
                                    icon="pi pi-refresh"
                                    severity="secondary"
                                    text
                                    size="small"
                                    class="btn-auto"
                                    @click="selectedPatient = null"
                                />
                            </div>
                        </div>
                        <InputError :message="form.errors.patient_id" />
                    </div>

                    <!-- Row: Status & Date -->
                    <div class="form-row">
                        <div class="field">
                            <label for="parcel-status" class="form-label">
                                Dispatch Status <span class="required">*</span>
                            </label>
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

                        <div class="field">
                            <label for="parcel-date" class="form-label">
                                Parcel Date <span class="required">*</span>
                            </label>
                            <DatePicker
                                id="parcel-date"
                                v-model="form.parcel_date"
                                date-format="yy-mm-dd"
                                placeholder="Select Date"
                                show-icon
                                fluid
                            />
                            <InputError :message="form.errors.parcel_date" />
                        </div>
                    </div>

                    <!-- Row: Amount & Payment Status -->
                    <div class="form-row">
                        <div class="field">
                            <label for="parcel-amount" class="form-label">
                                Amount (₹) <span class="required">*</span>
                            </label>
                            <InputNumber
                                id="parcel-amount"
                                v-model="form.amount"
                                mode="decimal"
                                :min-fraction-digits="2"
                                :max-fraction-digits="2"
                                :min="0"
                                placeholder="0.00"
                                fluid
                            />
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div class="field">
                            <label for="payment-status" class="form-label">
                                Payment Status <span class="required">*</span>
                            </label>
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

                    <!-- Multi-Select Medicines & Prescription Days -->
                    <div class="form-row">
                        <div class="field">
                            <label for="parcel-medicines" class="form-label">
                                Dispatched Medicines
                            </label>
                            <MultiSelect
                                id="parcel-medicines"
                                v-model="form.medicines"
                                :options="props.medicinesInventory"
                                placeholder="Select or search medicines..."
                                filter
                                display="chip"
                                fluid
                            />
                            <InputError :message="form.errors.medicines" />
                        </div>

                        <div class="field">
                            <label for="parcel-days-prescription" class="form-label">
                                Prescription for Number of Days
                            </label>
                            <InputNumber
                                id="parcel-days-prescription"
                                v-model="form.days_prescription"
                                :min="1"
                                :max="365"
                                suffix=" days"
                                show-buttons
                                placeholder="e.g., 15 days"
                                fluid
                            />
                            <InputError :message="form.errors.days_prescription" />
                        </div>
                    </div>

                    <!-- Complaints / Symptoms Textarea -->
                    <div class="field">
                        <label for="parcel-complaints" class="form-label">
                            Patient Complaints / Symptoms
                        </label>
                        <Textarea
                            id="parcel-complaints"
                            v-model="form.complaints"
                            rows="2"
                            auto-resize
                            placeholder="Write patient complaints, symptoms, or reason for medicine parcel..."
                            fluid
                        />
                        <InputError :message="form.errors.complaints" />
                    </div>

                    <!-- Address Textarea -->
                    <div class="field">
                        <div class="address-label-row">
                            <label for="parcel-address" class="form-label">
                                Delivery Address
                                <span class="address-auto-badge">Auto-filled from patient profile</span>
                            </label>
                        </div>
                        <Textarea
                            id="parcel-address"
                            v-model="form.address"
                            rows="2"
                            placeholder="Automatically populated when a patient is selected..."
                            fluid
                        />
                        <InputError :message="form.errors.address" />
                    </div>

                    <!-- Delivery Completion Section -->
                    <div
                        v-if="form.parcel_status === 'delivered'"
                        class="delivery-completion-section"
                    >
                        <div class="section-divider-title">
                            <i class="pi pi-check-circle"></i> Delivery
                            Completion Details
                        </div>
                        <div class="form-row">
                            <div class="field">
                                <label for="delivered-date" class="form-label">
                                    Delivered Date
                                </label>
                                <DatePicker
                                    id="delivered-date"
                                    v-model="form.delivered_date"
                                    date-format="yy-mm-dd"
                                    placeholder="yyyy-mm-dd"
                                    show-icon
                                    fluid
                                />
                                <InputError
                                    :message="form.errors.delivered_date"
                                />
                            </div>

                            <div class="field">
                                <label
                                    for="instructions-given"
                                    class="form-label"
                                >
                                    Instructions Given
                                </label>
                                <Select
                                    id="instructions-given"
                                    v-model="form.instructions_given"
                                    :options="instructionsOptions"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Select"
                                    fluid
                                />
                                <InputError
                                    :message="form.errors.instructions_given"
                                />
                            </div>
                        </div>

                        <div class="field mt-2">
                            <label for="instruction-note" class="form-label">
                                Medication Instruction Note
                            </label>
                            <Textarea
                                id="instruction-note"
                                v-model="form.instruction_note"
                                rows="2"
                                placeholder="Dosage instructions communicated to the patient..."
                                fluid
                            />
                            <InputError
                                :message="form.errors.instruction_note"
                            />
                        </div>
                    </div>

                    <!-- Internal Notes -->
                    <div class="field">
                        <label for="parcel-notes" class="form-label">
                            Internal Shipping Notes / Tracking ID
                        </label>
                        <Textarea
                            id="parcel-notes"
                            v-model="form.notes"
                            rows="2"
                            placeholder="Courier name, AWB tracking number, or handling instructions..."
                            fluid
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>

                <div class="dialog-footer">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        outlined
                        class="btn-auto"
                        @click="showDialog = false"
                    />
                    <Button
                        type="submit"
                        :label="isEditMode ? 'Update Parcel' : 'Save Parcel'"
                        icon="pi pi-check"
                        :loading="form.processing"
                        severity="primary"
                        class="btn-auto"
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
.courier-workspace {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.header-action-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.btn-auto {
    width: auto !important;
    white-space: nowrap !important;
}

/* KPI Summary Cards */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

@media (max-width: 1100px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
}

.summary-card {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.15rem 1.25rem;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    text-align: left;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        transform 0.15s ease,
        box-shadow 0.15s ease;
    font-family: inherit;
}

.summary-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 50%, var(--surface-border));
    transform: translateY(-1px);
}

.summary-card.is-active {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary-color) 18%, transparent);
}

.summary-card__icon {
    width: 2.85rem;
    height: 2.85rem;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.summary-card__icon--blue {
    background: color-mix(in srgb, #3b82f6 14%, var(--surface-card));
    color: #2563eb;
}

.summary-card__icon--amber {
    background: color-mix(in srgb, #f59e0b 14%, var(--surface-card));
    color: #d97706;
}

.summary-card__icon--green {
    background: color-mix(in srgb, #10b981 14%, var(--surface-card));
    color: #059669;
}

.summary-card__icon--rose {
    background: color-mix(in srgb, #f43f5e 14%, var(--surface-card));
    color: #e11d48;
}

.summary-card__content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.summary-card__label {
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary-color);
}

.summary-card__value {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--text-color);
    line-height: 1.2;
}

.summary-card__sub {
    font-size: 0.76rem;
    font-weight: 500;
    color: var(--text-secondary-color);
}

/* Unified Workspace Header & Filter Bar */
.workspace-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--surface-border);
    background: color-mix(in srgb, var(--surface-50) 65%, var(--surface-card));
    flex-wrap: wrap;
}

.status-segments {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.segment-pill {
    padding: 0.36rem 0.75rem;
    border-radius: 999px;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-secondary-color);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    font-family: inherit;
}

.segment-pill:hover {
    color: var(--text-color);
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--surface-border));
}

.segment-pill.is-selected {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--primary-contrast-color);
}

.segment-pill--warn.is-selected {
    background: #e11d48;
    border-color: #e11d48;
    color: #fff;
}

.filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--surface-border);
    background: var(--surface-card);
    flex-wrap: wrap;
}

.filter-bar__search {
    position: relative;
    flex: 1 1 18rem;
    min-width: 15rem;
    display: flex;
    align-items: center;
}

.filter-bar__search-icon {
    position: absolute;
    left: 0.85rem;
    color: var(--text-secondary-color);
    font-size: 0.88rem;
    pointer-events: none;
    z-index: 1;
}

.filter-bar__search-input {
    width: 100%;
    padding-left: 2.35rem !important;
    padding-right: 2.2rem !important;
}

.filter-bar__clear-input {
    position: absolute;
    right: 0.6rem;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: var(--text-secondary-color);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.filter-bar__clear-input:hover {
    background: var(--surface-hover);
    color: var(--text-color);
}

.filter-bar__controls {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.filter-select {
    min-width: 12rem;
}

.filter-select--sm {
    min-width: 11rem;
}

/* Table Cells */
.parcel-id-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.28rem;
}

.parcel-date-meta {
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--text-secondary-color);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.patient-cell__info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.patient-cell__name-btn {
    border: none;
    background: none;
    padding: 0;
    margin: 0;
    text-align: left;
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-color);
    cursor: pointer;
    transition: color 0.15s ease;
}

.patient-cell__name-btn:hover {
    color: var(--primary-color);
    text-decoration: underline;
}

.patient-cell__sub {
    font-size: 0.76rem;
    color: var(--text-secondary-color);
}

.status-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
}

.delivered-sub {
    font-size: 0.72rem;
    font-weight: 600;
    color: #059669;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.medicines-tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    max-width: 18rem;
}

.med-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.18rem 0.5rem;
    border-radius: 6px;
    background: color-mix(in srgb, var(--surface-100) 80%, var(--surface-card));
    border: 1px solid var(--surface-border);
    color: var(--text-color);
    font-size: 0.72rem;
    font-weight: 600;
}

.med-chip--more {
    background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-card));
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 25%, transparent);
    cursor: help;
}

.address-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    max-width: 15rem;
}

.address-truncate {
    margin: 0;
    font-size: 0.82rem;
    color: var(--text-color);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.35;
}

.notes-preview {
    font-size: 0.73rem;
    color: var(--text-secondary-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.billing-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
}

.billing-cell__amount {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--text-color);
    font-variant-numeric: tabular-nums;
}

.tag-xs {
    font-size: 0.68rem !important;
    padding: 0.12rem 0.45rem !important;
}

.text-muted-italic {
    font-size: 0.78rem;
    font-style: italic;
    color: var(--text-secondary-color);
}

.text-right {
    text-align: right !important;
}

.row-actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.2rem;
}

.action-icon-btn {
    width: 2rem !important;
    height: 2rem !important;
    padding: 0 !important;
}

.empty-state__icon-ring {
    width: 3.75rem;
    height: 3.75rem;
    border-radius: 50%;
    background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-card));
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.25rem;
}

.empty-state__icon-ring i {
    font-size: 1.5rem;
    color: var(--primary-color);
    opacity: 1;
}

.empty-state__actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-top: 0.5rem;
}

.pagination-summary {
    font-size: 0.82rem;
    color: var(--text-secondary-color);
}

.pagination-summary strong {
    color: var(--text-color);
}

/* Dialog Styles */
.dialog-form {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.dialog-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.38rem;
}

.form-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-color);
}

.required {
    color: #e11d48;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.9rem;
}

@media (max-width: 600px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

.selected-patient-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1rem;
    border-radius: 10px;
    background: color-mix(in srgb, var(--primary-color) 7%, var(--surface-card));
    border: 1px solid color-mix(in srgb, var(--primary-color) 28%, var(--surface-border));
    flex-wrap: wrap;
}

.selected-patient-banner__left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.selected-patient-banner__avatar {
    width: 2.5rem !important;
    height: 2.5rem !important;
    background: var(--primary-color) !important;
    color: var(--primary-contrast-color) !important;
    font-weight: 700;
}

.selected-patient-banner__title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.95rem;
    color: var(--text-color);
}

.patient-id-badge {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    color: var(--text-secondary-color);
}

.selected-patient-banner__meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    font-size: 0.76rem;
    color: var(--text-secondary-color);
    margin-top: 0.15rem;
}

.selected-patient-banner__actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.delivery-completion-section {
    padding: 1rem;
    border: 1px solid color-mix(in srgb, #10b981 30%, var(--surface-border));
    border-radius: 10px;
    background: color-mix(in srgb, #10b981 5%, var(--surface-card));
}

.section-divider-title {
    font-size: 0.84rem;
    font-weight: 700;
    color: #059669;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.mt-2 {
    margin-top: 0.5rem;
}

.dialog-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.65rem;
    padding-top: 1rem;
    border-top: 1px solid var(--surface-border);
}

.parcel-clinical-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.35rem;
}

.rx-days-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #0369a1;
    background: #e0f2fe;
    border: 1px solid #bae6fd;
    padding: 0.14rem 0.45rem;
    border-radius: 6px;
}

.parcel-complaint-preview {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.73rem;
    font-weight: 500;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 0.14rem 0.45rem;
    border-radius: 6px;
    max-width: 220px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.parcel-dependents-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    padding: 0.45rem 0.65rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
}

.parcel-dep-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #1e40af;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.parcel-dep-chip {
    border: 1px solid #93c5fd;
    background: #ffffff;
    color: #1d4ed8;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.parcel-dep-chip:hover {
    background: #dbeafe;
}

.address-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.address-auto-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #047857;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
}
</style>
