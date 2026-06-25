<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';
import AppointmentDetailsDialog from '@/components/AppointmentDetailsDialog.vue';
import { dashboard } from '@/routes';
import { appointments as appointmentsRoute, medicineTracking } from '@/routes/admin';

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

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'All Appointments', href: appointmentsRoute() },
        ],
    },
});

const props = defineProps<{
    appointments: PaginatedAppointments;
    filters: {
        search: string | null;
        perPage: number;
    };
    perPageOptions: number[];
}>();

const searchQuery = ref(props.filters?.search ?? '');
const perPage = ref(props.filters?.perPage ?? 15);
const selectedAppointment = ref<AppointmentCard | null>(null);
const detailDialogVisible = ref(false);

const currentPageInsights = computed(() => {
    const appointments = props.appointments.data;

    return [
        {
            label: 'Showing',
            value: appointments.length,
            note: `${props.appointments.total} total records`,
        },
        {
            label: 'Awaiting action',
            value: appointments.filter(
                (appointment) => appointment.queueStatus === 'pending',
            ).length,
            note: 'Pending on this page',
        },
        {
            label: 'Completed',
            value: appointments.filter(
                (appointment) => appointment.queueStatus === 'complete',
            ).length,
            note: 'Closed cases on this page',
        },
    ];
});

function openDetails(appointment: AppointmentCard): void {
    selectedAppointment.value = appointment;
    detailDialogVisible.value = true;
}

function onDialogSaved(): void {
    router.reload({
        only: ['appointments'],
    });
}

