<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppointmentDetailsDialog from '@/components/AppointmentDetailsDialog.vue';
import PatientHistorySidebar from '@/components/PatientHistorySidebar.vue';
import { booking, dashboard } from '@/routes';
import {
    appointments as appointmentsRoute,
    medicineTracking,
} from '@/routes/admin';
import { book as bookAppointmentRoute, receipt as appointmentReceiptRoute } from '@/routes/admin/appointments';

type Session = 'Morning' | 'Evening';

type AppointmentDetails = {
    associatedComplaint: string | null;
    chiefComplaint: string | null;
    presentingComplaint: string | null;
    medicalHistory: string | null;
    paternalFamilyHistory: string | null;
    maternalFamilyHistory: string | null;
    vaccinationHistory: string | null;
    addictionHistory: string | null;
    dietaryHabits: string | null;
    occupation: string | null;
    childrenCount: number | null;
    currentMedications: string | null;
    appetite: string | null;
    thirst: string | null;
    sleepPattern: string | null;
    urination: string | null;
    bowelMovements: string | null;
    physicalExamination: string | null;
    personalityNotes: string | null;
    temperament: string | null;
    anxietyNotes: string | null;
    fearNotes: string | null;
    generalNature: string | null;
    dreamNotes: string | null;
    desires: string | null;
    cravings: string | null;
    followUpDate: string | null;
    prescriptionDays: number | null;
    medicationInstructions: string | null;
    diagnosisNotes: string | null;
    treatmentNotes: string | null;
};

type EditableAppointment = {
    purpose_of_appointment: string | null;
    chief_complaint: string | null;
    present_complaint: string | null;
    associated_complaint: string | null;
    past_history: string | null;
    family_history_father_side: string | null;
    family_history_mother_side: string | null;
    history_of_vaccination: string | null;
    addiction: string | null;
    diet: string | null;
    occupation: string | null;
    number_of_children: string | null;
    medicine_taking: string | null;
    appetite: string | null;
    thirst: string | null;
    sleep: string | null;
    urine: string | null;
    stool: string | null;
    pysical_examination: string | null;
    as_a_person: string | null;
    nature_of_person: string | null;
    anxiety: string | null;
    fear: string | null;
    nature: string | null;
    dreams: string | null;
    desire: string | null;
    craving: string | null;
    diagnosis: string | null;
    treatment: string | null;
    medication_instructions: string | null;
    follow_up_day: string | null;
    days_prescription: number | null;
    amount: string | null;
    medicines: any[] | null;
};

type AppointmentHistoryItem = {
    id: number;
    appointmentDate: string;
    appointmentDateLabel: string;
    appointmentType: string;
    session: string;
    status: string;
    editable: EditableAppointment;
};

type AppointmentCard = {
    id: number;
    history: AppointmentHistoryItem[];
    appointmentDate: string;
    appointmentNumber: string | null;
    appointmentSequence: number;
    appointmentType: string;
    amount: string | null;
    paymentType?: string | null;
    medicineStatus?: boolean;
    diagnosis?: string | null;
    chiefComplaint?: string | null;
    patientName: string;
    patientNumber: string | null;
    patientId?: number | null;
    gender: string | null;
    age: number | null;
    dateOfBirth?: string | null;
    address?: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
    session: Session;
    onHold: boolean;
    holdOrder: number | null;
    queueStatus: 'running' | 'pending' | 'on_hold' | 'complete';
    status: string;
    reasonForVisit: string | null;
    details: AppointmentDetails;
    editable: EditableAppointment;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedAppointments = {
    data: AppointmentCard[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
    total: number;
    from: number | null;
    to: number | null;
    per_page: number;
};

type AppointmentsSummary = {
    total: number;
    today: number;
    pending: number;
    on_hold: number;
    complete: number;
    revenue: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'All Appointments', href: appointmentsRoute() },
        ],
    },
});

type DateBreakdownItem = {
    date: string;
    total: number;
    morning: number;
    evening: number;
    complete: number;
    on_hold: number;
    pending: number;
    revenue: number;
};

const props = defineProps<{
    appointments: PaginatedAppointments;
    summary?: AppointmentsSummary;
    dateBreakdown?: DateBreakdownItem[];
    filters: {
        search: string | null;
        status?: string | null;
        session?: string | null;
        appointment_type?: string | null;
        payment_type?: string | null;
        medicine_status?: string | null;
        date_preset?: string | null;
        date_from?: string | null;
        date_to?: string | null;
        perPage: number;
    };
    perPageOptions: number[];
}>();

const viewMode = ref<'date_wise' | 'list'>('date_wise');

