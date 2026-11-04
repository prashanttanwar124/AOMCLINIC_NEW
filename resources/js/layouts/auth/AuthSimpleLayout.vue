<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        portal?: 'patient' | 'staff';
    }>(),
    {
        title: '',
        description: '',
        portal: 'patient',
    },
);
</script>

<template>
    <div class="auth-page">
        <div class="auth-clean-shell">
            <div class="auth-clean-card">
                <div class="auth-topbar">
                    <Link :href="home()" class="auth-logo">
                        <span class="logo-text">
                            Clinic
                            <span class="logo-text-alt">Connect</span>
                        </span>
                    </Link>
                </div>

                <div class="auth-header-copy">
                    <h1>{{ title }}</h1>
                    <p v-if="description">{{ description }}</p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-clean-shell {
    width: min(100%, 27rem);
    margin: 0 auto;
}

.auth-clean-card {
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 14px;
    box-shadow: 0 12px 32px -8px rgba(15, 23, 42, 0.08);
    padding: 2.25rem;
}

@media (max-width: 480px) {
    .auth-clean-card {
        padding: 1.5rem;
    }
}

.auth-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-bottom: 1.25rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--surface-border);
}

.auth-logo .logo-text {
    font-size: 1.35rem;
}

.auth-header-copy {
    margin-bottom: 1.5rem;
}

.auth-header-copy h1 {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--text-color);
    line-height: 1.25;
}

.auth-header-copy p {
    margin: 0.35rem 0 0;
    font-size: 0.88rem;
    color: var(--text-secondary-color);
    line-height: 1.45;
}
</style>
