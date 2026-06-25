<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import AutoComplete from 'primevue/autocomplete';
import { computed, ref, watch } from 'vue';
import AppointmentDetailsDialog from '@/components/AppointmentDetailsDialog.vue';
import InputError from '@/components/InputError.vue';
import { booking, dashboard } from '@/routes';
import { patients as patientsRoute } from '@/routes/admin';
import PatientController from '@/actions/App/Http/Controllers/Admin/PatientController';
import http from '@/lib/http';

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

type PatientCard = {
    id: number;
    patientNumber: string | null;
    name: string;
    email: string | null;
    phone: string | null;
    gender: string | null;
    age: number | null;
    city: string | null;
    dateOfBirth: string | null;
    address: string | null;
    appointments: AppointmentCard[];
    children?: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
    }[];
    parent?: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedPatients = {
    data: PatientCard[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
    total: number;
};

type PatientRegistryFilter = 'all' | 'waiting' | 'today' | 'account_holders';

defineOptions({
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Patients Registry', href: patientsRoute() },
        ],
    },
});

const props = defineProps<{
    patients: PaginatedPatients;
    filters: {
        search: string | null;
    };
}>();

const searchQuery = ref(props.filters.search ?? '');
const selectedPatientId = ref<number | null>(null);

const selectedPatient = computed<PatientCard | null>(() => {
    return (
        props.patients.data.find((p) => p.id === selectedPatientId.value) ??
        null
    );
});

const selectedAppointment = ref<AppointmentCard | null>(null);
const detailDialogVisible = ref(false);
const todayKey = new Intl.DateTimeFormat('en-CA').format(new Date());

const filteredPatients = computed<PatientCard[]>(() => {
    return props.patients.data;
});

// Reset selected patient selection if it gets paginated out
watch(
    filteredPatients,
    (data) => {
        if (!data.length) {
            selectedPatientId.value = null;

            return;
        }

        if (!data.some((patient) => patient.id === selectedPatientId.value)) {
            selectedPatientId.value = data[0].id;
        }
    },
    { immediate: true },
);

function selectPatient(patient: PatientCard): void {
    selectedPatientId.value = patient.id;
}

function selectFamilyMember(child: { id: number; name: string }): void {
    router.get(
        patientsRoute().url,
        { id: child.id },
        {
            preserveState: false,
            preserveScroll: true,
            onSuccess: () => {
                selectedPatientId.value = child.id;
            },
        },
    );
}

function openDetails(appointment: AppointmentCard): void {
    selectedAppointment.value = appointment;
    detailDialogVisible.value = true;
}

function handleSearch(): void {
    router.get(
        patientsRoute().url,
        { search: searchQuery.value },
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

function onDialogSaved(): void {
    router.reload({
        only: ['patients'],
    });
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function formatDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-').map(Number);

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
}

function formatShortDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-').map(Number);

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
    }).format(new Date(year, month - 1, day));
}

function formatToken(sequence: number): string {
    return sequence.toString().padStart(2, '0');
}