const canTeleportActions = ref(false);
onMounted(() => {
    canTeleportActions.value = !!document.getElementById('admin-header-actions');
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

function toDateString(date: Date | null | undefined): string | null {
    if (!date) {
        return null;
    }
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const todayIso = toDateString(new Date()) ?? '';

// Filter reactive state initialized from server props
const searchQuery = ref(props.filters?.search ?? '');
const statusFilter = ref<string | null>(props.filters?.status || null);
const sessionFilter = ref<string | null>(props.filters?.session || null);
const appointmentTypeFilter = ref<string | null>(
    props.filters?.appointment_type || null,
);
const paymentTypeFilter = ref<string | null>(
    props.filters?.payment_type || null,
);
const medicineStatusFilter = ref<string | null>(
    props.filters?.medicine_status || null,
);
const datePresetFilter = ref<string | null>(
    props.filters?.date_preset || null,
);
const dateFromFilter = ref<Date | null>(
    parseLocalDate(props.filters?.date_from),
);
const dateToFilter = ref<Date | null>(parseLocalDate(props.filters?.date_to));
const perPage = ref(props.filters?.perPage ?? 15);

// Dialog & Sidebar state
const selectedAppointment = ref<AppointmentCard | null>(null);
const detailDialogVisible = ref(false);
const showHistorySidebar = ref(false);
const historyPatientId = ref<number | null>(null);

// Dropdown options
const sessionOptions = [
    { label: 'Morning Session', value: 'Morning' },
    { label: 'Evening Session', value: 'Evening' },
];

const visitTypeOptions = [
    { label: 'New Visit', value: 'New' },
    { label: 'Follow Up', value: 'Follow Up' },
];

const paymentTypeOptions = [
    { label: 'Cash', value: 'Cash' },
    { label: 'UPI', value: 'UPI' },
    { label: 'Card', value: 'Card' },
    { label: 'Bank Transfer', value: 'Bank Transfer' },
    { label: 'Online', value: 'Online' },
    { label: 'Unbilled / Unpaid', value: 'unpaid' },
];

const medicineStatusOptions = [
    { label: 'Dispatched', value: 'dispatched' },
    { label: 'Pending Dispatch', value: 'pending' },
];

const datePresets = [
    { label: 'All Dates', value: null, icon: 'pi pi-calendar' },
    { label: 'Today', value: 'today', icon: 'pi pi-bolt' },
    { label: 'Yesterday', value: 'yesterday', icon: 'pi pi-history' },
    { label: 'This Week', value: 'this_week', icon: 'pi pi-calendar-plus' },
    { label: 'Last 7 Days', value: 'last_7_days', icon: 'pi pi-clock' },
    { label: 'This Month', value: 'this_month', icon: 'pi pi-calendar-times' },
    { label: 'Last 30 Days', value: 'last_30_days', icon: 'pi pi-chart-line' },
];

const computedSummary = computed<AppointmentsSummary>(() => {
    if (props.summary) {
        return props.summary;
    }
    const rows = props.appointments.data;
    return {
        total: props.appointments.total,
        today: rows.filter((a) => a.appointmentDate === todayIso).length,
        pending: rows.filter((a) => a.queueStatus === 'pending').length,
        on_hold: rows.filter((a) => a.queueStatus === 'on_hold').length,
        complete: rows.filter((a) => a.queueStatus === 'complete').length,
        revenue: rows.reduce(
            (sum, a) => sum + (a.amount ? Number(a.amount) : 0),
            0,
        ),
    };
});

const hasActiveFilters = computed(() => {
    return Boolean(
        searchQuery.value.trim() ||
            statusFilter.value ||
            sessionFilter.value ||
            appointmentTypeFilter.value ||
            paymentTypeFilter.value ||
            medicineStatusFilter.value ||
            datePresetFilter.value ||
            dateFromFilter.value ||
            dateToFilter.value,
    );
});

const activeFilterChips = computed(() => {
    const chips: Array<{ key: string; label: string; clear: () => void }> = [];

    if (datePresetFilter.value) {
        const presetLabel =
            datePresets.find((p) => p.value === datePresetFilter.value)
                ?.label ?? datePresetFilter.value;
        chips.push({
            key: 'date_preset',
            label: `Date: ${presetLabel}`,
            clear: () => {
                datePresetFilter.value = null;
                applyFilters();
            },
        });
    } else if (dateFromFilter.value || dateToFilter.value) {
        const fromStr = dateFromFilter.value
            ? formatDate(toDateString(dateFromFilter.value)!)
            : 'Start';
        const toStr = dateToFilter.value
            ? formatDate(toDateString(dateToFilter.value)!)
            : 'Now';
        chips.push({
            key: 'date_range',
            label: `Date: ${fromStr} → ${toStr}`,
            clear: () => {
                dateFromFilter.value = null;
                dateToFilter.value = null;
                applyFilters();
            },
        });
    }

    if (statusFilter.value) {
        const statusNames: Record<string, string> = {
            pending: 'Waiting',
            on_hold: 'On Hold',
            complete: 'Completed',
        };
        chips.push({
            key: 'status',
            label: `Status: ${statusNames[statusFilter.value] ?? statusFilter.value}`,
            clear: () => {
                statusFilter.value = null;
                applyFilters();
            },
        });
    }

    if (sessionFilter.value) {
        chips.push({
            key: 'session',
            label: `Session: ${sessionFilter.value}`,
            clear: () => {
                sessionFilter.value = null;
                applyFilters();
            },
        });
    }

    if (appointmentTypeFilter.value) {
        chips.push({
            key: 'appointment_type',
            label: `Type: ${appointmentTypeFilter.value}`,
            clear: () => {
                appointmentTypeFilter.value = null;
                applyFilters();
            },
        });
    }

    if (paymentTypeFilter.value) {
        chips.push({
            key: 'payment_type',
            label: `Payment: ${paymentTypeFilter.value === 'unpaid' ? 'Unbilled' : paymentTypeFilter.value}`,
            clear: () => {
                paymentTypeFilter.value = null;
                applyFilters();
            },
        });
    }

    if (medicineStatusFilter.value) {
        chips.push({
            key: 'medicine_status',
            label: `Pharmacy: ${medicineStatusFilter.value === 'dispatched' ? 'Dispatched' : 'Pending'}`,
            clear: () => {
                medicineStatusFilter.value = null;
                applyFilters();
            },
        });
    }

    return chips;
});

function buildQueryParams() {
    return {
        search: searchQuery.value.trim() || undefined,
        status: statusFilter.value || undefined,
        session: sessionFilter.value || undefined,
        appointment_type: appointmentTypeFilter.value || undefined,
        payment_type: paymentTypeFilter.value || undefined,
        medicine_status: medicineStatusFilter.value || undefined,
        date_preset: datePresetFilter.value || undefined,
        date_from: toDateString(dateFromFilter.value) || undefined,
        date_to: toDateString(dateToFilter.value) || undefined,
        perPage: perPage.value !== 15 ? perPage.value : undefined,
    };
}

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters(): void {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = null;
    }

    router.get(appointmentsRoute().url, buildQueryParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

watch(searchQuery, (val) => {
    if (val.trim() === (props.filters?.search ?? '').trim()) {
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

function selectDatePreset(preset: string | null): void {
    if (datePresetFilter.value === preset && preset !== null) {
        datePresetFilter.value = null;
    } else {
        datePresetFilter.value = preset;
    }
    dateFromFilter.value = null;
    dateToFilter.value = null;
    applyFilters();
}

function onCustomDateChange(): void {
    datePresetFilter.value = null;
    if (
        dateFromFilter.value &&
        dateToFilter.value &&
        dateToFilter.value < dateFromFilter.value
    ) {
        dateToFilter.value = new Date(dateFromFilter.value.getTime());
    }
    applyFilters();
}

function toggleStatusFilter(
    status: 'pending' | 'on_hold' | 'complete' | null,
): void {
    if (status === null || statusFilter.value === status) {
        statusFilter.value = null;
    } else {
        statusFilter.value = status;
    }
    applyFilters();
}

function toggleSessionFilter(session: 'Morning' | 'Evening' | null): void {
    if (session === null || sessionFilter.value === session) {
        sessionFilter.value = null;
    } else {
        sessionFilter.value = session;
    }
    applyFilters();
}

function resetAllFilters(): void {
    searchQuery.value = '';
    statusFilter.value = null;
    sessionFilter.value = null;
    appointmentTypeFilter.value = null;
    paymentTypeFilter.value = null;
    medicineStatusFilter.value = null;
    datePresetFilter.value = null;
    dateFromFilter.value = null;
    dateToFilter.value = null;
    applyFilters();
}

function handlePerPageChange(): void {
    applyFilters();
}

function goToPage(url: string | null): void {
    if (!url) {
        return;
    }

    router.get(
        url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function openDetails(appointment: AppointmentCard): void {
    selectedAppointment.value = appointment;
    detailDialogVisible.value = true;
}

function viewPatientHistory(patientId: number | null | undefined): void {
    if (!patientId) {
        return;
    }
    historyPatientId.value = patientId;
    showHistorySidebar.value = true;
}

function printReceipt(appointmentId: number): void {
    window.open(appointmentReceiptRoute(appointmentId).url, '_blank');
}

function onDialogSaved(): void {
    router.reload({
        only: ['appointments', 'summary'],
    });
}

function formatDate(dateStr: string): string {
    const parsed = parseLocalDate(dateStr);
    if (!parsed) {
        return dateStr;
    }

    return new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
}

function formatFullWeekdayDate(dateStr: string): string {
    const parsed = parseLocalDate(dateStr);
    if (!parsed) {
        return dateStr;
    }

    return new Intl.DateTimeFormat('en-IN', {
        weekday: 'long',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
}

function formatShortCardWeekday(dateStr: string): string {
    if (dateStr === todayIso) {
        return 'Today';
    }
    const parsed = parseLocalDate(dateStr);
    if (!parsed) {
        return '';
    }
    return new Intl.DateTimeFormat('en-IN', { weekday: 'short' }).format(parsed);
}

function formatShortCardDay(dateStr: string): string {
    const parsed = parseLocalDate(dateStr);
    if (!parsed) {
        return dateStr;
    }
    return new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: 'short',
    }).format(parsed);
}

function selectSpecificDate(dateStr: string): void {
    const currentFrom = toDateString(dateFromFilter.value);
    const currentTo = toDateString(dateToFilter.value);
    if (currentFrom === dateStr && currentTo === dateStr && !datePresetFilter.value) {
        dateFromFilter.value = null;
        dateToFilter.value = null;
    } else {
        datePresetFilter.value = null;
        dateFromFilter.value = parseLocalDate(dateStr);
        dateToFilter.value = parseLocalDate(dateStr);
    }
    applyFilters();
}

function isDateSelected(dateStr: string): boolean {
    const currentFrom = toDateString(dateFromFilter.value);
    const currentTo = toDateString(dateToFilter.value);
    if (datePresetFilter.value === 'today' && dateStr === todayIso) {
        return true;
    }
    return currentFrom === dateStr && currentTo === dateStr;
}

function getDateGroupStats(dateStr: string) {
    const fromServer = props.dateBreakdown?.find((d) => d.date === dateStr);
    if (fromServer) {
        return fromServer;
    }
    const rows = props.appointments.data.filter(
        (r) => r.appointmentDate === dateStr,
    );
    return {
        date: dateStr,
        total: rows.length,
        morning: rows.filter((r) => r.session === 'Morning').length,
        evening: rows.filter((r) => r.session === 'Evening').length,
        complete: rows.filter((r) => r.queueStatus === 'complete').length,
        on_hold: rows.filter((r) => r.queueStatus === 'on_hold').length,
        pending: rows.filter((r) => r.queueStatus === 'pending').length,
        revenue: rows.reduce((s, r) => s + Number(r.amount ?? 0), 0),
    };
}

function formatCurrency(amount: number | string | null | undefined): string {
    const num = Number(amount ?? 0);
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(Number.isNaN(num) ? 0 : num);
}

function formatToken(sequence: number): string {
    return sequence.toString().padStart(2, '0');
}

function getInitials(name: string): string {
    return (
        name
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || 'PT'
    );
}

function queueStatusLabel(status: AppointmentCard['queueStatus']): string {
    return {
        complete: 'Completed',
        on_hold: 'On Hold',
        pending: 'Waiting',
        running: 'Running Now',
    }[status];
}

function queueStatusSeverity(
    status: AppointmentCard['queueStatus'],
): 'success' | 'warn' | 'danger' | 'contrast' {
    switch (status) {
        case 'complete':
            return 'success';
        case 'on_hold':
            return 'danger';
        case 'pending':
            return 'warn';
        case 'running':
            return 'contrast';
    }
}
</script>

<template>
    <Head title="All Appointments" />

    <div class="page-grid all-appointments-workspace">
        <!-- Teleport Primary Actions into Layout Header -->
        <Teleport v-if="canTeleportActions" to="#admin-header-actions">
            <div class="header-action-group">
                <Button
                    label="Live Queue Desk"
                    icon="pi pi-desktop"
                    severity="secondary"
                    outlined
                    size="small"
                    class="btn-auto"
                    @click="router.visit(booking().url)"
                />
                <Button
                    label="Book Appointment"
                    icon="pi pi-calendar-plus"
                    severity="primary"
                    size="small"
                    class="btn-auto"
                    @click="router.visit(bookAppointmentRoute().url)"
                />
            </div>
        </Teleport>

        <!-- Interactive KPI Summary Cards -->
        <section class="summary-grid" aria-label="Appointments overview">
            <button
                type="button"
                class="summary-card"
                :class="{
                    'is-active':
                        !statusFilter &&
                        !datePresetFilter &&
                        !dateFromFilter &&
                        !dateToFilter,
                }"
                @click="toggleStatusFilter(null)"
            >
                <div class="summary-card__icon summary-card__icon--blue">
                    <i class="pi pi-calendar"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Filtered Total</span>
                    <strong class="summary-card__value">{{
                        computedSummary.total
                    }}</strong>
                    <span class="summary-card__sub"
                        >{{ computedSummary.today }} scheduled for today</span
                    >
                </div>
            </button>

            <button
                type="button"
                class="summary-card"
                :class="{ 'is-active': statusFilter === 'pending' }"
                @click="toggleStatusFilter('pending')"
            >
                <div class="summary-card__icon summary-card__icon--amber">
                    <i class="pi pi-clock"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Waiting in Queue</span>
                    <strong class="summary-card__value">{{
                        computedSummary.pending
                    }}</strong>
                    <span class="summary-card__sub"
                        >Click to filter waiting cases</span
                    >
                </div>
            </button>

            <button
                type="button"
                class="summary-card"
                :class="{ 'is-active': statusFilter === 'on_hold' }"
                @click="toggleStatusFilter('on_hold')"
            >
                <div class="summary-card__icon summary-card__icon--rose">
                    <i class="pi pi-pause-circle"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">On Hold</span>
                    <strong class="summary-card__value">{{
                        computedSummary.on_hold
                    }}</strong>
                    <span class="summary-card__sub"
                        >Click to filter on-hold cases</span
                    >
                </div>
            </button>

            <button
                type="button"
                class="summary-card"
                :class="{ 'is-active': statusFilter === 'complete' }"
                @click="toggleStatusFilter('complete')"
            >
                <div class="summary-card__icon summary-card__icon--green">
                    <i class="pi pi-check-circle"></i>
                </div>
                <div class="summary-card__content">
                    <span class="summary-card__label">Completed & Billed</span>
                    <strong class="summary-card__value">{{
                        computedSummary.complete
                    }}</strong>
                    <span class="summary-card__sub"
                        >Revenue:
                        {{ formatCurrency(computedSummary.revenue) }}</span
                    >
                </div>
            </button>
        </section>

        <!-- Unified Table Workspace Card -->
        <section class="page-card admin-table-card">
            <!-- Row 1: Quick Date Presets & Status Segment Bar -->
            <div class="workspace-header">
                <div
                    class="date-preset-segments"
                    role="group"
                    aria-label="Date range presets"
                >
                    <span class="segment-group-label">
                        <i class="pi pi-calendar"></i> Date:
                    </span>
                    <button
                        v-for="preset in datePresets"
                        :key="preset.label"
                        type="button"
                        class="segment-pill"
                        :class="{
                            'is-selected':
                                preset.value === null
                                    ? !datePresetFilter &&
                                      !dateFromFilter &&
                                      !dateToFilter
                                    : datePresetFilter === preset.value,
                        }"
                        @click="selectDatePreset(preset.value)"
                    >
                        <span>{{ preset.label }}</span>
                    </button>
                </div>

                <div
                    class="status-segments"
                    role="group"
                    aria-label="Filter appointments by status"
                >
                    <button
                        type="button"
                        class="segment-pill"
                        :class="{ 'is-selected': !statusFilter }"
                        @click="toggleStatusFilter(null)"
                    >
                        All ({{ computedSummary.total }})
                    </button>
                    <button
                        type="button"
                        class="segment-pill"
                        :class="{ 'is-selected': statusFilter === 'pending' }"
                        @click="toggleStatusFilter('pending')"
                    >
                        Waiting ({{ computedSummary.pending }})
                    </button>
                    <button
                        type="button"
                        class="segment-pill segment-pill--warn"
                        :class="{ 'is-selected': statusFilter === 'on_hold' }"
                        @click="toggleStatusFilter('on_hold')"
                    >
                        On Hold ({{ computedSummary.on_hold }})
                    </button>
                    <button
                        type="button"
                        class="segment-pill"
                        :class="{ 'is-selected': statusFilter === 'complete' }"
                        @click="toggleStatusFilter('complete')"
                    >
                        Completed ({{ computedSummary.complete }})
                    </button>
                </div>
            </div>

            <!-- Row 2: Search, Date Range Pickers, and Attribute Filters -->
            <div class="filter-bar">
                <div class="filter-bar__primary-row">
                    <!-- Search Input -->
                    <div class="filter-bar__search">
                        <i class="pi pi-search filter-bar__search-icon"></i>
                        <InputText
                            v-model="searchQuery"
                            placeholder="Search patient name, phone, token #, diagnosis, or complaint..."
                            class="filter-bar__search-input"
                            @keyup.enter="applyFilters"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="filter-bar__clear-input"
                            title="Clear search"
                            @click="
                                searchQuery = '';
                                applyFilters();
                            "
                        >
                            <i class="pi pi-times"></i>
                        </button>
                    </div>

                    <!-- Custom Date Range Pickers -->
                    <div class="filter-bar__date-range">
                        <div class="date-input-wrap">
                            <span class="date-input-label">From</span>
                            <DatePicker
                                v-model="dateFromFilter"
                                date-format="yy-mm-dd"
                                placeholder="yyyy-mm-dd"
                                show-icon
                                show-clear
                                class="date-range-picker"
                                @update:model-value="onCustomDateChange"
                            />
                        </div>
                        <span class="date-range-sep">→</span>
                        <div class="date-input-wrap">
                            <span class="date-input-label">To</span>
                            <DatePicker
                                v-model="dateToFilter"
                                date-format="yy-mm-dd"
                                placeholder="yyyy-mm-dd"
                                :min-date="dateFromFilter ?? undefined"
                                show-icon
                                show-clear
                                class="date-range-picker"
                                @update:model-value="onCustomDateChange"
                            />
                        </div>
                    </div>
                </div>

                <!-- Dropdown Filters Row -->
                <div class="filter-bar__secondary-row">
                    <div class="filter-bar__dropdowns">
                        <Select
                            v-model="sessionFilter"
                            :options="sessionOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="All Sessions"
                            show-clear
                            class="filter-select"
                            @change="applyFilters"
                        />

                        <Select
                            v-model="appointmentTypeFilter"
                            :options="visitTypeOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="All Visit Types"
                            show-clear
                            class="filter-select"
                            @change="applyFilters"
                        />

                        <Select
                            v-model="paymentTypeFilter"
                            :options="paymentTypeOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="All Payment Modes"
                            show-clear
                            class="filter-select"
                            @change="applyFilters"
                        />

                        <Select
                            v-model="medicineStatusFilter"
                            :options="medicineStatusOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="All Pharmacy Status"
                            show-clear
                            class="filter-select"
                            @change="applyFilters"
                        />
                    </div>

                    <div class="filter-bar__actions">
                        <div class="view-mode-toggle" role="group" aria-label="View mode">
                            <button
                                type="button"
                                class="view-mode-btn"
                                :class="{ 'is-active': viewMode === 'date_wise' }"
                                @click="viewMode = 'date_wise'"
                            >
                                <i class="pi pi-calendar-clock"></i>
                                <span>Date-Wise View</span>
                            </button>
                            <button
                                type="button"
                                class="view-mode-btn"
                                :class="{ 'is-active': viewMode === 'list' }"
                                @click="viewMode = 'list'"
                            >
                                <i class="pi pi-list"></i>
                                <span>Flat List</span>
                            </button>
                        </div>

                        <Button
                            v-if="hasActiveFilters"
                            icon="pi pi-filter-slash"
                            label="Reset All"
                            severity="secondary"
                            outlined
                            size="small"
                            class="btn-auto"
                            @click="resetAllFilters"
                        />
                    </div>
                </div>

                <!-- Date-Wise Breakdown Strip -->
                <div
                    v-if="props.dateBreakdown && props.dateBreakdown.length > 0"
                    class="date-breakdown-strip"
                >
                    <div class="date-breakdown-strip__header">
                        <span class="date-breakdown-strip__title">
                            <i class="pi pi-calendar"></i>
                            Date-Wise Schedule Breakdown (click any date to filter)
                        </span>
                    </div>
                    <div class="date-breakdown-cards">
                        <button
                            v-for="day in props.dateBreakdown"
                            :key="day.date"
                            type="button"
                            class="date-breakdown-card"
                            :class="{
                                'is-selected': isDateSelected(day.date),
                                'is-today': day.date === todayIso,
                            }"
                            @click="selectSpecificDate(day.date)"
                        >
                            <div class="date-breakdown-card__top">
                                <span class="date-breakdown-card__weekday">
                                    {{ formatShortCardWeekday(day.date) }}
                                </span>
                                <span class="date-breakdown-card__total">
                                    {{ day.total }} {{ day.total === 1 ? 'visit' : 'visits' }}
                                </span>
                            </div>
                            <strong class="date-breakdown-card__date">
                                {{ formatShortCardDay(day.date) }}
                            </strong>
                            <div class="date-breakdown-card__slots">
                                <span title="Morning Session">
                                    <i class="pi pi-sun"></i> {{ day.morning }}
                                </span>
                                <span title="Evening Session">
                                    <i class="pi pi-moon"></i> {{ day.evening }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Active Filter Chips Strip -->
                <div
                    v-if="activeFilterChips.length > 0"
                    class="active-filters-strip"
                >
                    <span class="active-filters-strip__title"
                        >Active filters:</span
                    >
                    <span
                        v-for="chip in activeFilterChips"
                        :key="chip.key"
                        class="active-filter-chip"
                    >
                        <span>{{ chip.label }}</span>
                        <button
                            type="button"
                            class="active-filter-chip__remove"
                            title="Remove filter"
                            @click="chip.clear()"
                        >
                            <i class="pi pi-times"></i>
                        </button>
                    </span>
                </div>
            </div>

            <!-- Appointments DataTable -->
            <DataTable
                v-if="props.appointments.data.length"
                :value="props.appointments.data"
                :row-group-mode="viewMode === 'date_wise' ? 'subheader' : undefined"
                :group-rows-by="viewMode === 'date_wise' ? 'appointmentDate' : undefined"
                class="admin-appointments-table"
                responsive-layout="scroll"
                striped-rows
                row-hover
                data-key="id"
                @row-click="(e) => openDetails(e.data)"
            >
                <template #groupheader="{ data }">
                    <div class="date-group-subheader">
                        <div class="date-group-subheader__left">
                            <span class="date-group-subheader__icon">
                                <i class="pi pi-calendar"></i>
                            </span>
                            <div>
                                <strong class="date-group-subheader__title">
                                    {{ formatFullWeekdayDate(data.appointmentDate) }}
                                </strong>
                                <span
                                    v-if="data.appointmentDate === todayIso"
                                    class="today-pill"
                                >
                                    Today
                                </span>
                            </div>
                        </div>

                        <div class="date-group-subheader__stats">
                            <span class="date-stat-pill date-stat-pill--morning">
                                <i class="pi pi-sun"></i>
                                Morning: {{ getDateGroupStats(data.appointmentDate).morning }}
                            </span>
                            <span class="date-stat-pill date-stat-pill--evening">
                                <i class="pi pi-moon"></i>
                                Evening: {{ getDateGroupStats(data.appointmentDate).evening }}
                            </span>
                            <span class="date-stat-pill date-stat-pill--complete">
                                <i class="pi pi-check-circle"></i>
                                Completed: {{ getDateGroupStats(data.appointmentDate).complete }}
                            </span>
                            <span class="date-stat-pill date-stat-pill--pending">
                                <i class="pi pi-clock"></i>
                                Waiting: {{ getDateGroupStats(data.appointmentDate).pending }}
                            </span>
                            <span class="date-stat-pill date-stat-pill--total">
                                Total: {{ getDateGroupStats(data.appointmentDate).total }}
                            </span>
                        </div>
                    </div>
                </template>
                <Column
                    header="Token / Ref"
                    style="width: 9rem; min-width: 9rem"
                >
                    <template #body="{ data }">
                        <div class="token-cell">
                            <span class="admin-token-chip">
                                Token #{{ formatToken(data.appointmentSequence) }}
                            </span>
                            <span
                                v-if="data.appointmentNumber"
                                class="appointment-muted-copy"
                            >
                                No. {{ data.appointmentNumber }}
                            </span>
                        </div>
                    </template>
                </Column>

                <Column
                    field="appointmentDate"
                    header="Date & Session"
                    sortable
                    style="width: 11.5rem; min-width: 11.5rem"
                >
                    <template #body="{ data }">
                        <div class="date-session-cell">
                            <div class="appointment-date-cell">
                                <i class="pi pi-calendar"></i>
                                <span>{{
                                    formatDate(data.appointmentDate)
                                }}</span>
                                <span
                                    v-if="data.appointmentDate === todayIso"
                                    class="today-pill"
                                >
                                    Today
                                </span>
                            </div>
                            <span
                                class="admin-session-badge"
                                :class="`admin-session-badge--${data.session.toLowerCase()}`"
                            >
                                <i
                                    :class="
                                        data.session.toLowerCase() === 'morning'
                                            ? 'pi pi-sun'
                                            : 'pi pi-moon'
                                    "
                                ></i>
                                {{ data.session }}
                            </span>
                        </div>
                    </template>
                </Column>

                <Column
                    field="patientName"
                    header="Patient"
                    sortable
                    style="min-width: 15.5rem"
                >
                    <template #body="{ data }">
                        <div class="admin-person-cell">
                            <Avatar
                                :label="getInitials(data.patientName)"
                                shape="circle"
                                class="admin-person-cell__avatar appointment-avatar"
                            />
                            <div class="patient-identity-col">
                                <button
                                    type="button"
                                    class="appointment-patient-name-btn"
                                    title="View patient clinical timeline"
                                    @click.stop="
                                        viewPatientHistory(data.patientId)
                                    "
                                >
                                    {{ data.patientName }}
                                </button>
                                <div class="appointment-muted-copy">
                                    ID #{{ data.patientId }}
                                    <template v-if="data.age">
                                        · {{ data.age }}y
                                    </template>
                                    <template v-if="data.gender">
                                        · {{ data.gender }}
                                    </template>
                                    <template v-if="data.phone">
                                        · {{ data.phone }}
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column
                    header="Clinical Notes"
                    style="min-width: 13rem"
                >
                    <template #body="{ data }">
                        <div
                            v-if="
                                data.diagnosis ||
                                data.chiefComplaint ||
                                data.reasonForVisit
                            "
                            class="clinical-cell"
                        >
                            <strong
                                v-if="data.diagnosis"
                                class="clinical-cell__diag"
                                :title="data.diagnosis"
                            >
                                {{ data.diagnosis }}
                            </strong>
                            <span
                                v-if="
                                    data.chiefComplaint || data.reasonForVisit
                                "
                                class="clinical-cell__complaint"
                                :title="
                                    data.chiefComplaint ||
                                    data.reasonForVisit ||
                                    ''
                                "
                            >
                                {{
                                    data.chiefComplaint || data.reasonForVisit
                                }}
                            </span>
                        </div>
                        <span v-else class="text-muted-italic"
                            >No clinical notes</span
                        >
                    </template>
                </Column>

                <Column
                    field="appointmentType"
                    header="Type & Billing"
                    sortable
                    style="width: 10.5rem; min-width: 10.5rem"
                >
                    <template #body="{ data }">
                        <div class="visit-fee-cell">
                            <span
                                class="admin-type-badge"
                                :class="`admin-type-badge--${data.appointmentType.toLowerCase().replace(' ', '-')}`"
                            >
                                {{ data.appointmentType }}
                            </span>
                            <div v-if="data.amount" class="billing-meta-row">
                                <strong class="appointment-fee">
                                    ₹{{ data.amount }}
                                </strong>
                                <span
                                    v-if="data.paymentType"
                                    class="payment-mode-pill"
                                >
                                    {{ data.paymentType }}
                                </span>
                            </div>
                            <span v-else class="text-muted-italic">Unbilled</span>
                        </div>
                    </template>
                </Column>

                <Column
                    field="queueStatus"
                    header="Status"
                    sortable
                    style="width: 9.5rem; min-width: 9.5rem"
                >
                    <template #body="{ data }">
                        <div class="status-pharmacy-cell">
                            <Tag
                                :value="queueStatusLabel(data.queueStatus)"
                                :severity="queueStatusSeverity(data.queueStatus)"
                                rounded
                            />
                            <span
                                v-if="data.medicineStatus"
                                class="pharmacy-sub pharmacy-sub--done"
                            >
                                <i class="pi pi-check-circle"></i> Meds
                                Dispatched
                            </span>
                            <span
                                v-else-if="data.queueStatus === 'complete'"
                                class="pharmacy-sub pharmacy-sub--pending"
                            >
                                <i class="pi pi-box"></i> At Pharmacy
                            </span>
                        </div>
                    </template>
                </Column>

                <Column
                    header="Actions"
                    class="text-right"
                    header-class="text-right"
                    style="width: 13.5rem; min-width: 13.5rem"
                >
                    <template #body="{ data }">
                        <div class="row-actions" @click.stop>
                            <Button
                                v-tooltip.top="'Inspect & Edit Clinical Case'"
                                icon="pi pi-folder-open"
                                label="Inspect"
                                size="small"
                                outlined
                                class="btn-auto"
                                @click="openDetails(data)"
                            />
                            <Button
                                v-if="data.patientId"
                                v-tooltip.top="'Patient Clinical Timeline'"
                                icon="pi pi-history"
                                severity="info"
                                text
                                rounded
                                size="small"
                                class="action-icon-btn"
                                @click="viewPatientHistory(data.patientId)"
                            />
                            <Button
                                v-tooltip.top="'Print Appointment Receipt'"
                                icon="pi pi-print"
                                severity="secondary"
                                text
                                rounded
                                size="small"
                                class="action-icon-btn"
                                @click="printReceipt(data.id)"
                            />
                            <Link
                                v-if="
                                    data.queueStatus === 'complete' ||
                                    data.status?.toLowerCase() === 'complete'
                                "
                                :href="
                                    medicineTracking({
                                        query: { selected: data.id },
                                    }).url
                                "
                            >
                                <Button
                                    v-tooltip.top="'Dispense Medicine & Bill'"
                                    icon="pi pi-box"
                                    severity="success"
                                    text
                                    rounded
                                    size="small"
                                    class="action-icon-btn"
                                />
                            </Link>
                        </div>
                    </template>
                </Column>
            </DataTable>

            <div v-else class="admin-empty-state admin-empty-state--soft">
                <i class="pi pi-calendar-times"></i>
                <h3>No matching appointments found</h3>
                <p v-if="hasActiveFilters">
                    No appointments match your selected date range, status, or
                    search criteria.
                </p>
                <p v-else>
                    Appointments will appear here once bookings are registered.
                </p>
                <Button
                    v-if="hasActiveFilters"
                    label="Reset All Filters"
                    icon="pi pi-filter-slash"
                    severity="secondary"
                    outlined
                    size="small"
                    class="btn-auto"
                    @click="resetAllFilters"
                />
            </div>

            <div
                v-if="props.appointments.data.length"
                class="admin-pagination-container"
            >
                <div class="admin-pagination-meta">
                    <span class="admin-pagination-summary">
                        Showing
                        <strong>{{ props.appointments.from ?? 0 }}</strong>–<strong>{{
                            props.appointments.to ?? 0
                        }}</strong>
                        of
                        <strong>{{ props.appointments.total }}</strong>
                        appointments
                    </span>

                    <label class="admin-pagination-perpage">
                        <span>Rows:</span>
                        <Select
                            v-model="perPage"
                            :options="props.perPageOptions"
                            class="admin-pagination-perpage__select"
                            @change="handlePerPageChange"
                        />
                    </label>
                </div>

                <div
                    v-if="
                        props.appointments.links &&
                        props.appointments.links.length > 3
                    "
                    class="admin-pagination"
                >
                    <button
                        v-for="link in props.appointments.links"
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

        <AppointmentDetailsDialog
            v-model:visible="detailDialogVisible"
            :appointment="selectedAppointment"
            @saved="onDialogSaved"
        />

        <PatientHistorySidebar
            v-model:visible="showHistorySidebar"
            :patient-id="historyPatientId"
        />
    </div>
</template>

<style scoped>
.all-appointments-workspace {
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

.summary-card__icon--rose {
    background: color-mix(in srgb, #f43f5e 14%, var(--surface-card));
    color: #e11d48;
}

.summary-card__icon--green {
    background: color-mix(in srgb, #10b981 14%, var(--surface-card));
    color: #059669;
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
    gap: 0.85rem;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--surface-border);
    background: color-mix(in srgb, var(--surface-50) 65%, var(--surface-card));
    flex-wrap: wrap;
}

.date-preset-segments,
.status-segments {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.segment-group-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary-color);
    margin-right: 0.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.segment-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.34rem 0.72rem;
    border-radius: 999px;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-secondary-color);
    font-size: 0.76rem;
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
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--surface-border);
    background: var(--surface-card);
}

.filter-bar__primary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.filter-bar__search {
    position: relative;
    flex: 1 1 20rem;
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

.filter-bar__date-range {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.date-input-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.date-input-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary-color);
}

.date-range-picker {
    width: 10.5rem;
}

.date-range-sep {
    color: var(--text-secondary-color);
    font-weight: 700;
    font-size: 0.85rem;
}

.filter-bar__secondary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.filter-bar__dropdowns {
    display: grid;
    grid-template-columns: repeat(4, minmax(10.5rem, 1fr));
    gap: 0.65rem;
    flex: 1 1 auto;
}

@media (max-width: 1100px) {
    .filter-bar__dropdowns {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .filter-bar__dropdowns {
        grid-template-columns: 1fr;
    }
}

.filter-select {
    width: 100%;
}

.filter-bar__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Active Filters Strip */
.active-filters-strip {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    padding-top: 0.35rem;
    border-top: 1px dashed var(--surface-border);
}

.active-filters-strip__title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-secondary-color);
}

.active-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-card));
    border: 1px solid color-mix(in srgb, var(--primary-color) 28%, transparent);
    color: var(--primary-color);
    font-size: 0.74rem;
    font-weight: 700;
}

.active-filter-chip__remove {
    border: none;
    background: none;
    padding: 0;
    color: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    opacity: 0.75;
}

.active-filter-chip__remove:hover {
    opacity: 1;
}

/* Table Cells */
.admin-appointments-table :deep(.p-datatable-tbody > tr) {
    cursor: pointer;
}

.token-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.2rem;
}

.date-session-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
}

