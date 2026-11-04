<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import {
    dashboard as patientDashboard,
    liveStatus as patientLiveStatus,
    login as patientLogin,
} from '@/routes/patient';

type ClinicInfo = {
    name: string;
    doctorName?: string | null;
    doctorTitle?: string | null;
    doctorQualifications?: string | null;
    address?: string | null;
    phone?: string | null;
    email?: string | null;
    logoUrl?: string | null;
};

type ScheduleInfo = {
    bookingEnabled: boolean;
    morningOpening: string;
    morningClosing: string;
    eveningOpening: string;
    eveningClosing: string;
    noticeEnabled: boolean;
    noticeText?: string | null;
};

const props = withDefaults(
    defineProps<{
        clinic?: ClinicInfo;
        schedule?: ScheduleInfo;
    }>(),
    {
        clinic: () => ({
            name: 'AOM Homeopathic Clinic',
            doctorName: null,
            doctorTitle: null,
            doctorQualifications: null,
            address: null,
            phone: null,
            email: null,
            logoUrl: null,
        }),
        schedule: () => ({
            bookingEnabled: true,
            morningOpening: '09:30',
            morningClosing: '13:30',
            eveningOpening: '17:00',
            eveningClosing: '20:30',
            noticeEnabled: false,
            noticeText: null,
        }),
    },
);

function formatTime(timeStr?: string | null): string {
    if (!timeStr) {
        return '';
    }
    const [hStr, mStr] = timeStr.split(':');
    const hours = Number(hStr);
    const minutes = Number(mStr ?? 0);
    if (Number.isNaN(hours)) {
        return timeStr;
    }
    const period = hours >= 12 ? 'PM' : 'AM';
    const hour12 = hours % 12 || 12;
    return `${hour12}:${minutes.toString().padStart(2, '0')} ${period}`;
}
</script>

