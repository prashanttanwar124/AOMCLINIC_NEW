<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import {
    dashboard as patientDashboard,
    login as patientLogin,
    register as patientRegister,
    liveStatus as patientLiveStatus,
} from '@/routes/patient';

const launchMetrics = [
    { label: 'Live queue visibility', value: '24/7' },
    { label: 'Faster check-ins', value: '5 min' },
    { label: 'Patient satisfaction', value: '94%' },
    { label: 'Inventory clarity', value: 'Real-time' },
];

const experienceHighlights = [
    {
        title: 'PrimeVue-first design system',
        copy: 'The interface now leans on the Sakai shell and PrimeVue components for a more unified and maintainable clinic experience.',
    },
    {
        title: 'Squared surfaces with selective pills',
        copy: 'The app keeps its established square-edge rule while using rounded-only capsule chips where they improve scanning and filtering.',
    },
    {
        title: 'Admin, auth, and patient journeys aligned',
        copy: 'Landing, portal, dashboard, booking, registry, and settings screens now speak the same visual language across the product.',
    },
    {
        title: 'Built for speed at the desk',
        copy: 'Faster hierarchy, calmer spacing, stronger status contrast, and clearer action placement help busy staff move with confidence.',
    },
];

const queuePreview = [
    {
        stage: 'Arrivals',
        detail: 'Morning desk confirms same-day bookings',
        status: 'Running',
    },
    {
        stage: 'Care team',
        detail: 'Records and visit context stay visible',
        status: 'Ready',
    },
    {
        stage: 'Pharmacy',
        detail: 'Inventory panels surface stock changes quickly',
        status: 'Synced',
    },
];
</script>

<template>
    <Head title="Welcome" />

    <div class="auth-page">
        <div class="landing-shell">
            <header class="landing-header">
                <div class="page-card" style="padding: 1rem 1.2rem">
                    <div class="landing-kicker landing-pill--soft">
                        AOM Clinic platform
                    </div>
                </div>

                <nav class="landing-nav">
                    <Link
                        v-if="$page.props.auth.staff"
                        :href="dashboard()"
                        class="link-button link-button--primary"
                    >
                        Staff dashboard
                    </Link>
                    <Link
                        v-else-if="$page.props.auth.patient"
                        :href="patientDashboard()"
                        class="link-button link-button--primary"
                    >
                        Patient portal
                    </Link>
                    <template v-else>
                        <Link :href="patientLiveStatus()" class="link-button"
                            >Live Queue</Link
                        >
                        <Link :href="login()" class="link-button"
                            >Staff login</Link
                        >
                        <Link :href="register()" class="link-button"
                            >Staff register</Link
                        >
                        <Link :href="patientLogin()" class="link-button"
                            >Patient login</Link
                        >
                        <Link
                            :href="patientRegister()"
                            class="link-button link-button--primary"
                        >
                            Patient register
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="landing-grid">
                <section class="page-card landing-panel">
                    <span class="landing-kicker landing-pill--soft">
                        Premium clinic workflow
                    </span>

                    <h1 class="landing-title">
                        One calm, modern workspace for admin teams and patients.
                    </h1>

                    <p class="landing-copy">
                        PrimeVue and the Sakai shell create a focused clinic
                        experience across booking, records, inventory,
                        authentication, and day-to-day operations.
                    </p>

                    <div class="landing-actions">
                        <Link
                            v-if="$page.props.auth.staff"
                            :href="dashboard()"
                            class="link-button link-button--primary"
                        >
                            Enter admin workspace
                        </Link>
                        <Link
                            v-else-if="$page.props.auth.patient"
                            :href="patientDashboard()"
                            class="link-button link-button--primary"
                        >
                            Open patient portal
                        </Link>
                        <Link
                            v-if="$page.props.auth.patient"
                            :href="patientLiveStatus()"
                            class="link-button"
                        >
                            Live Queue Status
                        </Link>
                        <template v-else>
                            <Link
                                :href="patientRegister()"
                                class="link-button link-button--primary"
                            >
                                Start as patient
                            </Link>
                            <Link
                                :href="patientLiveStatus()"
                                class="link-button"
                            >
                                Live Queue Status
                            </Link>
                            <Link :href="login()" class="link-button">
                                Staff sign in
                            </Link>
                        </template>

                        <a
                            href="https://laravel.com/docs"
                            target="_blank"
                            rel="noreferrer"
                            class="link-button"
                        >
                            Laravel docs
                        </a>
                    </div>

                    <div class="landing-feature-grid">
                        <article
                            v-for="highlight in experienceHighlights"
                            :key="highlight.title"
                            class="landing-feature"
                        >
                            <h3 class="panel-title" style="font-size: 1rem">
                                {{ highlight.title }}
                            </h3>
                            <p class="panel-subtitle">
                                {{ highlight.copy }}
                            </p>
                        </article>
                    </div>
                </section>

                <aside class="auth-hero-panel landing-side-stack">
                    <section class="landing-metric-card">
                        <span class="landing-pill landing-pill--white">
                            Live operations
                        </span>
                        <h2 style="font-size: 2rem; margin: 1rem 0 0">
                            Booking, queues, and follow-ups stay readable at a
                            glance.
                        </h2>
                        <p
                            style="
                                margin: 0.9rem 0 0;
                                color: rgba(255, 255, 255, 0.78);
                            "
                        >
                            The refreshed shell focuses on stronger hierarchy,
                            cleaner data presentation, and faster action
                            discovery.
                        </p>
                    </section>

                    <section class="landing-metric-grid">
                        <article
                            v-for="metric in launchMetrics"
                            :key="metric.label"
                            class="landing-stat"
                        >
                            <span
                                class="stat-label"
                                style="color: rgba(255, 255, 255, 0.76)"
                            >
                                {{ metric.label }}
                            </span>
                            <strong>{{ metric.value }}</strong>
                        </article>
                    </section>

                    <section class="landing-queue">
                        <article
                            v-for="item in queuePreview"
                            :key="item.stage"
                            class="landing-queue__item"
                        >
                            <div class="landing-queue__meta">
                                <strong>{{ item.stage }}</strong>
                                <span style="color: rgba(255, 255, 255, 0.74)">
                                    {{ item.detail }}
                                </span>
                            </div>
                            <span class="landing-pill landing-pill--white">
                                {{ item.status }}
                            </span>
                        </article>
                    </section>
                </aside>
            </main>
        </div>
    </div>
</template>