.appointment-date-cell {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--text-color);
}

.appointment-date-cell i {
    color: var(--text-secondary-color);
    font-size: 0.8rem;
}

.today-pill {
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    padding: 0.08rem 0.4rem;
    border-radius: 999px;
    background: color-mix(in srgb, #10b981 15%, var(--surface-card));
    color: #059669;
}

.appointment-avatar {
    width: 2.35rem !important;
    height: 2.35rem !important;
}

.patient-identity-col {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
}

.appointment-patient-name-btn {
    border: none;
    background: none;
    padding: 0;
    margin: 0;
    text-align: left;
    font-family: inherit;
    font-size: 0.92rem;
    color: var(--text-color);
    font-weight: 700;
    cursor: pointer;
}

.appointment-patient-name-btn:hover {
    color: var(--primary-color);
    text-decoration: underline;
}

.appointment-muted-copy {
    color: var(--text-secondary-color);
    font-size: 0.76rem;
    font-weight: 500;
}

.clinical-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    max-width: 14rem;
}

.clinical-cell__diag {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--text-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.clinical-cell__complaint {
    font-size: 0.76rem;
    color: var(--text-secondary-color);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.3;
}

.visit-fee-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.28rem;
}

.billing-meta-row {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.appointment-fee {
    font-size: 0.86rem;
    font-weight: 800;
    color: var(--text-color);
    font-variant-numeric: tabular-nums;
}

.payment-mode-pill {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.08rem 0.4rem;
    border-radius: 4px;
    background: var(--surface-100);
    border: 1px solid var(--surface-border);
    color: var(--text-secondary-color);
}

.status-pharmacy-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
}

