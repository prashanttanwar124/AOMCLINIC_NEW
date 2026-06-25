<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { create as bookAppointment } from '@/actions/App/Http/Controllers/Patient/PatientAppointmentController';
import { liveStatus } from '@/routes/patient';

type AppointmentCard = {
    date: string | null;
    number: string | null;
    reason: string | null;
    session: string;
    status: string;
    type: string;
};

type PastAppointment = {
    date: string | null;
    type: string;
    diagnosis: string | null;
    treatment: string | null;
    medicines: string | null;
};

type Prescription = {
    date: string;
    treatment: string;
    days: number;
    medicines: string | null;
};

type Staff = {
    name: string;
    role: string;
};

type Billing = {
    hasDue: boolean;
    amount: string | null;
    paymentType: string | null;
    date: string | null;
};

const props = defineProps<{
    appointments: AppointmentCard[];
    bookingEnabled: boolean;
    pastAppointments: PastAppointment[];
    latestPrescription: Prescription | null;
    clinicStaff: Staff[];
    billingInfo: Billing;
}>();

defineOptions({
    layout: {
        title: 'Your care dashboard',
    },
});

const quickActions = [
    { label: 'Request appointment', icon: 'pi pi-calendar-plus' },
    { label: 'Message clinic', icon: 'pi pi-send' },
    { label: 'Upload document', icon: 'pi pi-upload' },
    { label: 'Pay invoice', icon: 'pi pi-wallet' },
];

const scheduledCount = computed(() => props.appointments.length);

// Next visit computed details
const nextVisit = computed(() => {
    if (props.appointments.length > 0) {
        return props.appointments[0];
    }
    return null;
});

// Dynamic care timeline compiled from upcoming and completed visits
const careTimeline = computed(() => {
    const items: Array<{ date: string; title: string; detail: string }> = [];

    // Add upcoming visits (max 2)
    props.appointments.slice(0, 2).forEach((app) => {
        items.push({
            date: app.date || 'Scheduled',
            title: `Upcoming ${app.type} visit`,
            detail: app.reason
                ? `Reason: ${app.reason}`
                : `Session: ${app.session} · Token ${app.number}`,
        });
    });

    // Add past completed visits (max 3)
    props.pastAppointments.slice(0, 3).forEach((app) => {
        items.push({
            date: app.date || 'Completed',
            title: `${app.type} Visit Completed`,
            detail: app.diagnosis
                ? `Diagnosis: ${app.diagnosis}`
                : 'Your clinic consultation summary is ready.',
        });
    });

    if (items.length === 0) {
        items.push({
            date: 'Portal Active',
            title: 'Welcome to your portal',
            detail: 'Use the booking desk to schedule your first clinic visit.',
        });
    }

    return items;
});

// Dynamic inbox notifications/messages based on patient record state
const dynamicMessages = computed(() => {
    const list: Array<{ title: string; detail: string }> = [];

    if (props.appointments.length > 0) {
        list.push({
            title: 'Appointment Confirmed',
            detail: `Your ${props.appointments[0].type} visit on ${props.appointments[0].date} is successfully scheduled.`,
        });
    }

    if (props.latestPrescription) {
        list.push({
            title: 'Medication Plan Active',
            detail: `Active treatment instructions: "${props.latestPrescription.treatment}".`,
        });
    }

    if (props.billingInfo.hasDue) {
        list.push({
            title: 'Recent Invoice Available',
            detail: `An invoice for ${props.billingInfo.amount} from your visit on ${props.billingInfo.date} is ready.`,
        });
    }

    if (list.length === 0) {
        list.push({
            title: 'No new updates',
            detail: 'All communications from your care navigator are up to date.',
        });
    }

    return list;
});
</script>

