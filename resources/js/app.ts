import { createInertiaApp, router } from '@inertiajs/vue3';
import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import { initializeTheme } from '@/composables/useAppearance';
import AdminLayout from '@/layouts/admin/AppLayout.vue';
import AdminSettingsLayout from '@/layouts/admin/settings/Layout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PatientAuthLayout from '@/layouts/patient/AuthLayout.vue';
import PatientPortalLayout from '@/layouts/patient/PortalLayout.vue';
import LiveLayout from '@/layouts/patient/LiveLayout.vue';
import { configureEcho } from '@laravel/echo-vue';

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT) : 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT) : 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const MediCare = definePreset(Aura, {
    primitive: {
        teal: {
            50: '#eafafa',
            100: '#d6f3f4',
            200: '#aee7e9',
            300: '#6fdcdf',
            400: '#38cfd3',
            500: '#0fb5ba',
            600: '#0e8e92',
            700: '#0c6e72',
            800: '#0b585b',
            900: '#0a4749',
            950: '#062c2e',
        },
    },
    semantic: {
        primary: {
            50: '{teal.50}',
            100: '{teal.100}',
            200: '{teal.200}',
            300: '{teal.300}',
            400: '{teal.400}',
            500: '{teal.500}',
            600: '{teal.600}',
            700: '{teal.700}',
            800: '{teal.800}',
            900: '{teal.900}',
            950: '{teal.950}',
        },
    },
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'admin/appointments/Receipt':
                return null;
            case name === 'admin/medical-certificates/Print':
                return null;
            case name === 'patient/LiveStatus':
                return LiveLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('patient/auth/'):
                return PatientAuthLayout;
            case name.startsWith('patient/'):
                return PatientPortalLayout;
            case name.startsWith('admin/settings/'):
                return [AdminLayout, AdminSettingsLayout];
            case name.startsWith('admin/'):
                return AdminLayout;
            default:
                return AdminLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
    withApp(app) {
        app.use(PrimeVue, {
            theme: {
                preset: MediCare,
                options: {
                    darkModeSelector: '.app-dark',
                },
            },
        });

        app.use(ToastService);
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// Prevent double-clicks / concurrent navigations
let isNavigating = false;

router.on('before', (event) => {
    if (isNavigating) {
        event.preventDefault();
    } else {
        isNavigating = true;
    }
});

router.on('finish', () => {
    isNavigating = false;
});