function getLatestAppointment(patient: PatientCard): AppointmentCard | null {
    if (!patient.appointments.length) {
        return null;
    }

    return patient.appointments.reduce((latest, appointment) => {
        return appointment.appointmentDate > latest.appointmentDate
            ? appointment
            : latest;
    });
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

function openPhone(phone: string | null): void {
    if (!phone) {
        return;
    }

    window.location.href = `tel:${phone.replace(/\s+/g, '')}`;
}

function openEmail(email: string | null): void {
    if (!email) {
        return;
    }

    window.location.href = `mailto:${email}`;
}

// Join / Unlink Account state and methods
const joinDialogVisible = ref(false);
const selectedDependentPatient = ref<any>(null);
const dependentSuggestions = ref<any[]>([]);
const isSubmittingJoin = ref(false);
const joinError = ref<string | null>(null);

watch(selectedDependentPatient, () => {
    joinError.value = null;
});

function openJoinDialog(): void {
    selectedDependentPatient.value = null;
    dependentSuggestions.value = [];
    joinError.value = null;
    joinDialogVisible.value = true;
}

const searchDependents = async (event: { query: string }) => {
    try {
        const { data } = await http.get(PatientController.search().url, {
            params: { query: event.query },
        });
        dependentSuggestions.value = data;
    } catch (e) {
        console.error(e);
    }
};

function submitJoin(): void {
    if (!selectedDependentPatient.value || typeof selectedDependentPatient.value !== 'object' || !selectedDependentPatient.value.id) {
        joinError.value = 'Please select a valid dependent patient.';
        return;
    }

    if (!selectedPatient.value) {
        return;
    }

    isSubmittingJoin.value = true;
    joinError.value = null;

    router.post(
        PatientController.join(selectedPatient.value.id).url,
        {
            dependent_id: selectedDependentPatient.value.id,
        },
        {
            onSuccess: () => {
                joinDialogVisible.value = false;
                selectedDependentPatient.value = null;
            },
            onError: (errors) => {
                if (errors.dependent_id) {
                    joinError.value = errors.dependent_id;
                } else {
                    joinError.value = 'An error occurred while linking the accounts.';
                }
            },
            onFinish: () => {
                isSubmittingJoin.value = false;
            },
        }
    );
}

function unlinkAccount(patient: { id: number; name: string }): void {
    if (confirm(`Are you sure you want to unlink ${patient.name} from their parent account?`)) {
        router.post(
            PatientController.unlink(patient.id).url,
            {},
            {
                preserveScroll: true,
            }
        );
    }
}
</script>

<template>
    <Head title="Patients Registry" />

    <div class="page-grid patients-workspace">
        <section class="patients-split-view">
            <article class="page-card admin-table-card patients-list-pane">
                <div class="admin-toolbar patient-list-toolbar">
                    <div class="admin-toolbar__copy">
                        <h3 class="panel-title">Patients</h3>
                        <p class="panel-subtitle">
                            Total records: {{ props.patients.total }}
                        </p>
                    </div>

                    <div class="admin-search">
                        <InputText
                            v-model="searchQuery"
                            placeholder="Search by name, ID, or phone..."
                            class="admin-search__input"
                            @keyup.enter="handleSearch"
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

                <div v-if="filteredPatients.length" class="patient-directory-list">
                    <div
                        v-for="patient in filteredPatients"
                        :key="patient.id"
                        class="patient-directory-item"
                        :class="{ 'is-selected': selectedPatientId === patient.id }"
                        @click="selectPatient(patient)"
                    >
                        <div class="patient-directory-item__left">
                            <Avatar
                                :label="getInitials(patient.name)"
                                shape="circle"
                                class="patient-avatar patient-avatar--list"
                            />
                            <div class="patient-directory-item__identity">
                                <span class="patient-directory-item__name">{{ patient.name }}</span>
                                <span class="patient-directory-item__id">ID #{{ patient.id }}</span>
                            </div>
                        </div>
                        <div class="patient-directory-item__right">
                            <div v-if="getLatestAppointment(patient)" class="patient-directory-item__visit">
                                <span class="patient-directory-item__date">
                                    {{ formatShortDate(getLatestAppointment(patient)!.appointmentDate) }}
                                </span>
                                <Tag
                                    :value="queueStatusLabel(getLatestAppointment(patient)!.queueStatus)"
                                    :severity="queueStatusSeverity(getLatestAppointment(patient)!.queueStatus)"
                                    class="patient-directory-item__tag"
                                />
                            </div>
                            <span v-else class="patient-directory-item__no-visits">No visits</span>
                            <i class="pi pi-chevron-right patient-directory-item__chevron"></i>
                        </div>
                    </div>
                </div>

                <div v-else class="admin-empty-state admin-empty-state--soft">
                    <i class="pi pi-users"></i>
                    <h3>No patients found</h3>
                    <p>
                        Try adjusting the search.
                    </p>
                </div>

                <div
                    class="admin-pagination-container patient-pagination-container"
                >
                    <p class="patient-registry-card__summary">
                        Showing {{ filteredPatients.length }} of
                        {{ props.patients.total }}
                    </p>

                    <div
                        v-if="
                            props.patients.links &&
                            props.patients.links.length > 3
                        "
                        class="admin-pagination"
                    >
                        <button
                            v-for="link in props.patients.links"
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

            <div class="patient-detail-stack">
                <article
                    v-if="selectedPatient"
                    class="page-card patient-summary-card"
                >
                    <div class="patient-summary-card__top">
                        <div class="patient-summary-card__identity">
                            <Avatar
                                :label="getInitials(selectedPatient.name)"
                                class="patient-avatar patient-avatar--profile"
                            />

                            <div class="patient-summary-card__copy">
                                <span class="patient-summary-card__eyebrow"
                                    >Selected patient</span
                                >
                                <h2 class="patient-summary-card__name">
                                    {{ selectedPatient.name }}
                                </h2>

                                <div class="patient-summary-card__badges">
                                    <Tag
                                        :value="`ID #${selectedPatient.id}`"
                                        severity="secondary"
                                    />
                                    <Tag
                                        v-if="selectedPatient.children?.length"
                                        value="Account holder"
                                        severity="success"
                                    />
                                    <Tag
                                        v-if="
                                            getLatestAppointment(
                                                selectedPatient,
                                            )
                                        "
                                        :value="
                                            queueStatusLabel(
                                                getLatestAppointment(
                                                    selectedPatient,
                                                )!.queueStatus,
                                            )
                                        "
                                        :severity="
                                            queueStatusSeverity(
                                                getLatestAppointment(
                                                    selectedPatient,
                                                )!.queueStatus,
                                            )
                                        "
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="patient-summary-card__actions">
                            <Button
                                icon="pi pi-phone"
                                label="Call"
                                outlined
                                size="small"
                                :disabled="!selectedPatient.phone"
                                @click="openPhone(selectedPatient.phone)"
                            />
                            <Button
                                icon="pi pi-envelope"
                                label="Message"
                                outlined
                                size="small"
                                :disabled="!selectedPatient.email"
                                @click="openEmail(selectedPatient.email)"
                            />
                        </div>
                    </div>

                    <div class="admin-detail-grid">
                        <div class="admin-detail-item">
                            <span>
                                <i class="pi pi-envelope"></i>
                            </span>
                            <div>
                                Email
                                <strong class="patient-contact-value">
                                    {{
                                        selectedPatient.email ?? 'Not provided'
                                    }}
                                </strong>
                            </div>
                        </div>
                        <div class="admin-detail-item">
                            <span>
                                <i class="pi pi-phone"></i>
                            </span>
                            <div>
                                Phone
                                <strong class="patient-contact-value">
                                    {{
                                        selectedPatient.phone ?? 'Not provided'
                                    }}
                                </strong>
                            </div>
                        </div>
                        <div class="admin-detail-item">
                            <span>
                                <i class="pi pi-calendar"></i>
                            </span>
                            <div>
                                Date of birth
                                <strong class="patient-contact-value">
                                    {{
                                        selectedPatient.dateOfBirth
                                            ? formatDate(
                                                  selectedPatient.dateOfBirth,
                                              )
                                            : 'Not provided'
                                    }}
                                    <span v-if="selectedPatient.age !== null">
                                        · {{ selectedPatient.age }}y
                                    </span>
                                </strong>
                            </div>
                        </div>
                        <div class="admin-detail-item">
                            <span>
                                <i class="pi pi-user"></i>
                            </span>
                            <div>
                                Sex
                                <strong class="patient-contact-value">
                                    {{
                                        selectedPatient.gender ?? 'Not provided'
                                    }}
                                </strong>
                            </div>
                        </div>
                    </div>
                </article>

                <article
                    v-if="selectedPatient"
                    class="page-card patient-family-card"
                >
                    <div class="patient-block-header">
                        <div class="patient-block-header__title">
                            <h3 class="panel-title">Family group</h3>
                            <Tag
                                :value="
                                    (() => {
                                        const count = (selectedPatient.parent ? 1 : 0) + (selectedPatient.children?.length ?? 0);
                                        return count === 0 ? 'No members' : count === 1 ? '1 member' : `${count} members`;
                                    })()
                                "
                                severity="secondary"
                            />
                        </div>
                        <div v-if="!selectedPatient.parent" class="patient-block-header__actions">
                            <Button
                                label="Add Dependent"
                                icon="pi pi-plus"
                                size="small"
                                outlined
                                class="flat-btn"
                                @click="openJoinDialog"
                            />
                        </div>
                    </div>

                    <div
                        v-if="selectedPatient.parent || selectedPatient.children?.length"
                        class="patient-family-grid"
                    >
                        <!-- Parent/Account Holder Card -->
                        <div
                            v-if="selectedPatient.parent"
                            class="patient-family-member"
                            @click="selectFamilyMember(selectedPatient.parent)"
                        >
                            <Avatar
                                :label="getInitials(selectedPatient.parent.name)"
                                class="patient-avatar patient-avatar--table"
                            />
                            <div class="patient-family-member__copy">
                                <div class="patient-family-member__title-row">
                                    <strong class="patient-family-member__name">
                                        {{ selectedPatient.parent.name }}
                                    </strong>
                                    <span class="patient-family-member__id">
                                        #{{ selectedPatient.parent.id }}
                                    </span>
                                    <Tag value="Account holder" severity="info" outlined class="patient-family-tag" />
                                </div>
                                <div class="patient-family-member__meta">
                                    <i class="pi pi-envelope"></i>
                                    {{ selectedPatient.parent.email ?? 'No email' }}
                                </div>
                                <div
                                    v-if="selectedPatient.parent.phone"
                                    class="patient-family-member__meta"
                                >
                                    <i class="pi pi-phone"></i>
                                    {{ selectedPatient.parent.phone }}
                                </div>
                            </div>
                            <Button
                                icon="pi pi-user-minus"
                                severity="danger"
                                text
                                rounded
                                size="small"
                                class="patient-family-member__unlink-btn"
                                title="Unlink parent"
                                @click.stop="unlinkAccount(selectedPatient)"
                            />
                        </div>

                        <!-- Children/Dependents Cards -->
                        <div
                            v-for="child in selectedPatient.children"
                            :key="child.id"
                            class="patient-family-member"
                            @click="selectFamilyMember(child)"
                        >
                            <Avatar
                                :label="getInitials(child.name)"
                                class="patient-avatar patient-avatar--table"
                            />
                            <div class="patient-family-member__copy">
                                <div class="patient-family-member__title-row">
                                    <strong class="patient-family-member__name">
                                        {{ child.name }}
                                    </strong>
                                    <span class="patient-family-member__id">
                                        #{{ child.id }}
                                    </span>
                                    <Tag value="Dependent" severity="success" outlined class="patient-family-tag" />
                                </div>
                                <div class="patient-family-member__meta">
                                    <i class="pi pi-envelope"></i>
                                    {{ child.email ?? 'No email' }}
                                </div>
                                <div
                                    v-if="child.phone"
                                    class="patient-family-member__meta"
                                >
                                    <i class="pi pi-phone"></i>
                                    {{ child.phone }}
                                </div>
                            </div>
                            <Button
                                icon="pi pi-user-minus"
                                severity="danger"
                                text
                                rounded
                                size="small"
                                class="patient-family-member__unlink-btn"
                                title="Unlink dependent"
                                @click.stop="unlinkAccount(child)"
                            />
                        </div>
                    </div>

                    <div
                        v-else
                        class="admin-empty-state admin-empty-state--soft patient-family-empty"
                    >
                        <i class="pi pi-users"></i>
                        <h3>No family members linked</h3>
                        <p>
                            This patient does not have any dependents attached
                            yet.
                        </p>
                    </div>
                </article>

                <article
                    v-if="selectedPatient"
                    class="page-card admin-table-card patient-appointments-card"
                >
                    <div class="admin-toolbar">
                        <div class="patient-block-header__title">
                            <h3 class="panel-title">Appointments</h3>
                            <Tag
                                :value="`${selectedPatient.appointments.length} total`"
                                severity="secondary"
                            />
                        </div>

                        <span class="patient-muted-copy">All statuses</span>
                    </div>

                    <DataTable
                        v-if="selectedPatient.appointments.length"
                        :value="selectedPatient.appointments"
                        class="patient-appointments-table"
                        row-hover
                        responsive-layout="scroll"
                        @row-click="(e) => openDetails(e.data)"
                    >
                        <Column
                            header="Token"
                            style="width: 7rem; min-width: 7rem"
                        >
                            <template #body="{ data }">
                                <div class="patient-token-pill">
                                    T-{{
                                        formatToken(data.appointmentSequence)
                                    }}
                                </div>
                            </template>
                        </Column>
                        <Column
                            header="Date"
                            style="width: 10rem; min-width: 10rem"
                        >
                            <template #body="{ data }">
                                <div class="appointment-date-cell">
                                    <i class="pi pi-calendar"></i>
                                    <span>{{
                                        formatShortDate(data.appointmentDate)
                                    }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column
                            header="Session / Type"
                            style="min-width: 13rem"
                        >
                            <template #body="{ data }">
                                <div
                                    class="admin-session-badge"
                                    :class="`admin-session-badge--${data.session.toLowerCase()}`"
                                >
                                    <i
                                        :class="
                                            data.session.toLowerCase() ===
                                            'morning'
                                                ? 'pi pi-sun'
                                                : 'pi pi-moon'
                                        "
                                    ></i>
                                    <span>{{ data.session }}</span>
                                </div>
                                <div class="patient-muted-copy">
                                    {{ data.appointmentType }}
                                </div>
                            </template>
                        </Column>
                        <Column
                            header="Status"
                            style="width: 8rem; min-width: 8rem"
                        >
                            <template #body="{ data }">
                                <Tag
                                    :value="queueStatusLabel(data.queueStatus)"
                                    :severity="
                                        queueStatusSeverity(data.queueStatus)
                                    "
                                />
                            </template>
                        </Column>
                        <Column
                            header="Open"
                            class="text-right"
                            header-class="text-right"
                            style="width: 5rem; min-width: 5rem"
                        >
                            <template #body="{ data }">
                                <Button
                                    icon="pi pi-folder-open"
                                    outlined
                                    size="small"
                                    @click="openDetails(data)"
                                />
                            </template>
                        </Column>
                    </DataTable>

                    <div
                        v-else
                        class="admin-empty-state admin-empty-state--soft"
                    >
                        <i class="pi pi-calendar-times"></i>
                        <h3>No appointment records</h3>
                        <p>This patient has not booked any appointments yet.</p>
                    </div>
                </article>

                <div
                    v-else
                    class="page-card admin-empty-state admin-empty-state--soft admin-selection-placeholder selection-placeholder"
                >
                    <i class="pi pi-id-card"></i>
                    <h3>No patient selected</h3>
                    <p>
                        Choose a patient from the registry to load their
                        profile, family group, and appointment history.
                    </p>
                </div>
            </div>
        </section>

        <AppointmentDetailsDialog
            v-model:visible="detailDialogVisible"
            :appointment="selectedAppointment"
            @saved="onDialogSaved"
        />

        <Dialog
            v-model:visible="joinDialogVisible"
            modal
            header="Add Dependent"
            :style="{ width: '450px' }"
            class="patient-join-dialog"
        >
            <div class="join-dialog-content">
                <p class="dialog-description">
                    Link another patient under <strong>{{ selectedPatient?.name }}</strong>'s account as a dependent. The selected patient will become a dependent of <strong>{{ selectedPatient?.name }}</strong>.
                </p>

                <div class="field">
                    <label for="dependent_search" class="dialog-field-label">Select Dependent Account</label>
                    <AutoComplete
                        id="dependent_search"
                        v-model="selectedDependentPatient"
                        :suggestions="dependentSuggestions"
                        option-label="label"
                        placeholder="Search by name, ID, or phone..."
                        fluid
                        @complete="searchDependents"
                    />
                    <InputError :message="joinError ?? undefined" class="join-error-msg" />
                </div>

                <div v-if="selectedDependentPatient && typeof selectedDependentPatient === 'object' && selectedDependentPatient.id" class="parent-details-card">
                    <div class="parent-details-header">
                        <i class="pi pi-user-check"></i>
                        <span>Selected Dependent Account</span>
                    </div>
                    <div class="parent-details-body">
                        <div><strong>Name:</strong> {{ selectedDependentPatient.name }}</div>
                        <div><strong>ID:</strong> #{{ selectedDependentPatient.id }}</div>
                        <div><strong>Phone:</strong> {{ selectedDependentPatient.phone || 'N/A' }}</div>
                        <div><strong>Email:</strong> {{ selectedDependentPatient.email || 'N/A' }}</div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="patient-join-dialog-actions">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        size="small"
                        @click="joinDialogVisible = false"
                    />
                    <Button
                        label="Add Dependent"
                        icon="pi pi-plus"
                        severity="primary"
                        size="small"
                        :loading="isSubmittingJoin"
                        :disabled="!selectedDependentPatient || typeof selectedDependentPatient !== 'object' || !selectedDependentPatient.id"
                        @click="submitJoin"
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.patients-split-view {
    display: grid;
    grid-template-columns: minmax(21rem, 0.9fr) minmax(0, 1.35fr);
    gap: 1rem;
    align-items: start;
}

.patients-list-pane,
.patient-appointments-card {
    padding: 0;
    overflow: hidden;
}

.patient-directory-table {
    cursor: pointer;
}

.patient-directory-row,
.patient-summary-card__identity,
.patient-family-member {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.patient-directory-row__copy,
.patient-summary-card__copy,
.patient-family-member__copy {
    display: grid;
    gap: 0.25rem;
    min-width: 0;
}

.patient-directory-row__name,
.patient-summary-card__name,
.patient-family-member__name {
    color: var(--text-color);
    font-weight: 700;
}

.patient-directory-row__meta,
.patient-muted-copy,
.patient-family-member__meta,
.patient-registry-card__summary,
.patient-summary-card__eyebrow {
    color: var(--text-secondary-color);
    font-size: 0.84rem;
}

.patient-avatar {
    background: var(--primary-color) !important;
    color: var(--primary-contrast-color) !important;
    font-weight: 700;
    flex-shrink: 0;
}

.patient-avatar--table {
    width: 2.25rem !important;
    height: 2.25rem !important;
}

.patient-avatar--profile {
    width: 3.25rem !important;
    height: 3.25rem !important;
}

.patient-detail-stack {
    display: grid;
    gap: 1rem;
}

.patient-summary-card,
.patient-family-card {
    display: grid;
    gap: 1rem;
}

.patient-summary-card__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.patient-block-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.patient-block-header__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.patient-summary-card__name {
    margin: 0;
    font-size: 1.35rem;
}

.patient-summary-card__badges,
.patient-summary-card__actions,
.patient-family-member__meta,
.patient-filter-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.patient-action-link {
    display: inline-flex;
}

.patient-contact-value {
    display: block;
    margin-top: 0.2rem;
    color: var(--text-color);
    font-weight: 700;
    word-break: break-word;
}

.patient-family-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.patient-family-member {
    align-items: flex-start;
    padding: 1rem 1.15rem;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
    cursor: pointer;
    transition: all 0.25s ease;
}

.patient-family-member:hover {
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
}

.patient-family-member__id {
    font-size: 0.82rem;
    color: var(--text-secondary-color);
    font-weight: 600;
}

.patient-family-tag {
    font-size: 0.72rem !important;
    padding: 0.15rem 0.45rem !important;
    font-weight: 700 !important;
}

.patient-family-member__title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.patient-last-visit {
    display: grid;
    justify-items: end;
    gap: 0.35rem;
}

.patient-token-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem 0.6rem;
    border: 1px solid color-mix(in srgb, var(--primary-color) 24%, transparent);
    background: color-mix(
        in srgb,
        var(--primary-color) 10%,
        var(--surface-card)
    );
    color: var(--primary-color);
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 700;
}

.appointment-date-cell {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--text-color);
    font-weight: 600;
}

.patient-pagination-container {
    justify-content: space-between;
    gap: 1rem;
}

.text-right {
    text-align: right;
}

.patient-directory-list {
    display: flex;
    flex-direction: column;
    background: var(--surface-card);
}

.patient-directory-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.15rem 1.25rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.08);
    cursor: pointer;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.patient-directory-item:hover {
    background: var(--surface-hover);
}