<template>
    <div class="page-grid patient-dashboard-page">
        <!-- Stats Grid -->
        <section class="stats-grid">
            <article class="stat-card primary-stat-card">
                <p class="stat-label">Next visit</p>
                <h2 v-if="nextVisit" class="stat-value">
                    {{ nextVisit.session }}
                </h2>
                <h2 v-else class="stat-value">None</h2>
                <p class="stat-note">
                    {{
                        nextVisit
                            ? `${nextVisit.type} · Token ${nextVisit.number} on ${nextVisit.date}`
                            : 'No upcoming visits scheduled'
                    }}
                </p>
            </article>
            <article class="stat-card secondary-stat-card">
                <p class="stat-label">Active plan</p>
                <h2 v-if="latestPrescription" class="stat-value">
                    {{
                        latestPrescription.days > 0
                            ? `${latestPrescription.days} Days`
                            : 'Prescribed'
                    }}
                </h2>
                <h2 v-else class="stat-value">None</h2>
                <p class="stat-note">
                    {{
                        latestPrescription
                            ? `${latestPrescription.treatment} (from ${latestPrescription.date})`
                            : 'No active treatment plan/prescription'
                    }}
                </p>
            </article>
            <article class="stat-card secondary-stat-card">
                <p class="stat-label">Total visits</p>
                <h2 class="stat-value">
                    {{
                        String(
                            pastAppointments.length + appointments.length,
                        ).padStart(2, '0')
                    }}
                </h2>
                <p class="stat-note">
                    {{ appointments.length }} scheduled &
                    {{ pastAppointments.length }} completed visits
                </p>
            </article>
        </section>

        <!-- Main Layout Split -->
        <section class="patient-dashboard-grid">
            <!-- Left Column: Snapshot & Appointments & Timeline -->
            <div class="patient-dashboard-main-column">
                <!-- Live Queue Board Banner -->
                <article class="page-card live-status-banner">
                    <div class="banner-content">
                        <div class="banner-icon">
                            <i class="pi pi-clock pulse-icon"></i>
                        </div>
                        <div class="banner-text">
                            <h3>Live Queue Board</h3>
                            <p>Check the live status of today's appointments and see when your token is being called in real-time.</p>
                        </div>
                    </div>
                    <Link :href="liveStatus().url">
                        <Button label="View Live Status" icon="pi pi-external-link" size="small" class="banner-btn" />
                    </Link>
                </article>

                <!-- Care Snapshot Card -->
                <article class="page-card snapshot-card">
                    <div class="snapshot-header-row">
                        <div>
                            <p class="stat-label">Your care overview</p>
                            <h2
                                v-if="latestPrescription"
                                class="panel-title"
                                style="margin-top: 0.35rem"
                            >
                                Active Treatment Prescription
                            </h2>
                            <h2
                                v-else
                                class="panel-title"
                                style="margin-top: 0.35rem"
                            >
                                Welcome to Clinic Connect
                            </h2>
                            <p
                                class="panel-subtitle"
                                style="margin-top: 0.55rem"
                            >
                                {{
                                    latestPrescription
                                        ? `Your active treatment instructions: "${latestPrescription.treatment}". Please follow the schedule as recommended.`
                                        : 'Manage your consultations, treatment files, and invoices directly from your patient panel.'
                                }}
                            </p>
                        </div>

                        <div
                            v-if="latestPrescription"
                            class="prescription-date-badge"
                        >
                            <span class="stat-label">Prescribed on</span>
                            <strong class="badge-value">{{
                                latestPrescription.date
                            }}</strong>
                        </div>
                    </div>

                    <!-- Quick Shortcuts Grid -->
                    <div class="quick-actions-grid">
                        <component
                            :is="
                                action.label === 'Request appointment' &&
                                bookingEnabled
                                    ? Link
                                    : 'div'
                            "
                            v-for="action in quickActions"
                            :key="action.label"
                            :href="
                                action.label === 'Request appointment' &&
                                bookingEnabled
                                    ? bookAppointment().url
                                    : undefined
                            "
                            class="quick-action-card"
                            :class="{
                                'is-disabled':
                                    action.label === 'Request appointment' &&
                                    !bookingEnabled,
                            }"
                        >
                            <div class="action-icon-wrapper">
                                <i :class="action.icon"></i>
                            </div>
                            <div class="action-title">
                                {{
                                    action.label === 'Request appointment' &&
                                    !bookingEnabled
                                        ? 'Booking closed'
                                        : action.label
                                }}
                            </div>
                            <p
                                class="panel-subtitle"
                                style="margin-top: 0.45rem"
                            >
                                {{
                                    action.label === 'Request appointment' &&
                                    !bookingEnabled
                                        ? 'Online booking is currently paused.'
                                        : 'Shortcut prepared for the next patient workflow step.'
                                }}
                            </p>
                        </component>
                    </div>
                </article>

                <!-- Scheduled Appointments List Card -->
                <article
                    id="appointments"
                    class="page-card appointments-list-panel"
                >
                    <div class="appointments-list-header">
                        <div>
                            <h3 class="panel-title">Upcoming appointments</h3>
                            <p class="panel-subtitle">
                                Everything you need before your next visit.
                            </p>
                        </div>

                        <div class="appointments-list-actions">
                            <Tag
                                :severity="
                                    scheduledCount > 0 ? 'info' : 'contrast'
                                "
                                :value="`${scheduledCount} scheduled`"
                                rounded
                            />
                            <Link
                                v-if="bookingEnabled"
                                :href="bookAppointment().url"
                            >
                                <Button
                                    label="Book appointment"
                                    icon="pi pi-calendar-plus"
                                    size="small"
                                />
                            </Link>
                            <Button
                                v-else
                                label="Booking Closed"
                                icon="pi pi-calendar-plus"
                                size="small"
                                disabled
                                severity="secondary"
                            />
                        </div>
                    </div>

                    <div
                        v-if="appointments.length > 0"
                        class="visit-cards-stack"
                    >
                        <div
                            v-for="visit in appointments"
                            :key="`${visit.date}-${visit.session}-${visit.number}`"
                            class="visit-list-card"
                        >
                            <div class="visit-list-card__row">
                                <div>
                                    <div class="visit-type-header">
                                        <span
                                            class="session-dot"
                                            :class="visit.session.toLowerCase()"
                                        ></span>
                                        <strong class="visit-type-label">
                                            {{ visit.type }}
                                        </strong>
                                    </div>
                                    <p
                                        class="panel-subtitle"
                                        style="margin-top: 0.45rem"
                                    >
                                        {{
                                            visit.reason ||
                                            'Reason will be shared with clinic team.'
                                        }}
                                    </p>
                                </div>

                                <div class="session-info">
                                    <div class="stat-label">
                                        {{ visit.date }}
                                    </div>
                                    <div class="session-detail">
                                        {{ visit.session }} · Token
                                        {{ visit.number ?? '-' }}
                                    </div>
                                    <div
                                        class="panel-subtitle"
                                        style="margin-top: 0.35rem"
                                    >
                                        Status: {{ visit.status }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="empty-bookings-card">
                        <h4 class="panel-title" style="font-size: 1rem">
                            No bookings yet
                        </h4>
                        <p class="panel-subtitle" style="margin-top: 0.45rem">
                            Pick a date and session to reserve your next clinic
                            visit.
                        </p>
                    </div>
                </article>

                <!-- Care Timeline Card -->
                <article id="care-team" class="page-card timeline-panel">
                    <div class="patient-care-grid">
                        <div class="timeline-column">
                            <h3 class="panel-title">Care timeline</h3>
                            <p
                                class="panel-subtitle"
                                style="margin-top: 0.45rem"
                            >
                                Recent updates from your care team and portal
                                activity.
                            </p>

                            <div class="timeline-items-stack">
                                <div
                                    v-for="item in careTimeline"
                                    :key="`${item.date}-${item.title}`"
                                    class="timeline-item-row"
                                >
                                    <div class="timeline-icon-wrapper">
                                        <i class="pi pi-check"></i>
                                    </div>

                                    <div>
                                        <div class="stat-label">
                                            {{ item.date }}
                                        </div>
                                        <div class="timeline-item-title">
                                            {{ item.title }}
                                        </div>
                                        <p
                                            class="panel-subtitle"
                                            style="margin-top: 0.35rem"
                                        >
                                            {{ item.detail }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Care Circle Panel (Right Side of Timeline) -->
                        <div class="care-circle-card">
                            <p class="eyebrow-label">Assigned team</p>
                            <h3 class="circle-title">Your care circle</h3>

                            <div class="staff-members-stack">
                                <div
                                    v-for="person in clinicStaff"
                                    :key="person.name"
                                    class="staff-member-card"
                                >
                                    <div class="staff-name">
                                        {{ person.name }}
                                    </div>
                                    <div class="staff-role">
                                        {{ person.role }}
                                    </div>
                                </div>
                                <div
                                    v-if="clinicStaff.length === 0"
                                    class="staff-empty-message"
                                >
                                    Our clinic navigators are here to support
                                    you.
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Right Column: Messages & Documents & Billing -->
            <div class="patient-dashboard-side-column">
                <!-- Inbox Messages -->
                <article id="messages" class="page-card messages-panel">
                    <div class="panel-heading-row">
                        <h3 class="panel-title">Messages</h3>
                        <Tag
                            :value="`${dynamicMessages.length} updates`"
                            severity="contrast"
                            rounded
                        />
                    </div>

                    <div class="messages-stack">
                        <div
                            v-for="message in dynamicMessages"
                            :key="message.title"
                            class="message-list-card"
                        >
                            <div class="message-title">{{ message.title }}</div>
                            <p
                                class="panel-subtitle"
                                style="margin-top: 0.35rem"
                            >
                                {{ message.detail }}
                            </p>
                        </div>
                    </div>
                </article>

                <!-- Medical Records (Documents) -->
                <article id="documents" class="page-card documents-panel">
                    <h3 class="panel-title">Documents</h3>
                    <p class="panel-subtitle" style="margin-top: 0.4rem">
                        Recent records available in your portal.
                    </p>

                    <div class="documents-stack">
                        <div
                            v-for="visit in pastAppointments"
                            :key="visit.date"
                            class="document-list-card"
                        >
                            <div>
                                <div class="document-name">
                                    {{ visit.type }} Record
                                </div>
                                <div
                                    class="panel-subtitle"
                                    style="margin-top: 0.35rem"
                                >
                                    Completed on {{ visit.date }}
                                </div>
                            </div>
                            <Tag value="Completed" severity="success" rounded />
                        </div>
                        <div
                            v-if="pastAppointments.length === 0"
                            class="panel-subtitle"
                            style="padding: 0.5rem 0"
                        >
                            No medical records available.
                        </div>
                    </div>
                </article>

                <!-- Billing Snap Card -->
                <article
                    id="billing"
                    class="page-card system-card billing-panel"
                >
                    <p class="billing-eyebrow">Billing snapshot</p>
                    <h3
                        v-if="billingInfo.hasDue"
                        class="panel-title billing-title"
                    >
                        Invoice total: {{ billingInfo.amount }}
                    </h3>
                    <h3 v-else class="panel-title billing-title">
                        No pending balance
                    </h3>
                    <p class="billing-copy">
                        {{
                            billingInfo.hasDue
                                ? `Payment method: ${billingInfo.paymentType} for your clinic visit on ${billingInfo.date}.`
                                : 'All invoices are fully paid and cleared with the billing department.'
                        }}
                    </p>
                </article>
            </div>
        </section>
    </div>
</template>

<style scoped>
.patient-dashboard-page {
    gap: 1.5rem;
}

/* Stat Cards styled using Sakai CSS rules and CSS variables */
.primary-stat-card {
    background: linear-gradient(
        135deg,
        var(--primary-color) 0%,
        color-mix(in srgb, var(--primary-color) 80%, #000) 100%
    ) !important;
    color: var(--primary-contrast-color) !important;
    border-color: transparent !important;
    box-shadow: var(--card-shadow-strong) !important;

    .stat-label,
    .stat-note {
        color: rgba(255, 255, 255, 0.88) !important;
    }

    .stat-value {
        color: #fff !important;
        font-size: 2.1rem;
        font-weight: 800;
        margin-top: 0.35rem;
    }
}

.secondary-stat-card {
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--primary-color) 4%, var(--surface-card)) 0%,
        var(--surface-card) 100%
    ) !important;
    border: 1px solid var(--surface-border) !important;
    box-shadow: var(--card-shadow) !important;
    transition:
        transform 0.2s ease,
        border-color 0.2s ease;

    &:hover {
        transform: translateY(-3px);
        border-color: var(--primary-color) !important;
    }

    .stat-value {
        color: var(--text-color);
        font-size: 2.1rem;
        font-weight: 800;
        margin-top: 0.35rem;
    }

    .stat-label {
        color: var(--text-secondary-color);
    }

    .stat-note {
        color: var(--text-secondary-color);
    }
}

