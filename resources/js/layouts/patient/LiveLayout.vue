<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { dashboard } from '@/routes/patient';
import { onMounted, onUnmounted, ref } from 'vue';

defineProps<{
    title?: string;
}>();

const formatTime = () => {
    return new Date().toLocaleTimeString('en-US', {
        timeZone: 'Asia/Kolkata',
        hour: 'numeric',
        minute: 'numeric',
        second: 'numeric',
        hour12: true
    });
};

const timeString = ref(formatTime());
let timer: any = null;

onMounted(() => {
    timer = setInterval(() => {
        timeString.value = formatTime();
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <div class="live-layout-wrapper">
        <Head :title="title ?? 'Live Queue Tracker'" />

        <!-- Ambient Background Glows -->
        <div class="ambient-glows">
            <div class="glow-sphere glow-1"></div>
            <div class="glow-sphere glow-2"></div>
            <div class="glow-sphere glow-3"></div>
        </div>

        <!-- Live Topbar -->
        <header class="live-topbar">
            <div class="live-topbar__left">
                <Link :href="$page.props.auth.patient ? dashboard() : '/'" class="live-topbar__logo">
                    <span class="logo-text">
                        Clinic <span class="logo-text-alt">Connect</span>
                        <span class="portal-badge">Live Status</span>
                    </span>
                </Link>
            </div>

            <div class="live-topbar__right">
                <!-- Live connection status badge -->
                <div class="status-indicator">
                    <span class="pulse-dot"></span>
                    <span class="status-label">Live Sync</span>
                </div>

                <!-- Live time -->
                <div class="live-time">
                    <i class="pi pi-clock"></i>
                    <span>{{ timeString }}</span>
                </div>

                <!-- Back to dashboard/home button based on auth status -->
                <Link :href="$page.props.auth.patient ? dashboard() : '/'" class="back-link">
                    <i class="pi pi-arrow-left"></i>
                    <span>{{ $page.props.auth.patient ? 'Back to Portal' : 'Back to Home' }}</span>
                </Link>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="live-main-container">
            <slot />
        </main>

        <!-- Live Footer -->
        <footer class="live-footer">
            <p>© {{ new Date().getFullYear() }} Clinic Connect. Automated Live Patient Queue Board. Updates automatically every 10s.</p>
        </footer>
    </div>
</template>

<style scoped>
.live-layout-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background-color: #f4f6fb;
    color: var(--text-color, #1e293b);
    font-family:
        'Plus Jakarta Sans',
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        sans-serif;
    position: relative;
    overflow-x: hidden;
}

/* Ambient Background Glows */
.ambient-glows {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    pointer-events: none;
    z-index: 0;
}

.glow-sphere {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
    opacity: 0.6;
}

.glow-1 {
    top: -10%;
    left: 10%;
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(15, 181, 186, 0.12) 0%, transparent 70%);
    animation: float-slow 22s infinite alternate ease-in-out;
}

.glow-2 {
    bottom: 5%;
    right: 5%;
    width: 550px;
    height: 550px;
    background: radial-gradient(circle, rgba(14, 165, 233, 0.1) 0%, transparent 70%);
    animation: float-slow-reverse 28s infinite alternate ease-in-out;
}

.glow-3 {
    top: 35%;
    right: 15%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.05) 0%, transparent 70%);
    animation: float-medium 18s infinite alternate ease-in-out;
}

@keyframes float-slow {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(50px, 80px) scale(1.08); }
}

@keyframes float-slow-reverse {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(-60px, -50px) scale(0.92); }
}

@keyframes float-medium {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(30px, -40px) scale(1.05); }
}

.live-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 2rem;
    background: var(--surface-card, #ffffff);
    border-bottom: 1px solid var(--surface-border, rgba(226, 232, 240, 0.8));
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}

.live-topbar__logo {
    text-decoration: none;
    color: inherit;
    transition: transform 0.2s ease;
}

.live-topbar__logo:hover {
    transform: scale(1.02);
}

.logo-text {
    font-weight: 800;
    font-size: 1.35rem;
    letter-spacing: -0.03em;
    color: var(--primary-color, #0fb5ba);
}

.logo-text-alt {
    color: var(--text-color, #0f172a);
    font-weight: 500;
}

.portal-badge {
    margin-left: 0.5rem;
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    background: color-mix(in srgb, var(--primary-color, #0fb5ba) 8%, transparent);
    color: var(--primary-color, #0fb5ba);
    border: 1px solid color-mix(in srgb, var(--primary-color, #0fb5ba) 12%, transparent);
}

.live-topbar__right {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(16, 185, 129, 0.06);
    border: 1px solid rgba(16, 185, 129, 0.15);
    padding: 0.35rem 0.8rem;
    border-radius: 9999px;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.02);
}

.pulse-dot {
    width: 7px;
    height: 7px;
    background-color: #10b981;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45);
    }
    70% {
        transform: scale(1.05);
        box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

.status-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: #10b981;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.live-time {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
    background: rgba(15, 181, 186, 0.04);
    padding: 0.35rem 0.8rem;
    border-radius: 9999px;
    border: 1px solid rgba(15, 181, 186, 0.1);
    box-shadow: 0 2px 8px rgba(15, 181, 186, 0.01);
}

.live-time i {
    font-size: 0.8rem;
    color: #0fb5ba;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--text-secondary-color, #475569);
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: color 0.15s ease;
}

.back-link:hover {
    color: var(--text-color, #1e293b);
}

.back-link i {
    font-size: 0.85rem;
}

.live-main-container {
    flex: 1;
    padding: 2.5rem 2rem;
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    box-sizing: border-box;
    position: relative;
    z-index: 1;
}

.live-footer {
    padding: 1.5rem;
    text-align: center;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    position: relative;
    z-index: 1;
}

.live-footer p {
    margin: 0;
    font-size: 0.75rem;
    color: #64748b;
}

@media (max-width: 768px) {
    .live-topbar {
        padding: 1rem;
        flex-direction: column;
        gap: 0.75rem;
        align-items: stretch;
    }

    .live-topbar__left {
        text-align: center;
    }

    .live-topbar__right {
        justify-content: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .live-main-container {
        padding: 1.5rem 1rem;
    }
}
</style>