.pharmacy-sub {
    font-size: 0.7rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.pharmacy-sub--done {
    color: #059669;
}

.pharmacy-sub--pending {
    color: #d97706;
}

.text-muted-italic {
    font-size: 0.76rem;
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
    gap: 0.25rem;
}

.action-icon-btn {
    width: 2rem !important;
    height: 2rem !important;
    padding: 0 !important;
}

.admin-pagination-meta {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.admin-pagination-summary {
    color: var(--text-secondary-color);
    font-size: 0.82rem;
    font-weight: 500;
}

.admin-pagination-summary strong {
    color: var(--text-color);
}

.admin-pagination-perpage {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--text-secondary-color);
    font-size: 0.82rem;
    font-weight: 600;
}

.admin-pagination-perpage__select {
    min-width: 4.75rem;
}

.view-mode-toggle {
    display: inline-flex;
    align-items: center;
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
    border-radius: 0.55rem;
    padding: 0.18rem;
    gap: 0.15rem;
}

.view-mode-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.68rem;
    border-radius: 0.42rem;
    border: none;
    background: transparent;
    color: var(--text-secondary-color);
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.view-mode-btn.is-active {
    background: #0c6e72;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(12, 110, 114, 0.25);
}

.date-breakdown-strip {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--surface-border);
    display: grid;
    gap: 0.5rem;
}