/* Patient Main Dashboard Grid Layout */
.patient-dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.65fr) minmax(19rem, 0.95fr);
    gap: 1.5rem;
}

@media (max-width: 991px) {
    .patient-dashboard-grid {
        grid-template-columns: 1fr;
    }
}

.patient-dashboard-main-column {
    display: grid;
    gap: 1.5rem;
    min-width: 0;
}

.patient-dashboard-side-column {
    display: grid;
    gap: 1.5rem;
    min-width: 0;
}

/* Overview / Snapshot */
.snapshot-card {
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--primary-color) 3%, var(--surface-card)) 0%,
        var(--surface-card) 100%
    ) !important;
    border: 1px solid var(--surface-border) !important;
    padding: 1.6rem;
}

.snapshot-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.35rem;
    flex-wrap: wrap;
}

.prescription-date-badge {
    padding: 0.95rem 1rem;
    border-radius: 22px;
    min-width: 14rem;
    background: color-mix(
        in srgb,
        var(--primary-color) 8%,
        var(--surface-card)
    );
    border: 1px solid
        color-mix(in srgb, var(--primary-color) 12%, var(--surface-border));
    display: flex;
    justify-content: space-between;
    align-items: center;

    .stat-label {
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-secondary-color);
    }

    .badge-value {
        color: var(--primary-color);
        font-weight: 700;
    }
}

