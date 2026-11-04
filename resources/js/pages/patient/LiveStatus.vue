<script setup lang="ts">
import { computed, watch, onMounted, onUnmounted, ref } from 'vue';
import { useEchoPublic } from '@laravel/echo-vue';
import { router } from '@inertiajs/vue3';
import Tag from 'primevue/tag';

type QueueItem = {
    token: string;
    sequence: number;
    status: 'running' | 'pending' | 'on_hold' | 'complete';
    isPatient: boolean;
};

type PatientApt = {
    id: number;
    patientName?: string | null;
    token: string;
    session: 'Morning' | 'Evening';
    status: 'running' | 'pending' | 'on_hold' | 'complete';
    queuePosition: number;
    onHold: boolean;
};

const props = defineProps<{
    today: string;
    todayFormatted: string;
    currentSession: 'Morning' | 'Evening';
    morningTimings: string | null;
    eveningTimings: string | null;
    isMorningClosed: boolean;
    isEveningClosed: boolean;
    noticeEnabled: boolean;
    noticeText: string | null;
    morningRunningToken: string | null;
    eveningRunningToken: string | null;
    morningQueue: QueueItem[];
    eveningQueue: QueueItem[];
    patientAppointmentToday: PatientApt | null;
    patientAppointmentsToday?: PatientApt[];
}>();

const selectedSession = ref<'Morning' | 'Evening'>(props.currentSession);
const selectedPatientAptIndex = ref(0);

watch(
    () => props.currentSession,
    (newSession) => {
        selectedSession.value = newSession;
    },
);

const allPatientAppointments = computed<PatientApt[]>(() => {
    if (props.patientAppointmentsToday && props.patientAppointmentsToday.length > 0) {
        return props.patientAppointmentsToday;
    }
    return props.patientAppointmentToday ? [props.patientAppointmentToday] : [];
});

const activePatientAppointment = computed<PatientApt | null>(() => {
    return (
        allPatientAppointments.value[selectedPatientAptIndex.value] ??
        allPatientAppointments.value[0] ??
        null
    );
});

// Listen to real-time updates via Laravel Reverb
useEchoPublic('live-queue', 'QueueUpdated', () => {
    router.reload({
        only: [
            'currentSession',
            'morningTimings',
            'eveningTimings',
            'isMorningClosed',
            'isEveningClosed',
            'noticeEnabled',
            'noticeText',
            'morningRunningToken',
            'eveningRunningToken',
            'morningQueue',
            'eveningQueue',
            'patientAppointmentToday',
            'patientAppointmentsToday',
        ],
    });
});

const activeQueue = computed(() => {
    return selectedSession.value === 'Morning' ? props.morningQueue : props.eveningQueue;
});

const runningToken = computed(() => {
    return selectedSession.value === 'Morning' ? props.morningRunningToken : props.eveningRunningToken;
});

const activeTimings = computed(() => {
    return selectedSession.value === 'Morning' ? props.morningTimings : props.eveningTimings;
});

const isActiveSessionClosed = computed(() => {
    return selectedSession.value === 'Morning' ? props.isMorningClosed : props.isEveningClosed;
});

const timeToMinutes = (timeStr: string): number => {
    const [time, modifier] = timeStr.trim().split(' ');
    let [hours, minutes] = time.split(':').map(Number);
    if (hours === 12) {
        hours = 0;
    }
    if (modifier?.toUpperCase() === 'PM') {
        hours += 12;
    }
    return hours * 60 + (minutes || 0);
};