<template>
    <Head :title="props.clinic.name" />

    <div class="auth-page welcome-page">
        <div class="welcome-shell">
            <!-- Top Navigation Bar -->
            <header class="welcome-topbar">
                <div class="welcome-brand">
                    <img
                        v-if="props.clinic.logoUrl"
                        :src="props.clinic.logoUrl"
                        :alt="props.clinic.name"
                        class="welcome-brand__logo"
                    />
                    <div v-else class="welcome-brand__icon">
                        <i class="pi pi-heart-fill"></i>
                    </div>
                    <div class="welcome-brand__copy">
                        <strong class="welcome-brand__name">{{
                            props.clinic.name
                        }}</strong>
                        <span
                            v-if="props.clinic.doctorName"
                            class="welcome-brand__sub"
                        >
                            {{ props.clinic.doctorName }}
                            <template v-if="props.clinic.doctorQualifications">
                                · {{ props.clinic.doctorQualifications }}
                            </template>
                        </span>
                    </div>
                </div>

                <nav class="welcome-nav">
                    <Link :href="patientLiveStatus()" class="link-button">
                        <i class="pi pi-bolt"></i>
                        <span>Live Queue</span>
                    </Link>

                    <Link
                        v-if="$page.props.auth.staff"
                        :href="dashboard()"
                        class="link-button link-button--primary"
                    >
                        <i class="pi pi-th-large"></i>
                        <span>Staff Workspace</span>
                    </Link>
                    <Link
                        v-else-if="$page.props.auth.patient"
                        :href="patientDashboard()"
                        class="link-button link-button--primary"
                    >
                        <i class="pi pi-user"></i>
                        <span>Patient Portal</span>
                    </Link>
                    <template v-else>
                        <Link
                            :href="patientLogin()"
                            class="link-button link-button--primary"
                        >
                            <i class="pi pi-sign-in"></i>
                            <span>Patient Login</span>
                        </Link>
                    </template>
                </nav>
            </header>

            <!-- Clinic Notice Banner (if enabled in Booking Settings) -->
            <div
                v-if="props.schedule.noticeEnabled && props.schedule.noticeText"
                class="welcome-notice-banner"
            >
                <i class="pi pi-megaphone"></i>
                <span>{{ props.schedule.noticeText }}</span>
            </div>

            <!-- Main Portal Cards Grid -->
            <main class="welcome-main-grid">
                <!-- Left: Patient Actions & Services -->
                <section class="page-card welcome-hero-card">
                    <span class="welcome-kicker">
                        <span class="welcome-kicker__dot"></span>
                        Online Patient &amp; Clinic Portal
                    </span>

                    <h1 class="welcome-title">
                        Check live token numbers, book appointments &amp; track
                        your care.
                    </h1>

                    <p class="welcome-description">
                        Welcome to <strong>{{ props.clinic.name }}</strong>.
                        Check the real-time consultation queue from home, sign
                        in to your patient portal to book tokens for yourself or
                        family members, and track prescribed courier medicines.
                    </p>

                    <!-- Primary Action Cards -->
                    <div class="welcome-action-cards">
                        <Link
                            :href="patientLiveStatus()"
                            class="welcome-action-tile welcome-action-tile--highlight"
                        >
                            <div class="welcome-action-tile__icon">
                                <i class="pi pi-clock"></i>
                            </div>
                            <div class="welcome-action-tile__body">
                                <strong>Check Live Token Queue</strong>
                                <p>
                                    See which appointment token is currently
                                    running in the clinic without logging in.
                                </p>
                            </div>
                            <i
                                class="pi pi-arrow-right welcome-action-tile__arrow"
                            ></i>
                        </Link>

                        <Link
                            :href="
                                $page.props.auth.patient
                                    ? patientDashboard()
                                    : patientLogin()
                            "
                            class="welcome-action-tile"
                        >
                            <div class="welcome-action-tile__icon">
                                <i class="pi pi-calendar-plus"></i>
                            </div>
                            <div class="welcome-action-tile__body">
                                <strong>
                                    {{
                                        $page.props.auth.patient
                                            ? 'Open Patient Portal'
                                            : 'Patient Portal Login'
                                    }}
                                </strong>
                                <p>
                                    Book morning or evening consultation tokens,
                                    manage family dependents, and view visit
                                    history.
                                </p>
                            </div>
                            <i
                                class="pi pi-arrow-right welcome-action-tile__arrow"
                            ></i>
                        </Link>

                        <Link
                            v-if="$page.props.auth.staff"
                            :href="dashboard()"
                            class="welcome-action-tile"
                        >
                            <div class="welcome-action-tile__icon">
                                <i class="pi pi-briefcase"></i>
                            </div>
                            <div class="welcome-action-tile__body">
                                <strong>Enter Staff Dashboard</strong>
                                <p>
                                    Manage daily consultations, patient records,
                                    vitals, medicine inventory, and courier
                                    dispatches.
                                </p>
                            </div>
                            <i
                                class="pi pi-arrow-right welcome-action-tile__arrow"
                            ></i>
                        </Link>
                    </div>
                </section>

                <!-- Right: Clinic Hours, Doctor & Contact Info -->
                <aside class="welcome-sidebar">
                    <!-- Consultation Timings Card -->
                    <section class="page-card welcome-info-card">
                        <div class="welcome-info-card__header">
                            <i class="pi pi-clock"></i>
                            <h2>Consultation Hours</h2>
                        </div>

                        <div class="welcome-timings-list">
                            <div class="welcome-timing-row">
                                <div class="welcome-timing-row__session">
                                    <i
                                        class="pi pi-sun welcome-session-icon--morning"
                                    ></i>
                                    <div>
                                        <strong>Morning Session</strong>
                                        <span>Daily consultation tokens</span>
                                    </div>
                                </div>
                                <span class="welcome-timing-badge">
                                    {{
                                        formatTime(
                                            props.schedule.morningOpening,
                                        )
                                    }}
                                    –
                                    {{
                                        formatTime(
                                            props.schedule.morningClosing,
                                        )
                                    }}
                                </span>
                            </div>

                            <div class="welcome-timing-row">
                                <div class="welcome-timing-row__session">
                                    <i
                                        class="pi pi-moon welcome-session-icon--evening"
                                    ></i>
                                    <div>
                                        <strong>Evening Session</strong>
                                        <span>Daily consultation tokens</span>
                                    </div>
                                </div>
                                <span class="welcome-timing-badge">
                                    {{
                                        formatTime(
                                            props.schedule.eveningOpening,
                                        )
                                    }}
                                    –
                                    {{
                                        formatTime(
                                            props.schedule.eveningClosing,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <!-- Clinic Contact & Location Card -->
                    <section class="page-card welcome-info-card">
                        <div class="welcome-info-card__header">
                            <i class="pi pi-map-marker"></i>
                            <h2>Clinic Information</h2>
                        </div>

                        <div class="welcome-contact-list">
                            <div
                                v-if="props.clinic.doctorName"
                                class="welcome-contact-item"
                            >
                                <i class="pi pi-user"></i>
                                <div>
                                    <span>Consulting Physician</span>
                                    <strong>{{
                                        props.clinic.doctorName
                                    }}</strong>
                                    <small
                                        v-if="
                                            props.clinic.doctorQualifications ||
                                            props.clinic.doctorTitle
                                        "
                                    >
                                        {{
                                            [
                                                props.clinic
                                                    .doctorQualifications,
                                                props.clinic.doctorTitle,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')
                                        }}
                                    </small>
                                </div>
                            </div>

                            <div
                                v-if="props.clinic.address"
                                class="welcome-contact-item"
                            >
                                <i class="pi pi-building"></i>
                                <div>
                                    <span>Clinic Address</span>
                                    <strong>{{ props.clinic.address }}</strong>
                                </div>
                            </div>

                            <div
                                v-if="props.clinic.phone"
                                class="welcome-contact-item"
                            >
                                <i class="pi pi-phone"></i>
                                <div>
                                    <span>Reception Desk</span>
                                    <a :href="`tel:${props.clinic.phone}`">
                                        {{ props.clinic.phone }}
                                    </a>
                                </div>
                            </div>

                            <div
                                v-if="props.clinic.email"
                                class="welcome-contact-item"
                            >
                                <i class="pi pi-envelope"></i>
                                <div>
                                    <span>Email</span>
                                    <a :href="`mailto:${props.clinic.email}`">
                                        {{ props.clinic.email }}
                                    </a>
                                </div>
                            </div>

                            <div class="welcome-reception-note">
                                <i class="pi pi-info-circle"></i>
                                <span>
                                    New patient portal accounts are registered
                                    directly at the clinic reception desk.
                                </span>
                            </div>
                        </div>
                    </section>
                </aside>
            </main>
        </div>
    </div>
</template>

<style scoped>
.welcome-page {
    align-items: start;
    padding-top: 2rem;
    padding-bottom: 3rem;
}

.welcome-shell {
    width: min(100%, 74rem);
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.welcome-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 1rem 1.35rem;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    box-shadow: var(--card-shadow);
}

.welcome-brand {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.welcome-brand__logo {
    width: 2.75rem;
    height: 2.75rem;
    object-fit: contain;
    border-radius: 8px;
}

.welcome-brand__icon {
    width: 2.65rem;
    height: 2.65rem;
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: grid;
    place-items: center;
    font-size: 1.15rem;
}

.welcome-brand__copy {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.welcome-brand__name {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-color);
    letter-spacing: -0.02em;
}

.welcome-brand__sub {
    font-size: 0.8rem;
    color: var(--text-secondary-color);
    font-weight: 600;
}

.welcome-nav {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.welcome-notice-banner {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.9rem 1.25rem;
    border-radius: 10px;
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #b45309;
    font-weight: 600;
    font-size: 0.9rem;
}

.welcome-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(21rem, 0.95fr);
    gap: 1.25rem;
    align-items: start;
}

@media (max-width: 960px) {
    .welcome-main-grid {
        grid-template-columns: 1fr;
    }
}

.welcome-hero-card {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.welcome-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    align-self: flex-start;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--primary-color) 10%, transparent);
    color: var(--primary-color);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.welcome-kicker__dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 50%;
    background: var(--primary-color);
}

.welcome-title {
    margin: 0;
    font-size: clamp(1.75rem, 3vw, 2.45rem);
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.03em;
    color: var(--text-color);
}

.welcome-description {
    margin: 0;
    font-size: 0.96rem;
    line-height: 1.65;
    color: var(--text-secondary-color);
}

.welcome-action-cards {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    margin-top: 0.35rem;
}

.welcome-action-tile {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.15rem 1.25rem;
    border-radius: 10px;
    border: 1px solid var(--surface-border);
    background: var(--surface-ground, #f8fafc);
    text-decoration: none;
    transition: all 0.18s ease;
}

.welcome-action-tile:hover {
    border-color: var(--primary-color);
    background: var(--surface-card);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px -6px rgba(15, 23, 42, 0.08);
}

.welcome-action-tile--highlight {
    background: color-mix(
        in srgb,
        var(--primary-color) 6%,
        var(--surface-card)
    );
    border-color: color-mix(
        in srgb,
        var(--primary-color) 28%,
        var(--surface-border)
    );
}

.welcome-action-tile__icon {
    width: 2.85rem;
    height: 2.85rem;
    border-radius: 10px;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.welcome-action-tile__body {
    flex: 1;
    min-width: 0;
}

.welcome-action-tile__body strong {
    display: block;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-color);
    margin-bottom: 0.2rem;
}

.welcome-action-tile__body p {
    margin: 0;
    font-size: 0.84rem;
    line-height: 1.45;
    color: var(--text-secondary-color);
}

.welcome-action-tile__arrow {
    color: var(--text-secondary-color);
    font-size: 0.9rem;
    transition: transform 0.18s ease, color 0.18s ease;
}

.welcome-action-tile:hover .welcome-action-tile__arrow {
    transform: translateX(3px);
    color: var(--primary-color);
}

.welcome-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.welcome-info-card {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.welcome-info-card__header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--surface-border);
}

.welcome-info-card__header i {
    color: var(--primary-color);
    font-size: 1.05rem;
}

.welcome-info-card__header h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-color);
}

.welcome-timings-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.welcome-timing-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 0.85rem 1rem;
    border-radius: 8px;
    background: var(--surface-ground, #f8fafc);
    border: 1px solid var(--surface-border);
}

.welcome-timing-row__session {
    display: flex;
    align-items: center;
    gap: 0.7rem;
}

.welcome-timing-row__session strong {
    display: block;
    font-size: 0.9rem;
    color: var(--text-color);
}

.welcome-timing-row__session span {
    display: block;
    font-size: 0.76rem;
    color: var(--text-secondary-color);
}

.welcome-session-icon--morning {
    color: #d97706;
    font-size: 1.1rem;
}

.welcome-session-icon--evening {
    color: #4f46e5;
    font-size: 1.1rem;
}

.welcome-timing-badge {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 10%, transparent);
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    white-space: nowrap;
}

.welcome-contact-list {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.welcome-contact-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.welcome-contact-item > i {
    color: var(--primary-color);
    font-size: 0.95rem;
    margin-top: 0.2rem;
    flex-shrink: 0;
}

.welcome-contact-item div {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.welcome-contact-item span {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary-color);
}

.welcome-contact-item strong,
.welcome-contact-item a {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-color);
    text-decoration: none;
    line-height: 1.4;
}

.welcome-contact-item a:hover {
    color: var(--primary-color);
}

.welcome-contact-item small {
    font-size: 0.8rem;
    color: var(--text-secondary-color);
}

.welcome-reception-note {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.75rem 0.9rem;
    border-radius: 8px;
    background: var(--surface-ground, #f8fafc);
    border: 1px solid var(--surface-border);
    font-size: 0.8rem;
    color: var(--text-secondary-color);
    line-height: 1.45;
    margin-top: 0.25rem;
}

.welcome-reception-note i {
    color: var(--primary-color);
    margin-top: 0.15rem;
}
</style>