.patient-directory-item.is-selected {
    background: color-mix(in srgb, var(--primary-color) 6%, var(--surface-card));
    border-left-color: var(--primary-color);
}

.patient-directory-item__left {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 0;
}

.patient-avatar--list {
    width: 2.5rem !important;
    height: 2.5rem !important;
    background: var(--primary-color) !important;
    color: var(--primary-contrast-color) !important;
    font-weight: 700;
}

.patient-directory-item__identity {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.patient-directory-item__name {
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--text-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.patient-directory-item__id {
    font-size: 0.82rem;
    color: var(--text-secondary-color);
    font-weight: 600;
}

.patient-directory-item__right {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.patient-directory-item__visit {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}

.patient-directory-item__date {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--text-color);
}

.patient-directory-item__tag {
    font-size: 0.72rem;
    padding: 0.15rem 0.45rem;
    font-weight: 700;
}

.patient-directory-item__no-visits {
    font-size: 0.82rem;
    color: var(--text-secondary-color);
    font-style: italic;
}

.patient-directory-item__chevron {
    font-size: 0.85rem;
    color: var(--text-secondary-color);
    transition: transform 0.2s ease;
}

.patient-directory-item:hover .patient-directory-item__chevron {
    transform: translateX(3px);
    color: var(--primary-color);
}

@media (max-width: 1024px) {
    .patients-split-view {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .patient-summary-card__top,
    .patient-block-header,
    .patient-pagination-container {
        flex-direction: column;
        align-items: stretch;
    }

    .patient-family-grid {
        grid-template-columns: 1fr;
    }

    .admin-search__input {
        flex: 1;
    }
}

.patient-list-toolbar {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
}

.patient-list-toolbar .admin-search {
    width: 100%;
    display: flex;
    gap: 0.5rem;
    flex-wrap: nowrap;
}

.patient-list-toolbar .admin-search__input {
    flex: 1;
    width: auto;
}

.patient-family-empty {
    min-height: 10rem !important;
    padding: 1.5rem 1rem !important;
    gap: 0.5rem;
}

.patient-family-empty i {
    font-size: 1.8rem !important;
}

.patient-family-empty h3 {
    font-size: 1rem !important;
}

.patient-family-empty p {
    font-size: 0.85rem !important;
    max-width: 20rem;
}

.patient-appointments-table :deep(.p-datatable-tbody > tr) {
    cursor: pointer;
}

.patient-family-member__copy {
    flex-grow: 1;
}

.patient-family-member__unlink-btn {
    align-self: center;
    flex-shrink: 0;
    margin-left: 0.5rem;
    width: 2.25rem !important;
    height: 2.25rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 50% !important;
    color: #ef4444 !important;
    background: transparent !important;
    transition: all 0.2s ease-in-out !important;
}

.patient-family-member__unlink-btn:hover {
    background: rgba(239, 68, 68, 0.08) !important;
    color: #dc2626 !important;
    transform: scale(1.08);
}

.patient-block-header__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.parent-details-card {
    padding: 1rem;
    border-radius: var(--content-border-radius, 6px);
    background: rgba(12, 110, 114, 0.05);
    border: 1px solid rgba(12, 110, 114, 0.15);
    margin-top: 1rem;
}

.parent-details-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: #0c6e72;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.parent-details-body {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
    gap: 0.5rem;
    font-size: 0.88rem;
    color: #10242e;
}

.patient-join-dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.25rem;
}

.patient-join-dialog-actions :deep(.p-button) {
    width: auto;
    white-space: nowrap;
}

.flat-btn {
    width: auto !important;
    white-space: nowrap !important;
}

.join-dialog-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.join-dialog-content .field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.dialog-description {
    font-size: 0.875rem;
    color: var(--text-secondary-color);
    line-height: 1.5;
}

.dialog-field-label {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-color);
}

.join-error-msg {
    margin-top: 0.25rem;
}

.join-dialog-content :deep(.p-autocomplete),
.join-dialog-content :deep(.p-autocomplete-input) {
    width: 100%;
}
</style>