const getMsUntilTransition = (): number | null => {
    const timings = props.currentSession === 'Morning' ? props.morningTimings : props.eveningTimings;
    if (!timings) return null;
    
    try {
        const parts = timings.split('-');
        const endStr = parts[1]?.trim();
        if (!endStr) return null;
        
        const endMinutes = timeToMinutes(endStr);
        
        // Get current time in Asia/Kolkata
        const nowString = new Date().toLocaleTimeString('en-US', {
            timeZone: 'Asia/Kolkata',
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        const [nowHours, nowMins, nowSecs] = nowString.split(':').map(Number);
        const currentMinutes = nowHours * 60 + nowMins + nowSecs / 60;
        
        let diffMinutes = endMinutes - currentMinutes;
        if (diffMinutes <= 0) {
            // Already past the end time
            return null;
        }
        
        return Math.round(diffMinutes * 60 * 1000);
    } catch (e) {
        console.error('Error calculating transition delay:', e);
        return null;
    }
};

let transitionTimeoutId: ReturnType<typeof setTimeout> | null = null;

const scheduleSessionTransition = () => {
    if (transitionTimeoutId) {
        clearTimeout(transitionTimeoutId);
        transitionTimeoutId = null;
    }
    
    const delay = getMsUntilTransition();
    if (delay !== null && delay > 0) {
        transitionTimeoutId = setTimeout(() => {
            router.reload({
                only: [
                    'currentSession',
                    'morningTimings',
                    'eveningTimings',
                    'isMorningClosed',
                    'isEveningClosed',
                    'noticeEnabled',
                    'noticeText',
                    'morningRunningToken',
                    'eveningRunningToken',
                    'morningQueue',
                    'eveningQueue',
                    'patientAppointmentToday'
                ]
            });
        }, delay);
    }
};

// Schedule on mount, and reschedule whenever session or timings change
watch(
    () => [props.currentSession, props.morningTimings, props.eveningTimings],
    () => {
        scheduleSessionTransition();
    },
    { immediate: true }
);

const getKolkataMinutes = (): number => {
    const nowString = new Date().toLocaleTimeString('en-US', {
        timeZone: 'Asia/Kolkata',
        hour12: false,
        hour: '2-digit',
        minute: '2-digit'
    });
    const [nowHours, nowMins] = nowString.split(':').map(Number);
    return nowHours * 60 + nowMins;
};

const nowMinutes = ref<number>(getKolkataMinutes());

const updateNowMinutes = () => {
    nowMinutes.value = getKolkataMinutes();
};

let timeIntervalId: ReturnType<typeof setTimeout> | null = null;
onMounted(() => {
    updateNowMinutes();
    timeIntervalId = setInterval(updateNowMinutes, 10000);
});

const morningStartMinutes = computed(() => {
    if (!props.morningTimings) return 9 * 60; // default 9:00 AM
    const parts = props.morningTimings.split('-');
    const startStr = parts[0]?.trim();
    return startStr ? timeToMinutes(startStr) : 9 * 60;
});

const morningStartFormatted = computed(() => {
    if (!props.morningTimings) return '9:00 AM';
    return props.morningTimings.split('-')[0]?.trim() || '9:00 AM';
});

const morningEndMinutes = computed(() => {
    if (!props.morningTimings) return 12 * 60; // 12:00 PM
    const parts = props.morningTimings.split('-');
    const endStr = parts[1]?.trim();
    return endStr ? timeToMinutes(endStr) : 12 * 60;
});

const eveningStartMinutes = computed(() => {
    if (!props.eveningTimings) return 16 * 60; // 4:00 PM
    const parts = props.eveningTimings.split('-');
    const startStr = parts[0]?.trim();
    return startStr ? timeToMinutes(startStr) : 16 * 60;
});

const eveningEndMinutes = computed(() => {
    if (!props.eveningTimings) return 20 * 60; // 8:00 PM
    const parts = props.eveningTimings.split('-');
    const endStr = parts[1]?.trim();
    return endStr ? timeToMinutes(endStr) : 20 * 60;
});

const eveningStartFormatted = computed(() => {
    if (!props.eveningTimings) return '4:00 PM';
    return props.eveningTimings.split('-')[0]?.trim() || '4:00 PM';
});

const viewState = computed(() => {
    // 1. Check if the current session is closed today in settings
    if (props.currentSession === 'Morning' && props.isMorningClosed) {
        return 'closed_session';
    }
    if (props.currentSession === 'Evening' && props.isEveningClosed) {
        return 'closed_session';
    }

    const current = nowMinutes.value;
    
    // 2. Before morning session starts
    const mStart = morningStartMinutes.value;
    if (mStart !== null && current < mStart) {
        return 'before_start';
    }
    
    // 3. Gap between sessions
    const mEnd = morningEndMinutes.value;
    const eStart = eveningStartMinutes.value;
    if (mEnd !== null && eStart !== null && current > mEnd && current < eStart) {
        return 'session_gap';
    }
    
    // 4. Evening session concluded
    const eEnd = eveningEndMinutes.value;
    if (eEnd !== null && current > eEnd) {
        return 'concluded';
    }
    
    return 'active';
});

onUnmounted(() => {
    if (transitionTimeoutId) {
        clearTimeout(transitionTimeoutId);
    }
    if (timeIntervalId) {
        clearInterval(timeIntervalId);
    }
});

const averageTimePerPatient = computed(() => {
    if (!activeTimings.value) {
        return 8; // fallback default
    }
    
    try {
        const [startStr, endStr] = activeTimings.value.split('-');
        if (!startStr || !endStr) return 8;
        
        const startMinutes = timeToMinutes(startStr);
        const endMinutes = timeToMinutes(endStr);
        
        // Get current time in Asia/Kolkata
        const nowString = new Date().toLocaleTimeString('en-US', {
            timeZone: 'Asia/Kolkata',
            hour12: false,
            hour: '2-digit',
            minute: '2-digit'
        });
        const [nowHours, nowMins] = nowString.split(':').map(Number);
        const currentMinutes = nowHours * 60 + nowMins;
        
        let remainingMinutes = 0;
        if (currentMinutes < startMinutes) {
            remainingMinutes = endMinutes - startMinutes;
        } else if (currentMinutes <= endMinutes) {
            remainingMinutes = endMinutes - currentMinutes;
        } else {
            remainingMinutes = 15; // clinic is running late
        }
        
        const pendingCount = activeQueue.value.filter(q => q.status === 'pending').length;
        if (pendingCount === 0) {
            return 0;
        }
        
        let rawAvg = remainingMinutes / pendingCount;
        
        if (currentMinutes > endMinutes) {
            return 5; // safe fallback for running late
        }
        
        return Math.max(4, Math.min(12, Math.round(rawAvg)));
    } catch (e) {
        console.error('Error calculating average queue time:', e);
        return 8;
    }
});

const getExpectedTime = (waitMinutes: number): string => {
    const now = new Date();
    const expectedDate = new Date(now.getTime() + (waitMinutes || 0) * 60 * 1000);
    return expectedDate.toLocaleTimeString('en-US', {
        timeZone: 'Asia/Kolkata',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
};

function formatToken(token: string | number): string {
    const num = Number(token);
    return isNaN(num) ? String(token) : num.toString().padStart(2, '0');
}

function getStatusLabel(status: QueueItem['status'] | PatientApt['status']): string {
    return {
        complete: 'Completed',
        on_hold: 'On Hold',
        pending: 'Waiting',
        running: 'Serving Now'
    }[status];
}

function getStatusSeverity(status: QueueItem['status'] | PatientApt['status']): 'success' | 'warn' | 'danger' | 'contrast' {
    switch (status) {
        case 'complete': return 'success';
        case 'on_hold': return 'danger';
        case 'pending': return 'warn';
        case 'running': return 'contrast';
    }
}
</script>

<template>
    <div class="live-status-container">
        
        <!-- Special Notice Alert Banner -->
        <div v-if="noticeEnabled && noticeText" class="special-notice-banner animate-slide-down">
            <i class="pi pi-info-circle notice-icon"></i>
            <div class="notice-content">
                <span class="notice-badge">Notice</span>
                <p class="notice-message">{{ noticeText }}</p>
            </div>
        </div>

        <!-- Session Closed or Outside Regular Hours Banner -->
        <div v-if="isActiveSessionClosed" class="special-notice-banner animate-slide-down">
            <i class="pi pi-ban notice-icon"></i>
            <div class="notice-content">
                <span class="notice-badge">Closed Today</span>
                <p class="notice-message">
                    The <strong>{{ selectedSession }} Session</strong> is closed today due to a scheduled clinic holiday or closure.
                </p>
            </div>
        </div>
        <div v-else-if="viewState !== 'active' && activeQueue.length === 0" class="other-session-banner animate-slide-down">
            <i class="pi pi-clock reminder-icon"></i>
            <div class="reminder-content">
                <span class="reminder-badge">Clinic Schedule</span>
                <p class="reminder-message">
                    <template v-if="viewState === 'before_start'">
                        Morning Session starts at <strong>{{ morningStartFormatted }}</strong> ({{ morningTimings || 'Scheduled' }}).
                    </template>
                    <template v-else-if="viewState === 'session_gap'">
                        Morning Session has concluded. Evening Session starts at <strong>{{ eveningStartFormatted }}</strong> ({{ eveningTimings || 'Scheduled' }}).
                    </template>
                    <template v-else>
                        Regular clinic consultation hours for today have concluded. Morning: {{ morningTimings || 'N/A' }} · Evening: {{ eveningTimings || 'N/A' }}.
                    </template>
                </p>
            </div>
        </div>

        <!-- Other Session Appointment Reminder Banner -->
        <div v-if="activePatientAppointment && activePatientAppointment.session !== selectedSession" class="other-session-banner animate-slide-down">
            <i class="pi pi-calendar-times reminder-icon"></i>
            <div class="reminder-content">
                <span class="reminder-badge">Session Notice</span>
                <p class="reminder-message">
                    You have Token <strong>{{ formatToken(activePatientAppointment.token) }}</strong> booked in the
                    <strong>{{ activePatientAppointment.session }} Session</strong> today.
                    <button
                        type="button"
                        class="switch-session-inline-btn"
                        @click="selectedSession = activePatientAppointment.session"
                    >
                        Switch to {{ activePatientAppointment.session }} Queue →
                    </button>
                </p>
            </div>
        </div>
        
        <!-- Header & Stats Dashboard Widget (Always Visible) -->
        <header class="dashboard-header-card animate-slide-down">
            <div class="session-info">
                <span class="session-indicator-badge" :class="selectedSession.toLowerCase()">
                    {{ selectedSession[0] }}
                </span>
                <div class="session-details">
                    <div class="session-title-with-tabs">
                        <h1>{{ selectedSession }} Session</h1>
                        <div class="live-session-tabs" role="tablist">
                            <button
                                type="button"
                                class="live-session-tab"
                                :class="{ 'is-active': selectedSession === 'Morning' }"
                                @click="selectedSession = 'Morning'"
                            >
                                <i class="pi pi-sun"></i>
                                Morning ({{ props.morningQueue.length }})
                            </button>
                            <button
                                type="button"
                                class="live-session-tab"
                                :class="{ 'is-active': selectedSession === 'Evening' }"
                                @click="selectedSession = 'Evening'"
                            >
                                <i class="pi pi-moon"></i>
                                Evening ({{ props.eveningQueue.length }})
                            </button>
                        </div>
                    </div>
                    <span class="timings">
                        <i class="pi pi-calendar"></i> {{ todayFormatted }}
                        <span class="timing-divider" v-if="activeTimings">|</span>
                        <i class="pi pi-clock" v-if="activeTimings"></i> {{ activeTimings }}
                        <span v-if="isActiveSessionClosed" class="closed-session-pill">Closed Today</span>
                    </span>
                </div>
            </div>
            
            <div class="quick-stats-grid">
                <div class="quick-stat-item">
                    <div class="stat-icon-wrapper waiting">
                        <i class="pi pi-users"></i>
                    </div>
                    <div class="stat-text">
                        <span class="stat-val">{{ activeQueue.filter(q => q.status === 'pending').length }}</span>
                        <span class="stat-lbl">Waiting Queue</span>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="stat-icon-wrapper timer">
                        <i class="pi pi-check-circle"></i>
                    </div>
                    <div class="stat-text">
                        <span class="stat-val">{{ activeQueue.filter(q => q.status === 'complete').length }}</span>
                        <span class="stat-lbl">Completed</span>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="stat-icon-wrapper room">
                        <i class="pi pi-hourglass"></i>
                    </div>
                    <div class="stat-text">
                        <span class="stat-val">~{{ getExpectedTime(activeQueue.filter(q => q.status === 'pending').length * averageTimePerPatient) }}</span>
                        <span class="stat-lbl">Est. Completion</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Dashboard Columns Grid (Always Visible) -->
        <div class="live-dashboard-grid" :class="{ 'has-appointment': activePatientAppointment }">
            
            <!-- Column 1: Patient's Personal Status Card (Only if they have an appointment) -->
            <section v-if="activePatientAppointment" class="personal-status-card animate-fade-in-left">
                <div class="personal-status-header">
                    <div class="personal-status-indicator">
                        <span class="pulse-indicator"></span>
                        <h3>Your Live Coupon</h3>
                    </div>
                    <Tag
                        :value="getStatusLabel(activePatientAppointment.status)"
                        :severity="getStatusSeverity(activePatientAppointment.status)"
                        rounded
                        class="premium-tag"
                    />
                </div>

                <div v-if="allPatientAppointments.length > 1" class="personal-tokens-switcher">
                    <button
                        v-for="(apt, idx) in allPatientAppointments"
                        :key="apt.id"
                        type="button"
                        class="personal-token-chip"
                        :class="{ 'is-active': selectedPatientAptIndex === idx }"
                        @click="
                            selectedPatientAptIndex = idx;
                            selectedSession = apt.session;
                        "
                    >
                        Token #{{ formatToken(apt.token) }}
                        <span v-if="apt.patientName">({{ apt.patientName }})</span>
                    </button>
                </div>

                <div class="personal-status-body">
                    <!-- Premium coupon ticket look -->
                    <div class="personal-ticket">
                        <div class="ticket-header">
                           <span class="ticket-eyebrow">
                               {{ activePatientAppointment.patientName ? `${activePatientAppointment.patientName} · Entry Ticket` : 'Clinic Connect Entry Ticket' }}
                           </span>
                        </div>
                        
                        <div class="ticket-body">
                           <div class="ticket-number-wrapper">
                               <span class="ticket-number-label">Token</span>
                               <span class="ticket-number">
                                   {{ formatToken(activePatientAppointment.token) }}
                                </span>
                           </div>
                        </div>

                        <!-- Stylized CSS barcode -->
                        <div class="ticket-barcode-container">
                            <div class="ticket-barcode">
                                <div v-for="n in 32" :key="n" class="barcode-line" :style="{ width: [1, 2, 3][n % 3] + 'px', opacity: n % 5 === 0 ? 0.35 : 1 }"></div>
                            </div>
                            <span class="barcode-serial">CC-TKT-{{ formatToken(activePatientAppointment.token) }}-{{ today.replace(/-/g, '') }}</span>
                        </div>

                        <div class="ticket-footer">
                            <div class="ticket-meta-grid">
                                <div class="meta-item">
                                    <span class="meta-label">Active Session</span>
                                    <span class="meta-value">{{ activePatientAppointment.session }}</span>
                                </div>
                                <div class="meta-item border-left-meta">
                                    <span class="meta-label">Line Position</span>
                                    <span class="meta-value">
                                        <span v-if="activePatientAppointment.status === 'running'" class="status-running-text">
                                            Your Turn!
                                        </span>
                                        <span v-else-if="activePatientAppointment.status === 'complete'" class="status-complete-text">
                                            Completed
                                        </span>
                                        <span v-else-if="activePatientAppointment.onHold" class="status-hold-text">
                                            On Hold
                                        </span>
                                        <span v-else>
                                            #{{ activePatientAppointment.queuePosition + 1 }} in line
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic estimated waiting duration countdown -->
                    <div class="status-summary-bar" v-if="activePatientAppointment.status === 'pending' && !activePatientAppointment.onHold">
                        <div class="wait-lead-stat">
                            <span class="stat-number">{{ activePatientAppointment.queuePosition }}</span>
                            <span class="stat-desc">patients waiting ahead of you</span>
                        </div>
                        <div class="status-tip-alert">
                            <i class="pi pi-info-circle"></i>
                            <p>Please remain in the lobby area. We'll announce Token {{ formatToken(activePatientAppointment.token) }} at approximately {{ getExpectedTime(activePatientAppointment.queuePosition * averageTimePerPatient) }}.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Column 2 (or 1): Now Serving Display Panel (Kiosk TV monitor) -->
            <section class="kiosk-serving-panel animate-fade-in">
                <div class="serving-card">
                    <div class="running-content">
                        <div class="running-header">
                            <span class="card-eyebrow">NOW SERVING · {{ selectedSession.toUpperCase() }}</span>
                            <div class="soundwave">
                                <span v-for="n in 5" :key="n" class="soundwave-bar" :style="{ animationDelay: (n * 0.15) + 's' }"></span>
                            </div>
                        </div>
                        
                        <div v-if="isActiveSessionClosed" class="empty-serving">
                            Session Closed Today
                        </div>
                        <div v-else-if="runningToken" class="huge-token-number">
                            Token {{ formatToken(runningToken) }}
                        </div>
                        <div v-else class="empty-serving">
                            {{ activeQueue.length > 0 ? 'All Tokens Completed' : 'Waiting for First Patient' }}
                        </div>

                        <div class="serving-footer">
                            <div class="active-room-indicator">
                                <span class="dot-green"></span>
                                <span>Proceed to Consultation Room</span>
                            </div>
                            <span class="sync-clock">Live Sync Active</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Column 3 (or 2): Waiting Queue Pipeline Timeline -->
            <section class="queue-list-panel animate-fade-in-right">
                <div class="queue-list-card">
                    <div class="queue-card-header">
                        <h3>{{ selectedSession }} Waiting List</h3>
                        <span class="queue-count-badge">{{ activeQueue.filter(q => q.status === 'pending').length }} Waiting</span>
                    </div>

                    <!-- Capped and scrollable timeline list -->
                    <div class="queue-list-scroll-wrapper">
                        <div class="tokens-list">
                            <!-- Connector pipeline line -->
                            <div class="timeline-pipe"></div>

                            <div
                                v-for="(item, index) in activeQueue.filter(q => q.status === 'pending')"
                                :key="item.token"
                                class="token-row"
                                :class="{ 'is-own': item.isPatient }"
                            >
                                <div class="token-row-left">
                                    <span class="sequence-number">
                                        <span class="seq-num-text">{{ index + 1 }}</span>
                                        <span class="pulse-ring" v-if="item.isPatient"></span>
                                    </span>
                                    <div class="token-badge-info">
                                        <span class="token-badge">Token {{ formatToken(item.token) }}</span>
                                        <span class="token-wait-time" v-if="index > 0">Expected: {{ getExpectedTime(index * averageTimePerPatient) }}</span>
                                        <span class="token-wait-time next-in-line" v-else>Next in line</span>
                                    </div>
                                </div>
                                <span class="wait-status">
                                    {{ item.isPatient ? 'You' : 'Waiting' }}
                                </span>
                            </div>
                            
                            <div v-if="!activeQueue.some(q => q.status === 'pending')" class="empty-list-state">
                                No tokens are currently waiting in the {{ selectedSession.toLowerCase() }} line.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- On Hold List widget -->
                <div class="queue-list-card held-card-widget" v-if="activeQueue.some(q => q.status === 'on_hold')">
                    <div class="queue-card-header">
                        <h3 class="held-title">ON HOLD</h3>
                        <span class="queue-count-badge held-count">{{ activeQueue.filter(q => q.status === 'on_hold').length }} Paused</span>
                    </div>
                    
                    <div class="held-list-container">
                       <div
                           v-for="item in activeQueue.filter(q => q.status === 'on_hold')"
                           :key="item.token"
                           class="token-row held"
                           :class="{ 'is-own': item.isPatient }"
                       >
                           <div class="token-row-left">
                               <span class="held-indicator-icon"><i class="pi pi-pause-circle"></i></span>
                               <span class="token-badge">Token {{ formatToken(item.token) }}</span>
                           </div>
                           <span class="wait-status text-red-500">
                               {{ item.isPatient ? 'You' : 'On Hold' }}
                           </span>
                       </div>
                    </div>
                </div>
            </section>

        </div>

    </div>
</template>

<style scoped>
/* Animations */
.animate-slide-down {
    animation: slideDown 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.animate-fade-in {
    animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.animate-fade-in-left {
    animation: fadeInLeft 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.animate-fade-in-right {
    animation: fadeInRight 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInLeft {
    from { opacity: 0; transform: translateX(-30px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes fadeInRight {
    from { opacity: 0; transform: translateX(30px); }
    to { opacity: 1; transform: translateX(0); }
}

.live-status-container {
    display: flex;
    flex-direction: column;
    width: 100%;
    gap: 2rem;
}

/* Dashboard Header Card */
.dashboard-header-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(226, 232, 240, 0.7);
    border-radius: 24px;
    padding: 1.25rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.02);
}

.session-info {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.session-indicator-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    font-size: 1.25rem;
    font-weight: 800;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(15, 181, 186, 0.15);
}

.session-indicator-badge.morning {
    background: linear-gradient(135deg, #0fb5ba 0%, #06b6d4 100%);
}

.session-indicator-badge.evening {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.session-details h1 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.03em;
}

.session-details .timings {
    font-size: 0.8rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-top: 0.2rem;
    font-weight: 600;
}

.timing-divider {
    color: #cbd5e1;
    margin: 0 0.2rem;
}

/* Quick Stats Grid */
.quick-stats-grid {
    display: flex;
    gap: 1.25rem;
}

.quick-stat-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(226, 232, 240, 0.5);
    padding: 0.5rem 1.15rem;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.01);
}

.stat-icon-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    font-size: 0.95rem;
}

.stat-icon-wrapper.waiting {
    background: rgba(15, 181, 186, 0.08);
    color: #0fb5ba;
}

.stat-icon-wrapper.timer {
    background: rgba(14, 165, 233, 0.08);
    color: #0ea5e9;
}

.stat-icon-wrapper.room {
    background: rgba(99, 102, 241, 0.08);
    color: #6366f1;
}

.stat-text {
    display: flex;
    flex-direction: column;
}

.stat-val {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}

.stat-lbl {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

/* Immersive 3-Column / 2-Column Responsive Grid */
.live-dashboard-grid {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 2rem;
    width: 100%;
    align-items: start;
}

.live-dashboard-grid.has-appointment {
    grid-template-columns: 310px 340px 1fr;
}

@media (max-width: 1150px) {
    .live-dashboard-grid.has-appointment {
        grid-template-columns: 310px 1fr;
    }
}

@media (max-width: 1024px) {
    .dashboard-header-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.25rem;
        padding: 1.25rem;
    }
    
    .quick-stats-grid {
        width: 100%;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .quick-stat-item {
        flex: 1;
        min-width: 140px;
    }
    
    .live-dashboard-grid,
    .live-dashboard-grid.has-appointment {
        grid-template-columns: 1fr !important;
    }
}

/* Personal Ticket Card */
.personal-status-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(226, 232, 240, 0.7);
    border-radius: 24px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.02);
    position: sticky;
    top: 5.5rem;
    align-self: start;
}

.personal-status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.5);
    margin-bottom: 1.25rem;
}

.personal-status-indicator {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.personal-status-indicator h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
}

.pulse-indicator {
    width: 8px;
    height: 8px;
    background-color: #0fb5ba;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(15, 181, 186, 0.4);
    animation: pulse-teal 2s infinite;
}

@keyframes pulse-teal {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(15, 181, 186, 0.4); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(15, 181, 186, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(15, 181, 186, 0); }
}

.premium-tag {
    font-size: 0.65rem !important;
    font-weight: 800 !important;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.2rem 0.6rem !important;
}

/* Coupon Stub Ticket style */
.personal-ticket {
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.01);
    overflow: hidden;
    position: relative;
    margin-bottom: 1.25rem;
}

.personal-ticket::before,
.personal-ticket::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    background: #f4f6fb; /* match layout bg */
    border: 2px solid #e2e8f0;
    border-radius: 50%;
    z-index: 10;
}

.personal-ticket::before {
    left: -10px;
    bottom: 58px;
}

.personal-ticket::after {
    right: -10px;
    bottom: 58px;
}

.ticket-header {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    border-bottom: 1.5px dashed #cbd5e1;
    text-align: center;
}

.ticket-eyebrow {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #64748b;
}

.ticket-body {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem 1rem 0.75rem;
}

.ticket-number-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.ticket-number-label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
}

.ticket-number {
    font-size: 3.25rem;
    font-weight: 800;
    color: #0fb5ba;
    line-height: 1.1;
    letter-spacing: -0.04em;
    text-shadow: 0 4px 15px rgba(15, 181, 186, 0.15);
}

.ticket-barcode-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    padding: 0.5rem 1rem 1.15rem;
    border-bottom: 1.5px dashed #cbd5e1;
    margin-bottom: 2px;
}

