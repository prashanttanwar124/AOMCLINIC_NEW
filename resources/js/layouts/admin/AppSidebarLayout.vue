<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import { computed, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import FlashToastBridge from '@/components/FlashToastBridge.vue';
import { booking, dashboard, logout } from '@/routes';
import {
    appointments,
    medicines,
    patients,
    medicineTracking,
    vitalsTracking,
    courierParcels,
    medicalCertificates,
    users,
    roles as rolesRoute,
    bookingSettings,
    clinicSettings,
} from '@/routes/admin';
import { book as bookRoute } from '@/routes/admin/appointments';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const mobileMenuActive = ref(false);
const staticMenuInactive = ref(false);

const user = computed(() => page.props.auth.staff!);
const appName = computed(() => page.props.name ?? 'Clinic Connect');
const currentPath = computed(() => page.url);

const userInitials = computed(() => {
    if (!user.value?.name) return 'ST';
    const parts = user.value.name.split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts[0].substring(0, 2).toUpperCase();
});

const permissions = computed(
    () => (page.props.auth.permissions as string[]) || [],
);
const roles = computed(() => (page.props.auth.roles as string[]) || []);
const canManageStaff = computed(
    () =>
        permissions.value.includes('manage staff') ||
        roles.value.includes('admin'),
);

const menuItems = computed(() => [
    {
        label: 'Operations',
        items: [
            { label: 'Dashboard', icon: 'pi pi-home', href: dashboard().url },
            {
                label: 'Booking Desk',
                icon: 'pi pi-desktop',
                href: booking().url,
            },
            {
                label: 'Book Appointment',
                icon: 'pi pi-calendar-plus',
                href: bookRoute().url,
            },
            {
                label: 'All Appointments',
                icon: 'pi pi-calendar',
                href: appointments().url,
            },
        ],
    },
    {
        label: 'Patient Care',
        items: [
            {
                label: 'Vitals Tracking',
                icon: 'pi pi-heart',
                href: vitalsTracking().url,
            },
            {
                label: 'Medicine Tracking',
                icon: 'pi pi-check-square',
                href: medicineTracking().url,
            },
            {
                label: 'Courier Parcels',
                icon: 'pi pi-send',
                href: courierParcels().url,
            },
            {
                label: 'Medical Certificates',
                icon: 'pi pi-file',
                href: medicalCertificates().url,
            },
        ],
    },
    {
        label: 'Records',
        items: [
            {
                label: 'Patients',
                icon: 'pi pi-users',
                href: patients().url,
            },
            {
                label: 'Medicines',
                icon: 'pi pi-box',
                href: medicines().url,
            },
        ],
    },
    {
        label: 'Workspace',
        items: [
            { label: 'Profile', icon: 'pi pi-user', href: editProfile().url },
            {
                label: 'Security',
                icon: 'pi pi-shield',
                href: editSecurity().url,
            },
            {
                label: 'Appearance',
                icon: 'pi pi-palette',
                href: editAppearance().url,
            },
        ],
    },
    ...(canManageStaff.value
        ? [
              {
                  label: 'System',
                  items: [
                      {
                          label: 'Users',
                          icon: 'pi pi-users',
                          href: users().url,
                      },
                      {
                          label: 'Roles & Permissions',
                          icon: 'pi pi-key',
                          href: rolesRoute().url,
                      },
                      {
                          label: 'Booking Settings',
                          icon: 'pi pi-cog',
                          href: bookingSettings().url,
                      },
                      {
                          label: 'Clinic Settings',
                          icon: 'pi pi-building',
                          href: clinicSettings().url,
                      },
                  ],
              },
          ]
        : []),
]);

const isItemActive = (href: string): boolean => {
    if (href === '/') {
        return currentPath.value === '/';
    }
    try {
        const origin =
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost';
        const currentUrlObj = new URL(currentPath.value, origin);
        const itemUrlObj = new URL(href, origin);

        const currentPathname = currentUrlObj.pathname.replace(/\/+$/, '');
        const itemPathname = itemUrlObj.pathname.replace(/\/+$/, '');

        if (currentPathname === itemPathname) {
            return true;
        }

        if (itemPathname === '/appointments' && currentPathname.startsWith('/appointments/book')) {
            return false;
        }

        return currentPathname.startsWith(itemPathname + '/');
    } catch (e) {
        return false;
    }
};

const activeMenuItem = computed(() => {
    return menuItems.value
        .flatMap((group) => group.items)
        .find((item) => isItemActive(item.href));
});

const pageSubtitle = computed(() => {
    const active = activeMenuItem.value?.label;
    switch (active) {
        case 'Dashboard':
            return "Overview of clinic metrics, today's queue, and quick actions.";
        case 'Booking Desk':
            return 'Manage day-to-day clinic operations with a cleaner admin shell.';
        case 'Book Appointment':
            return 'Schedule a new appointment directly for a clinic patient.';
        case 'All Appointments':
            return 'Search, filter, and review all patient appointments.';
        case 'Medicine Tracking':
            return 'Monitor and track medicine distribution and queue status.';
        case 'Vitals Tracking':
            return "Record patient vitals and basic clinical details for today's queue.";
        case 'Patients':
            return 'Manage patient records, profiles, and history.';
        case 'Medicines':
            return 'Manage medicine inventory, categories, and stock sizes.';
        case 'Courier Parcels':
            return 'Manage courier parcel shipments, statuses, and payment records.';
        case 'Medical Certificates':
            return 'Manage medical certificate types, default charges, and issue new patient certificates.';
        case 'Profile':
            return 'Update your personal profile information.';
        case 'Security':
            return 'Manage your account security and password.';
        case 'Appearance':
            return 'Customize the look and feel of the clinic interface.';
        case 'Users':
            return 'Manage clinic staff users and access permissions.';
        case 'Roles & Permissions':
            return 'Manage user roles and authorization permissions.';
        case 'Booking Settings':
            return 'Configure online slot capacities, opening hours, and scheduled clinic closures.';
        case 'Clinic Settings':
            return 'Configure clinic contact details, address, registration info, and receipt logo.';
        default:
            return 'Manage day-to-day clinic operations with a cleaner admin shell.';
    }
});

const containerClass = computed(() => ({
    'layout-static': true,
    'layout-static-inactive': staticMenuInactive.value,
    'layout-mobile-active': mobileMenuActive.value,
    'admin-shell': true,
}));

const isActiveRoute = (href: string): boolean => isItemActive(href);

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
                        >Clinic <span class="logo-text-alt">Connect</span></span
                    >
                </Link>
            </div>

            <div class="layout-topbar-actions">
                <div class="layout-topbar-menu-content admin-topbar-panel">
                    <div class="admin-topbar-workspace-badge">
                        <i class="pi pi-briefcase badge-icon"></i>
                        <div class="badge-content">
                            <span class="badge-label">Workspace</span>
                            <span class="badge-value">{{
                                activeMenuItem?.label ?? 'Operations'
                            }}</span>
                        </div>
                    </div>

                    <div class="admin-topbar-user-profile">
                        <div class="user-avatar">
                            {{ userInitials }}
                        </div>
                        <div class="user-info">
                            <span class="user-role">Signed In</span>
                            <span class="user-name">{{ user.name }}</span>
                        </div>
                    </div>

                    <div class="admin-topbar-actions">
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

        <aside class="layout-sidebar admin-sidebar-surface">
            <ul class="layout-menu admin-sidebar-menu">
                <li
                    v-for="group in menuItems"
                    :key="group.label"
                    class="layout-root-menuitem"
                >
                    <div
                        class="layout-menuitem-root-text admin-sidebar-menu__group-label"
                    >
                        {{ group.label }}
                    </div>
                    <ul class="admin-sidebar-menu__list">
                        <li v-for="item in group.items" :key="item.href">
                            <Link
                                :href="item.href"
                                class="admin-sidebar-menu__link"
                                :class="{
                                    'active-route admin-sidebar-menu__link--active':
                                        isActiveRoute(item.href),
                                }"
                                @click="closeMobileMenu"
                            >
                                <i
                                    class="layout-menuitem-icon admin-sidebar-menu__icon"
                                    :class="item.icon"
                                ></i>
                                <span class="admin-sidebar-menu__label">{{
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
                <section
                    class="page-card admin-page-header admin-page-header-card"
                >
                    <div
                        class="admin-page-header__copy admin-page-header-card__copy"
                    >
                        <Breadcrumbs
                            v-if="props.breadcrumbs.length"
                            :breadcrumbs="props.breadcrumbs"
                            class="admin-page-header__breadcrumbs"
                        />
                        <h1
                            class="panel-title admin-page-header__title admin-page-header-card__title"
                        >
                            {{ activeMenuItem?.label ?? 'Dashboard' }}
                        </h1>
                        <p
                            class="panel-subtitle admin-page-header-card__subtitle"
                        >
                            {{ pageSubtitle }}
                        </p>
                    </div>
                    <div
                        id="admin-header-actions"
                        class="admin-page-header__actions"
                    ></div>
                </section>

                <slot />
            </div>
        </div>

        <div class="layout-mask" @click="closeMobileMenu"></div>

        <Toast position="top-right" />
        <FlashToastBridge />
    </div>
</template>