.date-breakdown-strip__title {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary-color);
}

.date-breakdown-cards {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
}

.date-breakdown-card {
    min-width: 7.75rem;
    flex-shrink: 0;
    padding: 0.55rem 0.7rem;
    border-radius: 0.6rem;
    border: 1px solid var(--surface-border);
    background: var(--surface-ground);
    text-align: left;
    cursor: pointer;
    display: grid;
    gap: 0.2rem;
    transition: all 0.15s ease;
}

.date-breakdown-card:hover {
    border-color: #0c6e72;
    transform: translateY(-1px);
}

.date-breakdown-card.is-today {
    border-color: rgba(12, 110, 114, 0.4);
    background: rgba(12, 110, 114, 0.04);
}

.date-breakdown-card.is-selected {
    background: #0c6e72;
    border-color: #0c6e72;
    color: #ffffff;
}

.date-breakdown-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-secondary-color);
}

.date-breakdown-card.is-selected .date-breakdown-card__top,
.date-breakdown-card.is-selected .date-breakdown-card__date,
.date-breakdown-card.is-selected .date-breakdown-card__slots {
    color: #ffffff;
}

.date-breakdown-card__date {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--text-color);
}

.date-breakdown-card__slots {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-secondary-color);
}

.date-group-subheader {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.6rem 0.85rem;
    background: rgba(12, 110, 114, 0.06);
    border-left: 4px solid #0c6e72;
    border-radius: 0.35rem;
}

.date-group-subheader__left {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.date-group-subheader__icon {
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 0.45rem;
    background: #0c6e72;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.88rem;
}

.date-group-subheader__title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text-color);
    margin-right: 0.45rem;
}

.date-group-subheader__stats {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.date-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.22rem 0.58rem;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    background: #ffffff;
    border: 1px solid var(--surface-border);
    color: var(--text-color);
}

.date-stat-pill--morning {
    color: #b45309;
    border-color: rgba(245, 158, 11, 0.3);
    background: #fffbeb;
}

.date-stat-pill--evening {
    color: #1d4ed8;
    border-color: rgba(59, 130, 246, 0.3);
    background: #eff6ff;
}

.date-stat-pill--complete {
    color: #15803d;
    border-color: rgba(34, 197, 94, 0.3);
    background: #f0fdf4;
}

.date-stat-pill--pending {
    color: #92400e;
    border-color: rgba(217, 119, 6, 0.3);
    background: #fef3c7;
}

.date-stat-pill--total {
    background: #0c6e72;
    border-color: #0c6e72;
    color: #ffffff;
}
</style>