.ticket-barcode {
    display: flex;
    align-items: stretch;
    height: 28px;
    gap: 2px;
    background: transparent;
}

.barcode-line {
    background-color: #1e293b;
    border-radius: 0.5px;
}

.barcode-serial {
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.62rem;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.05em;
}

.ticket-footer {
    background: #f8fafc;
    padding: 0.85rem 1rem;
}

.ticket-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    text-align: center;
}

.border-left-meta {
    border-left: 1px solid #e2e8f0;
}

.meta-item {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.meta-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #94a3b8;
}

.meta-value {
    font-size: 0.8rem;
    font-weight: 800;
    color: #1e293b;
}

.status-running-text {
    color: #10b981;
    font-weight: 800;
    animation: text-pulse-glow 1.5s infinite alternate ease-in-out;
}

.status-complete-text {
    color: #64748b;
}

.status-hold-text {
    color: #ef4444;
}

@keyframes text-pulse-glow {
    0% { opacity: 0.85; text-shadow: 0 0 4px rgba(16, 185, 129, 0.1); }
    100% { opacity: 1; text-shadow: 0 0 10px rgba(16, 185, 129, 0.3); }
}

.status-summary-bar {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.wait-lead-stat {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(14, 165, 233, 0.04);
    border: 1px solid rgba(14, 165, 233, 0.08);
    padding: 0.65rem 1rem;
    border-radius: 14px;
}

.wait-lead-stat .stat-number {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0ea5e9;
    line-height: 1;
}

.wait-lead-stat .stat-desc {
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
}

.status-tip-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    background: rgba(15, 181, 186, 0.03);
    border: 1px solid rgba(15, 181, 186, 0.08);
    border-radius: 14px;
    padding: 0.75rem 1rem;
}

