<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Card from 'primevue/card';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';
import {
    toggleHold as toggleHoldAction,
} from '@/actions/App/Http/Controllers/Admin/CurrentAppointmentController';
import http, { pushToast } from '@/lib/http';
import { booking, dashboard } from '@/routes';
import AppointmentDetailsDialog from '@/components/AppointmentDetailsDialog.vue';

type Session = 'Morning' | 'Evening';

const SESSIONS: readonly Session[] = ['Morning', 'Evening'];

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Current Appointments', href: booking() },
        ],
    },
});

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

type Summary = {
    total: number;
    running: number;
    pending: number;
    onHold: number;
    complete: number;
};

const props = defineProps<{
    appointmentDate: string;
    today: string;
    appointments: AppointmentCard[];
    currentAppointmentIds: Record<string, number | null>;
    summary: Summary;
}>();

function defaultSession(): Session {
    return (
        SESSIONS.find((session) =>
            props.appointments.some(
                (appointment) => appointment.session === session,
            ),
        ) ?? 'Morning'
    );
}

const activeSession = ref<Session>(defaultSession());

const sessionOptions = computed(() =>
    SESSIONS.map((session) => ({
        value: session,
        label: `${session} (${
            props.appointments.filter(
                (appointment) => appointment.session === session,
            ).length
        })`,
    })),
);

const sessionAppointments = computed<AppointmentCard[]>(() =>
    props.appointments.filter(
        (appointment) => appointment.session === activeSession.value,
    ),
);

const queueAppointments = computed<AppointmentCard[]>(() =>
    sessionAppointments.value.filter(
        (appointment) =>
            !appointment.onHold && appointment.queueStatus !== 'complete',
    ),
);

const heldAppointments = computed<AppointmentCard[]>(() =>
    sessionAppointments.value
        .filter(
            (appointment) =>
                appointment.onHold && appointment.queueStatus !== 'complete',
        )
        .slice()
        .sort((a, b) => (a.holdOrder ?? 0) - (b.holdOrder ?? 0)),
);

const selectedAppointmentId = ref<number | null>(null);
const detailDialogVisible = ref(false);

const currentAppointment = computed<AppointmentCard | null>(() => {
    const currentId = props.currentAppointmentIds[activeSession.value] ?? null;

    if (currentId === null) {
        return null;
    }

    return (
        props.appointments.find(
            (appointment) => appointment.id === currentId,
        ) ?? null
    );
});

watch(
    () => props.appointments,
    (appointments) => {
        if (!appointments.some((a) => a.session === activeSession.value)) {
            activeSession.value = defaultSession();
        }

        if (
            selectedAppointmentId.value !== null &&
            !appointments.some((a) => a.id === selectedAppointmentId.value)
        ) {
            selectedAppointmentId.value = null;
            detailDialogVisible.value = false;
        }
    },
);

const selectedAppointment = computed<AppointmentCard | null>(() => {
    if (selectedAppointmentId.value === null) {
        return currentAppointment.value;
    }

    return (
        props.appointments.find(
            (appointment) => appointment.id === selectedAppointmentId.value,
        ) ?? currentAppointment.value
    );
});

function parseDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
}

const appointmentDateLabel = computed(() =>
    new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'long',
        weekday: 'long',
    }).format(parseDate(props.appointmentDate)),
);

const toast = useToast();

const summaryCards = computed(() => [
    {
        label: 'Today total',
        value: props.summary.total,
        note: 'Booked for this clinic day',
        accent: 'var(--p-primary-500)',
    },
    {
        label: 'Running now',
        value: props.summary.running,
        note: 'Actively moving through queue',
        accent: 'var(--p-green-500)',
    },
    {
        label: 'Waiting',
        value: props.summary.pending,
        note: 'Ready after the current case',
        accent: 'var(--p-orange-500)',
    },
    {
        label: 'On hold',
        value: props.summary.onHold,
        note: 'Paused via the Hold button',
        accent: 'var(--p-red-400)',
    },
]);

const openDetails = (appointment: AppointmentCard): void => {
    selectedAppointmentId.value = appointment.id;
    detailDialogVisible.value = true;
};

const toggleHold = async (appointment: AppointmentCard): Promise<void> => {
    try {
        const { data } = await http.patch(
            toggleHoldAction(appointment.id).url,
            {
                on_hold: !appointment.onHold,
            },
        );

        pushToast(toast, data.toast);
        router.reload({
            only: ['appointments', 'summary', 'currentAppointmentIds'],
        });
    } catch {
        pushToast(toast, {
            type: 'error',
            message: 'Could not update hold status. Please try again.',
        });
    }
};

function formatToken(sequence: number): string {
    return sequence.toString().padStart(2, '0');
}