/* Quick Actions shortcuts grid */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    gap: 1rem;
}

.quick-action-card {
    padding: 1rem;
    border: 1px solid var(--surface-border) !important;
    background: var(--surface-card) !important;
    box-shadow: var(--card-shadow) !important;
    text-decoration: none !important;
    transition: all 0.2s ease !important;
    cursor: pointer;

    &:hover {
        transform: translateY(-2px);
        border-color: var(--primary-color) !important;
    }

    &.is-disabled {
        opacity: 0.5;
        cursor: default;
        &:hover {
            transform: none;
            border-color: var(--surface-border) !important;
        }
    }

    .action-icon-wrapper {
        width: 2.75rem;
        height: 2.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: color-mix(
            in srgb,
            var(--primary-color) 10%,
            var(--surface-card)
        );
        color: var(--primary-color);
        margin-bottom: 0.85rem;
        border-radius: var(--content-border-radius);
    }

    .action-title {
        font-weight: 700;
        color: var(--text-color);
    }
}

/* Appointments lists */
.appointments-list-panel {
    padding: 1.6rem;
}

.appointments-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.appointments-list-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.visit-cards-stack {
    display: grid;
    gap: 1rem;
}

.visit-list-card {
    padding: 1.2rem;
    background: var(--surface-card) !important;
    border: 1px solid var(--surface-border) !important;
    border-radius: var(--content-border-radius);
    transition: border-color 0.2s ease;

    &:hover {
        border-color: var(--primary-color) !important;
    }
}