.status-tip-alert i {
    color: #0fb5ba;
    font-size: 1rem;
    margin-top: 0.1rem;
}

.status-tip-alert p {
    margin: 0;
    font-size: 0.76rem;
    font-weight: 600;
    color: #475569;
    line-height: 1.4;
}

/* Now Serving display card (Kiosk TV style) */
.kiosk-serving-panel {
    display: flex;
    flex-direction: column;
    width: 100%;
}

.serving-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    border-radius: 24px;
    border: 1px solid rgba(15, 181, 186, 0.25);
    box-shadow: 0 20px 45px -15px rgba(15, 181, 186, 0.25), inset 0 1px 0 0 rgba(255, 255, 255, 0.1);
    overflow: hidden;
    position: relative;
}

.running-content {
    display: flex;
    flex-direction: column;
    padding: 2rem 1.75rem;
    min-height: 200px;
    justify-content: space-between;
}

.running-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 0.75rem;
    margin-bottom: 1.5rem;
}

.serving-card .card-eyebrow {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    color: #0fb5ba;
    text-shadow: 0 0 8px rgba(15, 181, 186, 0.25);
}

.huge-token-number {
    font-size: 4rem;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.03em;
    text-align: center;
    margin: 0.75rem 0;
    text-shadow: 0 0 15px rgba(15, 181, 186, 0.6), 0 0 35px rgba(15, 181, 186, 0.25);
    line-height: 1;
}