function formatValue(value: string | null): string {
    if (!value) {
        return 'Not added yet';
    }
    return value;
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

function onDialogSaved(): void {
    router.reload({
        only: ['appointments', 'summary', 'currentAppointmentIds'],
    });
}
</script>

<template>
    <Head title="Current Appointments" />

    <div class="page-grid appointment-desk">
        <section class="page-card booking-hero">
            <div>
                <p class="stat-label">Booking desk</p>
                <h2 class="panel-title booking-hero__title">
                    Current appointments for {{ appointmentDateLabel }}
                </h2>
                <p class="panel-subtitle booking-hero__subtitle">
                    Double-click any appointment card—or use “View full
                    details”—to open the complete patient case in a pop-up. Use
                    the Hold button to pause a slower case without leaving the
                    live queue.
                </p>
            </div>

            <Card class="booking-current-card">
                <template #content>
                    <div v-if="currentAppointment" class="booking-current">
                        <div class="booking-current__top">
                            <div class="booking-token-pill">
                                Token
                                {{
                                    formatToken(
                                        currentAppointment.appointmentSequence,
                                    )
                                }}
                            </div>

                            <Tag
                                :value="
                                    queueStatusLabel(
                                        currentAppointment.queueStatus,
                                    )
                                "
                                :severity="
                                    queueStatusSeverity(
                                        currentAppointment.queueStatus,
                                    )
                                "
                            />
                        </div>

                        <div class="booking-current__identity">
                            <Avatar
                                :label="
                                    currentAppointment.patientName.charAt(0)
                                "
                                shape="circle"
                                size="large"
                            />

                            <div>
                                <h3>{{ currentAppointment.patientName }}</h3>
                                <p>
                                    {{ currentAppointment.session }} session
                                    <span
                                        v-if="currentAppointment.age !== null"
                                    >
                                        · {{ currentAppointment.age }} yrs
                                    </span>
                                    <span v-if="currentAppointment.gender">
                                        · {{ currentAppointment.gender }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <p class="booking-current__reason">
                            {{ formatValue(currentAppointment.reasonForVisit) }}
                        </p>

                        <div class="booking-meta-row">
                            <span v-if="currentAppointment.phone" title="Phone">
                                <i class="pi pi-phone mr-1"></i>
                                {{ currentAppointment.phone }}
                            </span>
                            <span v-if="currentAppointment.city" title="Location">
                                <i class="pi pi-map-marker mr-1"></i>
                                {{ currentAppointment.city }}
                            </span>
                            <span v-if="currentAppointment.patientNumber" title="Patient ID">
                                <i class="pi pi-id-card mr-1"></i>
                                {{ currentAppointment.patientNumber }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-else
                        class="booking-empty-state booking-empty-state--soft"
                    >
                        <i class="pi pi-calendar-times"></i>
                        <h3>No live appointment right now</h3>
                        <p>
                            Today’s queue is empty. As soon as patients are
                            booked for this date, they’ll appear here.
                        </p>
                    </div>
                </template>
            </Card>
        </section>

        <section class="booking-summary-grid">
            <article
                v-for="card in summaryCards"
                :key="card.label"
                class="page-card booking-summary-card"
            >
                <span class="stat-label">{{ card.label }}</span>
                <strong :style="{ color: card.accent }">{{
                    card.value
                }}</strong>
                <p>{{ card.note }}</p>
            </article>
        </section>

        <div class="admin-section-header booking-session-bar">
            <div>
                <h3 class="panel-title">Today’s live queue</h3>
                <p class="panel-subtitle">
                    Switch between morning and evening. Double-click a card for
                    details; use Hold to move a card to the hold column.
                </p>
            </div>

            <SelectButton
                v-model="activeSession"
                :options="sessionOptions"
                option-label="label"
                option-value="value"
                :allow-empty="false"
                aria-label="Session"
            />
        </div>

        <section class="booking-workspace">
            <article class="page-card booking-queue-shell">
                <div class="admin-section-header">
                    <div>
                        <h3 class="panel-title">{{ activeSession }} queue</h3>
                        <p class="panel-subtitle">
                            Active cases moving through the {{ activeSession }}
                            session.
                        </p>
                    </div>

                    <Tag
                        :value="`${queueAppointments.length} active`"
                        severity="secondary"
                    />
                </div>

                <div v-if="queueAppointments.length" class="booking-queue-list">
                    <article
                        v-for="appointment in queueAppointments"
                        :key="appointment.id"
                        class="booking-queue-card"
                        :class="{
                            'is-current': appointment.queueStatus === 'running',
                            'is-selected':
                                selectedAppointment?.id === appointment.id,
                        }"
                        @dblclick="openDetails(appointment)"
                    >
                        <div class="booking-queue-card__top">
                            <div>
                                <div class="booking-token-line">
                                    Token
                                    {{
                                        formatToken(
                                            appointment.appointmentSequence,
                                        )
                                    }}
                                    <span v-if="appointment.appointmentNumber">
                                        · No.
                                        {{ appointment.appointmentNumber }}
                                    </span>
                                </div>
                                <h4>{{ appointment.patientName }}</h4>
                                <p>
                                    {{
                                        formatValue(appointment.reasonForVisit)
                                    }}
                                </p>
                            </div>

                            <Tag
                                :value="
                                    queueStatusLabel(appointment.queueStatus)
                                "
                                :severity="
                                    queueStatusSeverity(appointment.queueStatus)
                                "
                            />
                        </div>

                        <div class="booking-meta-row booking-meta-row--cards">
                            <span>
                                <i class="pi pi-calendar mr-1"></i>
                                {{ appointment.session }}
                            </span>
                            <span>
                                <i class="pi pi-user mr-1"></i>
                                {{ appointment.appointmentType }}
                            </span>
                            <span v-if="appointment.phone" title="Phone">
                                <i class="pi pi-phone mr-1"></i>
                                {{ appointment.phone }}
                            </span>
                            <span v-if="appointment.city" title="Location">
                                <i class="pi pi-map-marker mr-1"></i>
                                {{ appointment.city }}
                            </span>
                        </div>

                        <div class="booking-queue-card__actions">
                            <Button
                                text
                                size="small"
                                icon="pi pi-eye"
                                label="View full details"
                                @click.stop="openDetails(appointment)"
                            />

                            <Button
                                text
                                size="small"
                                icon="pi pi-pause"
                                label="Put on Hold"
                                @click.stop="toggleHold(appointment)"
                            />
                        </div>
                    </article>
                </div>

                <div v-else class="booking-empty-state">
                    <i class="pi pi-calendar-times"></i>
                    <h3>No active {{ activeSession.toLowerCase() }} cases</h3>
                    <p>
                        Booked {{ activeSession.toLowerCase() }} appointments
                        that are not on hold will show up here.
                    </p>
                </div>
            </article>

            <article class="page-card booking-hold-shell">
                <div class="admin-section-header">
                    <div>
                        <h3 class="panel-title">On hold</h3>
                        <p class="panel-subtitle">
                            Cases paused from the {{ activeSession }} queue.
                        </p>
                    </div>

                    <Tag
                        :value="`${heldAppointments.length} held`"
                        severity="danger"
                    />
                </div>

                <div v-if="heldAppointments.length" class="booking-queue-list">
                    <article
                        v-for="appointment in heldAppointments"
                        :key="appointment.id"
                        class="booking-queue-card is-held"
                        :class="{
                            'is-selected':
                                selectedAppointment?.id === appointment.id,
                        }"
                        @dblclick="openDetails(appointment)"
                    >
                        <div class="booking-queue-card__top">
                            <div>
                                <div class="booking-token-line">
                                    Token
                                    {{
                                        formatToken(
                                            appointment.appointmentSequence,
                                        )
                                    }}
                                    <span v-if="appointment.appointmentNumber">
                                        · No.
                                        {{ appointment.appointmentNumber }}
                                    </span>
                                </div>
                                <h4>{{ appointment.patientName }}</h4>
                                <p>
                                    {{
                                        formatValue(appointment.reasonForVisit)
                                    }}
                                </p>
                            </div>

                            <Tag value="On Hold" severity="danger" />
                        </div>

                        <div class="booking-meta-row booking-meta-row--cards">
                            <span>
                                <i class="pi pi-calendar mr-1"></i>
                                {{ appointment.session }}
                            </span>
                            <span>
                                <i class="pi pi-user mr-1"></i>
                                {{ appointment.appointmentType }}
                            </span>
                            <span v-if="appointment.phone" title="Phone">
                                <i class="pi pi-phone mr-1"></i>
                                {{ appointment.phone }}
                            </span>
                        </div>

                        <div class="booking-queue-card__actions">
                            <Button
                                text
                                size="small"
                                icon="pi pi-eye"
                                label="View full details"
                                @click.stop="openDetails(appointment)"
                            />

                            <Button
                                text
                                size="small"
                                icon="pi pi-play"
                                label="Release Hold"
                                @click.stop="toggleHold(appointment)"
                            />
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="booking-empty-state booking-empty-state--soft"
                >
                    <i class="pi pi-pause-circle"></i>
                    <h3>Nothing on hold</h3>
                    <p>
                        When you put a {{ activeSession.toLowerCase() }} case on
                        hold, it moves here.
                    </p>
                </div>
            </article>
        </section>

        <AppointmentDetailsDialog
            v-model:visible="detailDialogVisible"
            :appointment="selectedAppointment"
            @saved="onDialogSaved"
        />
    </div>
</template>

<style scoped>
.appointment-desk {
    gap: 1.5rem;
}


.booking-hero {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: minmax(0, 1.35fr) minmax(21rem, 0.95fr);
    align-items: stretch;
}

.booking-hero__title {
    font-size: 1.8rem;
}

.booking-hero__subtitle {
    margin-top: 0.6rem;
    max-width: 48rem;
    line-height: 1.6;
}

.booking-current-card :deep(.p-card-content) {
    padding: 0;
}

.booking-current {
    display: grid;
    gap: 1rem;
}

.booking-current__top,
.booking-queue-card__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.booking-token-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.45rem 0.8rem;
    border-radius: 9999px;
    background: color-mix(in srgb, var(--p-primary-500) 14%, white);
    color: var(--p-primary-700);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.booking-current__identity {
    display: flex;
    align-items: center;
    gap: 0.9rem;
}

.booking-current__identity :deep(.p-avatar) {
    width: 3rem !important;
    height: 3rem !important;
    flex-shrink: 0 !important;
    background: var(--p-primary-500) !important;
    color: white !important;
}

.booking-current__identity h3,
.booking-queue-card__top h4 {
    margin: 0;
    font-size: 1.2rem;
}

.booking-current__identity p,
.booking-queue-card__top p,
.booking-summary-card p,
.booking-empty-state p {
    margin: 0.3rem 0 0;
    color: var(--text-color-secondary);
    line-height: 1.5;
}

.booking-current__reason {
    margin: 0;
    padding: 0.95rem 1rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-50);
    line-height: 1.6;
}

