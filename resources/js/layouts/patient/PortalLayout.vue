<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Toast from 'primevue/toast';
import FlashToastBridge from '@/components/FlashToastBridge.vue';
import { create as bookAppointment } from '@/actions/App/Http/Controllers/Patient/PatientAppointmentController';
import { dashboard, dependents, liveStatus, logout } from '@/routes/patient';

defineProps<{
    title?: string;
}>();

const page = usePage();
const patient = computed(() => page.props.auth.patient!);
const appName = computed(() => page.props.name ?? 'Clinic Connect');
const currentPath = computed(() => page.url);
const mobileMenuActive = ref(false);
const staticMenuInactive = ref(false);

const patientInitials = computed(() => {
    if (!patient.value?.name) return 'PT';
    const parts = patient.value.name.split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts[0].substring(0, 2).toUpperCase();
});

const menuItems = computed(() => [
    {
        label: 'Overview',
        items: [
            { label: 'Dashboard', icon: 'pi pi-home', href: dashboard().url },
            {
                label: 'Appointments',
                icon: 'pi pi-calendar',
                href: bookAppointment().url,
            },
            {
                label: 'Live Queue Status',
                icon: 'pi pi-clock',
                href: liveStatus().url,
            },
            {
                label: 'Dependents',
                icon: 'pi pi-users',
                href: dependents().url,
            },
        ],
    },
]);

const containerClass = computed(() => ({
    'layout-static': true,
    'layout-static-inactive': staticMenuInactive.value,
    'layout-mobile-active': mobileMenuActive.value,
    'patient-layout': true,
}));

const isActiveRoute = (href: string): boolean => {
    if (href.startsWith('#')) {
        return false;
    }

    return currentPath.value.startsWith(href);
};

const toggleMenu = (): void => {
    if (window.innerWidth > 991) {
        staticMenuInactive.value = !staticMenuInactive.value;
        return;
    }

    mobileMenuActive.value = !mobileMenuActive.value;
};

const closeMobileMenu = (): void => {
    mobileMenuActive.value = false;
};
</script>

<template>
    <div class="layout-wrapper" :class="containerClass">
        <Head :title="title" />

        <div class="layout-topbar">
            <div class="layout-topbar-logo-container">
                <button
                    type="button"
                    class="layout-menu-button layout-topbar-action"
                    @click="toggleMenu"
                >
                    <i class="pi pi-bars"></i>
                </button>

                <Link :href="dashboard()" class="layout-topbar-logo">
                    <span class="logo-text"
                        >Clinic <span class="logo-text-alt">Connect</span>
                        <span class="portal-badge">Patient Portal</span></span
                    >
                </Link>
            </div>

            <div class="layout-topbar-actions">
                <div class="layout-topbar-menu-content patient-topbar-panel">
                    <div class="admin-topbar-user-profile">
                        <div class="user-avatar patient-avatar">
                            {{ patientInitials }}
                        </div>
                        <div class="user-info">
                            <span class="user-role">Patient</span>
                            <span class="user-name">{{ patient.name }}</span>
                        </div>
                    </div>
                    <div class="patient-topbar-actions">
                        <Link
                            :href="logout()"
                            method="post"
                            as="button"
                            class="layout-topbar-action logout-action"
                        >
                            <i class="pi pi-sign-out"></i>
                            <span>Logout</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
        <aside class="layout-sidebar patient-sidebar">
            <ul class="layout-menu">
                <li
                    v-for="group in menuItems"
                    :key="group.label"
                    class="layout-root-menuitem"
                >
                    <div
                        class="layout-menuitem-root-text patient-menu-group-label"
                    >
                        {{ group.label }}
                    </div>
                    <ul class="patient-menu-list">
                        <li v-for="item in group.items" :key="item.href">
                            <Link
                                :href="item.href"
                                class="patient-menu-link"
                                :class="{
                                    'active-route patient-menu-link--active':
                                        isActiveRoute(item.href),
                                }"
                                @click="closeMobileMenu"
                            >
                                <i
                                    class="layout-menuitem-icon patient-menu-link__icon"
                                    :class="item.icon"
                                ></i>
                                <span class="patient-menu-link__label">{{
                                    item.label
                                }}</span>
                            </Link>
                        </li>
                    </ul>
                </li>
            </ul>
        </aside>

        <div class="layout-main-container">
            <div class="layout-main app-shell-page">
                <section class="page-card patient-header-card">
                    <div class="patient-header-card__row">
                        <div class="patient-header-card__copy">
                            <p class="stat-label patient-header-card__eyebrow">
                                Patient experience
                            </p>
                            <h1 class="panel-title patient-header-card__title">
                                {{ title ?? 'Your care dashboard' }}
                            </h1>
                        </div>

                        <div class="badge-live patient-header-card__badge">
                            Secure portal
                        </div>
                    </div>
                </section>

                <slot />
            </div>
        </div>

        <div class="layout-mask" @click="closeMobileMenu"></div>

        <Toast position="top-right" />
        <FlashToastBridge />
    </div>
</template>