.empty-serving {
    font-size: 1.4rem;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.6);
    text-align: center;
    margin: 1.5rem 0;
    letter-spacing: -0.01em;
}

.serving-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 1rem;
    margin-top: 1.5rem;
}

.active-room-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    font-weight: 800;
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.25);
    color: #10b981;
    padding: 0.3rem 0.75rem;
    border-radius: 9999px;
}

.dot-green {
    width: 6px;
    height: 6px;
    background-color: #10b981;
    border-radius: 50%;
    box-shadow: 0 0 8px #10b981;
    animation: breathing-dot-green 1.8s infinite ease-in-out;
}

@keyframes breathing-dot-green {
    0% { transform: scale(0.9); opacity: 0.7; }
    50% { transform: scale(1.1); opacity: 1; box-shadow: 0 0 12px #10b981; }
    100% { transform: scale(0.9); opacity: 0.7; }
}

.sync-clock {
    font-size: 0.65rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.45);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Soundwave animations */
.soundwave {
    display: flex;
    align-items: center;
    gap: 3px;
    height: 14px;
}

.soundwave-bar {
    width: 2.5px;
    height: 100%;
    background-color: #0fb5ba;
    border-radius: 1.5px;
    animation: soundwave-rise-pulse 1.2s ease-in-out infinite alternate;
}

@keyframes soundwave-rise-pulse {
    0% { height: 20%; opacity: 0.5; }
    100% { height: 100%; opacity: 1; }
}

/* Waiting list section */
.queue-list-panel {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    width: 100%;
}

.queue-list-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(226, 232, 240, 0.7);
    border-radius: 24px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.02);
}