.booking-summary-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.booking-summary-card {
    display: grid;
    gap: 0.55rem;
}

.booking-summary-card strong {
    font-size: 2rem;
    line-height: 1;
}

.booking-session-bar {
    margin-bottom: -0.25rem;
}

.booking-workspace {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: minmax(0, 1.35fr) minmax(20rem, 0.65fr);
    align-items: start;
}

.booking-queue-shell,
.booking-hold-shell {
    display: grid;
    gap: 1.25rem;
}

.booking-hold-shell {
    position: sticky;
    top: 1.5rem;
}

.booking-queue-list {
    display: grid;
    gap: 1rem;
}

.booking-queue-card {
    padding: 1rem;
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    background: var(--surface-card);
    cursor: pointer;
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease;
}

.booking-queue-card:hover {
    border-color: var(--p-primary-400);
    box-shadow: 0 12px 24px -22px rgba(15, 23, 42, 0.45);
}

.booking-queue-card.is-current {
    border-color: var(--p-primary-500);
    border-left: 3px solid var(--p-primary-500);
    background: color-mix(in srgb, var(--p-primary-50) 25%, var(--surface-card));
}

.booking-queue-card.is-held {
    border-style: dashed;
    border-color: color-mix(
        in srgb,
        var(--p-red-400) 58%,
        var(--surface-border)
    );
    background: color-mix(in srgb, var(--p-red-50) 45%, var(--surface-card));
}

