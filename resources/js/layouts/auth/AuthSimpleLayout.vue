<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="auth-page">
        <div class="auth-shell">
            <div class="auth-card">
                <aside class="auth-card__showcase">
                    <span class="auth-showcase-kicker"
                        >AOM Clinic Portal</span
                    >
                    <h2 class="auth-showcase-title">
                        Your health, simplified and connected.
                    </h2>
                    <p class="auth-showcase-copy">
                        Access your online patient records, schedule appointments, track your medicine orders, and connect with your practitioner securely.
                    </p>

                    <div class="auth-showcase-stats">
                        <div class="auth-showcase-stat">
                            <span
                                class="stat-label"
                                style="color: rgba(255, 255, 255, 0.72)"
                            >
                                Booking
                            </span>
                            <strong>24/7</strong>
                            <span>Online appointment scheduling</span>
                        </div>
                        <div class="auth-showcase-stat">
                            <span
                                class="stat-label"
                                style="color: rgba(255, 255, 255, 0.72)"
                            >
                                Live Queue
                            </span>
                            <strong>Real-time</strong>
                            <span>Track your status and wait times</span>
                        </div>
                    </div>

                    <ul class="auth-showcase-list">
                        <li>
                            <i class="pi pi-calendar"></i>
                            <span
                                >Book appointments at your convenience</span
                            >
                        </li>
                        <li>
                            <i class="pi pi-send"></i>
                            <span
                                >Get your prescribed medicines delivered directly to your doorstep</span
                            >
                        </li>
                        <li>
                            <i class="pi pi-heart"></i>
                            <span
                                >Personal care, vitals tracking, and history in one portal</span
                            >
                        </li>
                    </ul>
                </aside>

                <section class="auth-card__content">
                    <div class="auth-brand">
                        <Link :href="home()" class="auth-logo">
                            <span class="logo-text">Clinic <span class="logo-text-alt">Connect</span></span>
                            <span class="visually-hidden">{{ title }}</span>
                        </Link>

                        <div>
                            <h1>{{ title }}</h1>
                            <p>{{ description }}</p>
                        </div>
                    </div>

                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