.queue-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(226, 232, 240, 0.5);
    padding-bottom: 0.85rem;
    margin-bottom: 1.25rem;
}

.queue-card-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.01em;
    border-left: 3px solid #0fb5ba;
    padding-left: 0.65rem;
    line-height: 1;
}

.queue-count-badge {
    font-size: 0.68rem;
    font-weight: 800;
    background: rgba(15, 181, 186, 0.08);
    color: #0fb5ba;
    border: 1px solid rgba(15, 181, 186, 0.15);
    padding: 0.25rem 0.6rem;
    border-radius: 8px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

/* Scroll wrapper cap */
.queue-list-scroll-wrapper {
    max-height: 520px;
    overflow-y: auto;
    padding: 0.5rem 0.5rem 0.5rem 1.25rem;
}

.queue-list-scroll-wrapper::-webkit-scrollbar {
    width: 5px;
}

.queue-list-scroll-wrapper::-webkit-scrollbar-track {
    background: rgba(241, 245, 249, 0.4);
    border-radius: 10px;
}

.queue-list-scroll-wrapper::-webkit-scrollbar-thumb {
    background: rgba(15, 181, 186, 0.2);
    border-radius: 10px;
}

.queue-list-scroll-wrapper::-webkit-scrollbar-thumb:hover {
    background: rgba(15, 181, 186, 0.4);
}

/* Connected queue timeline layout */
.tokens-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    position: relative;
}