.booking-queue-card.is-selected {
    outline: 2px solid color-mix(in srgb, var(--p-primary-500) 35%, transparent);
    outline-offset: 2px;
}

.booking-token-line {
    color: var(--text-color-secondary);
    font-size: 0.83rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.booking-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
}

.booking-meta-row span {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.7rem;
    border-radius: 6px;
    background: var(--surface-100);
    color: var(--text-color-secondary);
    font-size: 0.85rem;
}

.booking-meta-row span i {
    color: var(--p-primary-500);
}

.booking-meta-row--cards {
    margin-top: 0.9rem;
}

.booking-queue-card__actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 1rem;
}

.booking-empty-state {
    display: grid;
    place-items: center;
    gap: 0.75rem;
    padding: 2.2rem 1.2rem;
    text-align: center;
}

.booking-empty-state--soft {
    min-height: 15rem;
}

.booking-empty-state i {
    font-size: 1.5rem;
    color: var(--text-color-secondary);
}

.booking-empty-state h3 {
    margin: 0;
}

@media (max-width: 1140px) {
    .booking-hero,
    .booking-workspace {
        grid-template-columns: 1fr;
    }

    .booking-hold-shell {
        position: static;
    }
}

@media (max-width: 840px) {
    .booking-summary-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 640px) {
    .booking-summary-grid {
        grid-template-columns: 1fr;
    }

    .booking-current__top,
    .booking-queue-card__top,
    .booking-queue-card__actions {
        flex-direction: column;
        align-items: stretch;
    }

    .booking-queue-card__actions {
        gap: 0.5rem;
    }
}
</style>