.visit-list-card__row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
    flex-wrap: wrap;
}

.visit-type-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.session-dot {
    width: 0.8rem;
    height: 0.8rem;
    border-radius: 999px;
    background: var(--primary-color);
    display: inline-block;

    &.morning {
        background: var(--primary-500, #0fb5ba);
    }
    &.evening {
        background: var(--primary-700, #0e8e92);
    }
}

.visit-type-label {
    font-size: 1.05rem;
    color: var(--text-color);
}

.session-info {
    padding: 0.8rem 0.9rem;
    background: color-mix(
        in srgb,
        var(--primary-color) 8%,
        var(--surface-card)
    ) !important;
    border-radius: 6px;
    min-width: 10rem;
}

.session-detail {
    margin-top: 0.35rem;
    font-weight: 700;
    color: var(--primary-color);
}

.empty-bookings-card {
    padding: 1.2rem;
    background: var(--surface-card) !important;
    border: 1px dashed var(--surface-border) !important;
    border-radius: var(--content-border-radius);
}

/* Care timeline & circle details */
.timeline-panel {
    padding: 1.6rem;
}

.patient-care-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
    gap: 1.25rem;
}

@media (max-width: 768px) {
    .patient-care-grid {
        grid-template-columns: 1fr;
    }
}

.timeline-column {
    display: grid;
}

.timeline-items-stack {
    display: grid;
    gap: 1rem;
    margin-top: 1.2rem;
}

.timeline-item-row {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.9rem;
    align-items: start;
}

.timeline-icon-wrapper {
    width: 2.25rem;
    height: 2.25rem;
    background: color-mix(
        in srgb,
        var(--primary-color) 10%,
        var(--surface-card)
    );
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    border-radius: var(--content-border-radius);
}

.timeline-item-title {
    margin-top: 0.35rem;
    font-weight: 700;
    color: var(--text-color);
}

/* Care Circle Container styling */
.care-circle-card {
    padding: 1.3rem;
    background: linear-gradient(
        160deg,
        var(--primary-800, #0c6e72),
        var(--primary-950, #062c2e)
    ) !important;
    color: rgba(255, 255, 255, 0.88) !important;
    border-radius: var(--content-border-radius);
    display: flex;
    flex-direction: column;

    .eyebrow-label {
        margin: 0;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        font-size: 0.74rem;
        color: rgba(255, 255, 255, 0.8) !important;
    }

    .circle-title {
        margin: 0.6rem 0 0;
        color: white !important;
        font-size: 1.3rem;
    }
}

.staff-members-stack {
    display: grid;
    gap: 0.95rem;
    margin-top: 1.25rem;
}

.staff-member-card {
    padding: 0.95rem;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 6px;

    .staff-name {
        font-weight: 700;
        color: white;
    }

    .staff-role {
        font-size: 0.88rem;
        margin-top: 0.35rem;
        opacity: 0.76;
        color: rgba(255, 255, 255, 0.85);
    }
}

.staff-empty-message {
    font-size: 0.88rem;
    opacity: 0.76;
    color: rgba(255, 255, 255, 0.85);
}

/* Sidebar Panels: Messages, Documents, Billing */
.messages-panel,
.documents-panel {
    padding: 1.4rem;
}

.panel-heading-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.messages-stack,
.documents-stack {
    display: grid;
    gap: 0.85rem;
}

.message-list-card {
    padding: 0.95rem 1rem;
    background: var(--surface-card) !important;
    border: 1px solid var(--surface-border) !important;
    border-radius: var(--content-border-radius);
    transition: border-color 0.2s ease;

    &:hover {
        border-color: var(--primary-color) !important;
    }

    .message-title {
        font-weight: 700;
        color: var(--text-color);
    }
}

.document-list-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.95rem 1rem;
    background: var(--surface-card) !important;
    border: 1px solid var(--surface-border) !important;
    border-radius: var(--content-border-radius);
    transition: border-color 0.2s ease;

    &:hover {
        border-color: var(--primary-color) !important;
    }

    .document-name {
        font-weight: 700;
        color: var(--text-color);
    }
}

/* Billing Panel */
.billing-panel {
    padding: 1.4rem;
    .billing-eyebrow {
        margin: 0;
        color: rgba(255, 255, 255, 0.72) !important;
    }
    .billing-title {
        color: white !important;
        margin-top: 0.35rem;
    }
    .billing-copy {
        color: rgba(255, 255, 255, 0.72) !important;
        margin: 0.5rem 0 0;
    }
}

/* Live Status Banner */
.live-status-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, rgba(15, 181, 186, 0.08) 0%, rgba(15, 181, 186, 0.02) 100%) !important;
    border: 1px solid rgba(15, 181, 186, 0.2) !important;
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.banner-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.banner-icon {
    width: 2.5rem;
    height: 2.5rem;
    background: rgba(15, 181, 186, 0.15);
    color: #0fb5ba;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.pulse-icon {
    animation: rotate-pulse 2s infinite ease-in-out;
}

@keyframes rotate-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.banner-text h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-color);
}

.banner-text p {
    margin: 0.25rem 0 0;
    font-size: 0.85rem;
    color: var(--text-secondary-color);
    line-height: 1.4;
}

.banner-btn {
    background: #0fb5ba !important;
    border-color: #0fb5ba !important;
}
</style>