.timeline-pipe {
    position: absolute;
    left: 29px;
    top: 1.5rem;
    bottom: 1.5rem;
    width: 2px;
    background: linear-gradient(180deg, #0fb5ba 0%, #cbd5e1 40%, #e2e8f0 100%);
    z-index: 0;
}

/* Token row widget */
.token-row {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 16px;
    padding: 0.85rem 1.25rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.01), 0 2px 4px -1px rgba(0, 0, 0, 0.01);
    z-index: 1;
    overflow: hidden;
}

.token-row:hover {
    transform: translateY(-2px) scale(1.005);
    box-shadow: 0 12px 22px -8px rgba(15, 181, 186, 0.08);
    border-color: rgba(15, 181, 186, 0.25);
}

.token-row::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 4px;
    background: #cbd5e1;
    border-top-left-radius: 16px;
    border-bottom-left-radius: 16px;
    opacity: 0.6;
}

.token-row.is-own {
    background: linear-gradient(135deg, rgba(15, 181, 186, 0.06) 0%, rgba(15, 181, 186, 0.01) 100%);
    border-color: rgba(15, 181, 186, 0.35);
    box-shadow: 0 8px 25px -5px rgba(15, 181, 186, 0.15);
}

.token-row.is-own::after {
    background: #0fb5ba;
    opacity: 1;
}

.token-row-left {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    z-index: 1;
}

.sequence-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    background: #ffffff;
    border: 2px solid #cbd5e1;
    border-radius: 50%;
    font-size: 0.68rem;
    font-weight: 800;
    color: #64748b;
    position: relative;
}

.is-own .sequence-number {
    background: #0fb5ba;
    border-color: #0fb5ba;
    color: #ffffff;
    box-shadow: 0 0 10px rgba(15, 181, 186, 0.35);
}

.pulse-ring {
    position: absolute;
    width: 28px;
    height: 28px;
    border: 2.5px solid rgba(15, 181, 186, 0.45);
    border-radius: 50%;
    animation: timeline-pulse-ring 2s infinite ease-out;
}

@keyframes timeline-pulse-ring {
    0% { transform: scale(0.6); opacity: 1; }
    100% { transform: scale(1.4); opacity: 0; }
}

.token-badge-info {
    display: flex;
    flex-direction: column;
}

.token-badge {
    font-weight: 800;
    font-size: 1rem;
    color: #1e293b;
    letter-spacing: -0.015em;
    line-height: 1.2;
}

.is-own .token-badge {
    color: #0fb5ba;
}

.token-wait-time {
    font-size: 0.72rem;
    font-weight: 700;
    color: #94a3b8;
    margin-top: 0.1rem;
}

.token-wait-time.next-in-line {
    color: #0fb5ba;
    font-weight: 800;
}

.wait-status {
    font-size: 0.72rem;
    font-weight: 800;
    color: #64748b;
    z-index: 1;
    background: #f1f5f9;
    padding: 0.2rem 0.6rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.is-own .wait-status {
    color: #0fb5ba;
    background: rgba(15, 181, 186, 0.1);
    border-color: rgba(15, 181, 186, 0.25);
}

/* Held items widget formatting */
.held-card-widget {
    border-color: rgba(239, 68, 68, 0.15);
}

.held-title {
    color: #ef4444 !important;
    border-left-color: #ef4444 !important;
}

.queue-count-badge.held-count {
    background: rgba(239, 68, 68, 0.08);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.15);
}