function handleSearch(): void {
    router.get(
        appointmentsRoute().url,
        { search: searchQuery.value, perPage: perPage.value },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function handleClearSearch(): void {
    searchQuery.value = '';
    handleSearch();
}

function handlePerPageChange(): void {
    // Reset to the first page when the page size changes.
    router.get(
        appointmentsRoute().url,
        { search: searchQuery.value, perPage: perPage.value },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
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

function formatDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-').map(Number);

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
}

function formatToken(sequence: number): string {
    return sequence.toString().padStart(2, '0');
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

    <div class="page-grid all-appointments">
        <section class="page-card admin-hero">
            <div class="admin-hero__copy">
                <p class="stat-label">Appointments ledger</p>
                <h2 class="admin-hero__title">
                    Inspect and review all historical and pending cases.
                </h2>
                <p class="panel-subtitle admin-hero__subtitle">
                    A paginated history of all appointments registered in the
                    clinic. Use the Action button to load the full clinical
                    records for any appointment, enabling reviews or retrospect
                    updates.
                </p>
            </div>
        </section>

        <section class="admin-inline-stats">
            <article
                v-for="insight in currentPageInsights"
                :key="insight.label"
                class="admin-mini-stat"
            >
                <span class="admin-mini-stat__label">{{ insight.label }}</span>
                <span class="admin-mini-stat__value">{{ insight.value }}</span>
                <span class="admin-mini-stat__note">{{ insight.note }}</span>
            </article>
        </section>

        <section class="page-card admin-table-card">
            <div class="admin-toolbar">
                <div class="admin-toolbar__copy">
                    <h3 class="panel-title">Appointments list</h3>
                    <p class="panel-subtitle">
                        Total records: {{ props.appointments.total }}
                    </p>
                </div>

                <div class="admin-toolbar__actions">
                    <div class="admin-search">
                        <InputText
                            v-model="searchQuery"
                            placeholder="Search patient, token..."
                            class="admin-search__input"
                            @keyup.enter="handleSearch"
                        />
                        <Button
                            icon="pi pi-search"
                            label="Search"
                            size="small"
                            @click="handleSearch"
                        />
                        <Button
                            v-if="searchQuery"
                            icon="pi pi-times"
                            severity="secondary"
                            outlined
                            size="small"
                            @click="handleClearSearch"
                        />
                    </div>
                </div>
            </div>

            <DataTable
                v-if="props.appointments.data.length"
                :value="props.appointments.data"
                class="admin-appointments-table"
                responsive-layout="scroll"
                striped-rows
                row-hover
                data-key="id"
                @row-click="(e) => openDetails(e.data)"
            >
                <Column
                    header="Token / No"
                    style="width: 9rem; min-width: 9rem"
                >
                    <template #body="{ data }">
                        <div class="admin-token-chip">
                            Token {{ formatToken(data.appointmentSequence) }}
                        </div>
                        <div
                            v-if="data.appointmentNumber"
                            class="appointment-muted-copy"
                        >
                            No. {{ data.appointmentNumber }}
                        </div>
                    </template>
                </Column>
                <Column
                    field="appointmentDate"
                    header="Date"
                    sortable
                    style="width: 11rem; min-width: 11rem"
                >
                    <template #body="{ data }">
                        <div class="appointment-date-cell">
                            <i class="pi pi-calendar"></i>
                            <span>{{ formatDate(data.appointmentDate) }}</span>
                        </div>
                    </template>
                </Column>
                <Column
                    field="patientName"
                    header="Patient Name"
                    sortable
                    style="min-width: 18rem"
                >
                    <template #body="{ data }">
                        <div class="admin-person-cell">
                            <Avatar
                                :label="data.patientName.charAt(0)"
                                shape="circle"
                                class="admin-person-cell__avatar appointment-avatar"
                            />
                            <div>
                                <div class="appointment-patient-name">
                                    {{ data.patientName }}
                                </div>
                                <div class="appointment-muted-copy">
                                    ID: #{{ data.patientId }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Column>
                <Column
                    field="appointmentType"
                    header="Visit Type"
                    sortable
                    style="width: 9rem; min-width: 9rem"
                >
                    <template #body="{ data }">
                        <span
                            class="admin-type-badge"
                            :class="`admin-type-badge--${data.appointmentType.toLowerCase().replace(' ', '-')}`"
                        >
                            {{ data.appointmentType }}
                        </span>
                    </template>
                </Column>
                <Column header="Session" style="width: 8rem; min-width: 8rem">
                    <template #body="{ data }">
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
                    </template>
                </Column>
                <Column
                    field="queueStatus"
                    header="Status"
                    sortable
                    style="width: 8rem; min-width: 8rem"
                >
                    <template #body="{ data }">
                        <Tag
                            :value="queueStatusLabel(data.queueStatus)"
                            :severity="queueStatusSeverity(data.queueStatus)"
                        />
                    </template>
                </Column>
                <Column
                    header="Actions"
                    class="text-right"
                    header-class="text-right"
                    style="width: 18rem; min-width: 18rem"
                >
                    <template #body="{ data }">
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <Button
                                icon="pi pi-folder-open"
                                label="Inspect Clinical Case"
                                size="small"
                                outlined
                                @click.stop="openDetails(data)"
                            />
                            <Link
                                v-if="data.queueStatus === 'complete' || data.status?.toLowerCase() === 'complete'"
                                :href="medicineTracking({ query: { selected: data.id } }).url"
                                @click.stop
                            >
                                <Button
                                    icon="pi pi-check-square"
                                    label="Medicine"
                                    severity="success"
                                    size="small"
                                    outlined
                                />
                            </Link>
                        </div>
                    </template>
                </Column>
            </DataTable>

            <div v-else class="admin-empty-state admin-empty-state--soft">
                <i class="pi pi-calendar-times"></i>
                <h3>No appointments registered yet</h3>
                <p>Appointments will appear here once bookings start.</p>
            </div>

            <div
                v-if="props.appointments.data.length"
                class="admin-pagination-container admin-pagination-container--split"
            >
                <div class="admin-pagination-meta">
                    <span class="admin-pagination-summary">
                        Showing
                        <strong>{{ props.appointments.from ?? 0 }}</strong>
                        to
                        <strong>{{ props.appointments.to ?? 0 }}</strong>
                        of
                        <strong>{{ props.appointments.total }}</strong>
                        appointments
                    </span>

                    <label class="admin-pagination-perpage">
                        <span>Rows per page</span>
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
    </div>
</template>

<style scoped>
.all-appointments {
    gap: 1.5rem;
}

.text-right {
    text-align: right;
}

.admin-appointments-table :deep(.p-datatable-tbody > tr) {
    cursor: pointer;
}

.admin-pagination-container--split {
    flex-direction: column;
    gap: 1rem;
}

@media (min-width: 768px) {
    .admin-pagination-container--split {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}

.admin-pagination-meta {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.admin-pagination-summary {
    color: var(--text-color-secondary);
    font-size: 0.85rem;
    font-weight: 600;
}

.admin-pagination-summary strong {
    color: var(--text-color);
}

.admin-pagination-perpage {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-color-secondary);
    font-size: 0.85rem;
    font-weight: 600;
}

.admin-pagination-perpage__select {
    min-width: 5rem;
}

.appointment-date-cell {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    color: var(--text-color);
}

.appointment-avatar {
    width: 2.25rem !important;
    height: 2.25rem !important;
}

.appointment-patient-name {
    font-size: 0.95rem;
    color: var(--text-color);
    font-weight: 600;
}

.appointment-muted-copy {
    color: var(--text-color-secondary);
    font-size: 0.8rem;
    font-weight: 600;
    margin-top: 0.35rem;
}


@media (max-width: 768px) {
    .admin-search {
        width: 100%;
    }

    .admin-search__input {
        flex: 1;
    }
}
</style>
