<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Registration Disabled',
        description: 'Self-registration is not enabled for clinic staff accounts.',
    },
});
</script>

<template>
    <Head title="Registration Disabled" />

    <div class="section-stack">
        <p style="margin: 0; color: var(--text-secondary-color); line-height: 1.6">
            New staff and doctor accounts are created directly by the clinic administrator from the User Management panel.
        </p>

        <Link :href="login()" class="link-button link-button--primary">
            Return to Staff Login
        </Link>
    </div>
</template>