.held-list-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.token-row.held {
    border-color: rgba(239, 68, 68, 0.15);
    background: rgba(239, 68, 68, 0.01);
}

.token-row.held::after {
    background: #ef4444;
}

.held-indicator-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    background: #ffffff;
    border: 1.5px solid #fca5a5;
    border-radius: 50%;
    color: #ef4444;
    font-size: 0.85rem;
}

.empty-list-state {
    font-size: 0.85rem;
    color: #94a3b8;
    font-style: italic;
    padding: 1.5rem;
    text-align: center;
    background: rgba(255, 255, 255, 0.5);
    border: 1.5px dashed rgba(226, 232, 240, 0.9);
    border-radius: 16px;
}

/* Scheduled Timings Panel */
.scheduled-timings-panel {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    min-height: 400px;
}

.timings-card {
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 28px;
    padding: 3rem 2.5rem;
    max-width: 500px;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.05);
}

.timings-icon-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: 18px;
    font-size: 1.75rem;
    margin-bottom: 1.5rem;
    color: #ffffff;
}

.timings-icon-wrapper.before_start {
    background: linear-gradient(135deg, #0fb5ba 0%, #06b6d4 100%);
    box-shadow: 0 8px 20px -6px rgba(15, 181, 186, 0.3);
}

.timings-icon-wrapper.session_gap {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 0 8px 20px -6px rgba(245, 158, 11, 0.3);
}

.timings-icon-wrapper.concluded {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    box-shadow: 0 8px 20px -6px rgba(100, 116, 139, 0.3);
}

.timings-header-text {
    text-align: center;
    margin-bottom: 2rem;
}

.timings-header-text h2 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.02em;
}

.timings-header-text .description {
    font-size: 0.9rem;
    color: #64748b;
    line-height: 1.5;
    margin: 0;
}

.timings-details-container {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-top: 1px dashed #cbd5e1;
    padding-top: 1.75rem;
}

.timing-row-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border-radius: 16px;
    background: rgba(241, 245, 249, 0.5);
    border: 1px solid rgba(226, 232, 240, 0.4);
    transition: all 0.3s ease;
}

.timing-row-item.active {
    background: rgba(15, 181, 186, 0.04);
    border-color: rgba(15, 181, 186, 0.2);
}

.timing-row-item.active .session-name {
    color: #0fb5ba;
    font-weight: 700;
}

.session-info-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.morning-dot {
    background-color: #0fb5ba;
    box-shadow: 0 0 8px rgba(15, 181, 186, 0.5);
}

.evening-dot {
    background-color: #f59e0b;
    box-shadow: 0 0 8px rgba(245, 158, 11, 0.5);
}

.session-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
}

.session-time-val {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0f172a;
}

.timing-row-item.closed {
    opacity: 0.6;
    background: rgba(241, 245, 249, 0.3);
    border-color: rgba(226, 232, 240, 0.3);
}

.timing-row-item.closed .session-time-val {
    color: #94a3b8;
    text-decoration: line-through;
}

/* Special Notice Banner */
.special-notice-banner {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    background: rgba(254, 243, 199, 0.6); /* amber/yellow warning background */
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 20px;
    padding: 1.25rem 1.75rem;
    box-shadow: 0 10px 25px -5px rgba(245, 158, 11, 0.05);
    margin-bottom: 0.5rem;
}

.notice-icon {
    font-size: 1.5rem;
    color: #d97706; /* dark amber icon */
    margin-top: 0.15rem;
}

.notice-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.notice-badge {
    align-self: flex-start;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #d97706;
    background: rgba(245, 158, 11, 0.15);
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
}

.notice-message {
    font-size: 0.9rem;
    font-weight: 600;
    color: #78350f;
    line-height: 1.5;
    margin: 0;
}

/* Other Session Appointment Reminder Banner */
.other-session-banner {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    background: rgba(15, 181, 186, 0.08); /* custom brand cyan translucent background */
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(15, 181, 186, 0.3);
    border-radius: 20px;
    padding: 1.25rem 1.75rem;
    box-shadow: 0 10px 25px -5px rgba(15, 181, 186, 0.05);
    margin-bottom: 0.5rem;
}

.reminder-icon {
    font-size: 1.5rem;
    color: #0fb5ba; /* brand color icon */
    margin-top: 0.15rem;
}

.reminder-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.reminder-badge {
    align-self: flex-start;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #0fb5ba;
    background: rgba(15, 181, 186, 0.15);
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
}

.reminder-message {
    font-size: 0.9rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.5;
    margin: 0;
}

.session-title-with-tabs {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.live-session-tabs {
    display: inline-flex;
    align-items: center;
    background: #f1f5f9;
    padding: 0.22rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    gap: 0.2rem;
}

.live-session-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    border: none;
    background: transparent;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.live-session-tab.is-active {
    background: #0c6e72;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(12, 110, 114, 0.25);
}

.closed-session-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.12rem 0.55rem;
    border-radius: 999px;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 0.72rem;
    font-weight: 700;
}

.switch-session-inline-btn {
    margin-left: 0.5rem;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    border: 1px solid #0c6e72;
    background: #0c6e72;
    color: #ffffff;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
}

.personal-tokens-switcher {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 0.75rem;
}

.personal-token-chip {
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.personal-token-chip.is-active {
    background: #0c6e72;
    border-color: #0c6e72;
    color: #ffffff;
}
</style>
