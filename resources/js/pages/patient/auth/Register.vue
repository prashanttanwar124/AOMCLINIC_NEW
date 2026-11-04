<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { login } from '@/routes/patient';

defineOptions({
    layout: {
        title: 'Registration Disabled',
        description: 'Patient portal accounts are registered by the clinic reception.',
    },
});
</script>

<template>
    <Head title="Registration Disabled" />

    <div class="section-stack">
        <p style="margin: 0; color: var(--text-secondary-color); line-height: 1.6">
            New patient profiles are added directly by the clinic team at reception. Once registered, you can sign in using your email and password.
        </p>

        <Link :href="login()" class="link-button link-button--primary">
            Return to Patient Login
        </Link>
    </div>
</template>
