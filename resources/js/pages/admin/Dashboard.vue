<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import Chart from 'primevue/chart';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';
import { booking, dashboard } from '@/routes';
import {
    appointments,
    medicines,
    medicineTracking,
    patients,
    vitalsTracking,
} from '@/routes/admin';

type ChartPoint = {
    label: string;
    shortDay?: string;
    revenue: number;
    appointments: number;
    morning?: number;
    evening?: number;
};

type PaymentRow = {
    method: string;
    count: number;
    amount: number;
};

type LowStockItem = {
    id: number;
    name: string;
    category: string | null;
    size: string | null;
    quantity: number;
};

type RecentAppointmentItem = {
    id: number;
    token: string | null;
    patientName: string;
    session: string;
    type: string;
    status: string;
    amount: number | null;
};

const props = withDefaults(
    defineProps<{
        referenceDate: string;
        referenceDateLabel: string;
        stats: {
            todayAppointments: number;
            todayCompleted: number;
            frontDeskLoad: number;
            ratingScore: number;
            satisfactionPercentage: number;
            referenceDateRevenue: number;
            revenueGrowthPercent: number;
            retentionRate: number;
            totalPatients?: number;
            pendingDispense?: number;
        };
        chartData: ChartPoint[];
        analyticsSummary: {
            weeklyRevenueTotal: number;
            weeklyAppointmentsTotal: number;
            dailyRevenueAverage: number;
            dailyAppointmentsAverage: number;
            peakRevenue: number;
            peakRevenueDay: string;
            peakAppointments: number;
            peakAppointmentsDay: string;
        };
        sessionSplit?: {
            morning: number;
            evening: number;
        };
        visitTypeSplit?: {
            newVisits: number;
            followUpVisits: number;
        };
        paymentBreakdown?: PaymentRow[];
        queueBreakdown?: {
            pending: number;
            onHold: number;
            completed: number;
            pendingDispense: number;
        };
        lowStockMedicines?: LowStockItem[];
        recentAppointments?: RecentAppointmentItem[];
    }>(),
    {
        sessionSplit: () => ({ morning: 0, evening: 0 }),
        visitTypeSplit: () => ({ newVisits: 0, followUpVisits: 0 }),
        paymentBreakdown: () => [],
        queueBreakdown: () => ({
            pending: 0,
            onHold: 0,
            completed: 0,
            pendingDispense: 0,
        }),
        lowStockMedicines: () => [],
        recentAppointments: () => [],
    },
);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

useEchoPublic('live-queue', 'QueueUpdated', () => {
    router.reload({
        only: [
            'stats',
            'chartData',
            'analyticsSummary',
            'sessionSplit',
            'visitTypeSplit',
            'paymentBreakdown',
            'queueBreakdown',
            'lowStockMedicines',
            'recentAppointments',
        ],
    });
});

const activeTab = ref<'revenue' | 'appointments'>('revenue');

function formatNumber(num: number): string {
    if (num >= 100000) {
        return (num / 100000).toFixed(1).replace(/\.0$/, '') + 'L';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    }
    return num.toString();
}

const heroStats = computed(() => [
    {
        label: 'Today appointments',
        value: props.stats.todayAppointments.toString(),
        valueSuffix: '',
        note: `Active date: ${props.referenceDateLabel}`,
        icon: 'pi pi-calendar',
        iconColor: '#3b82f6',
        railColor: '#3b82f6',
        badgeText: `${props.stats.todayCompleted} done`,
        badgeSeverity: 'info',
    },
    {
        label: 'Live queue load',
        value: props.stats.frontDeskLoad.toString().padStart(2, '0'),
        valueSuffix: '',
        note: `${props.queueBreakdown.onHold} on hold · ${props.queueBreakdown.pendingDispense} at pharmacy`,
        icon: 'pi pi-clock',
        iconColor: '#f59e0b',
        railColor: '#f59e0b',
        badgeText:
            props.stats.frontDeskLoad > 0 ? 'Active queue' : 'All clear',
        badgeSeverity: props.stats.frontDeskLoad > 0 ? 'warning' : 'success',
    },
    {
        label: 'Revenue pulse',
        value: `₹${formatNumber(props.stats.referenceDateRevenue)}`,
        valueSuffix: '',
        note: `₹${formatNumber(props.analyticsSummary.dailyRevenueAverage)} daily average`,
        icon: 'pi pi-indian-rupee',
        iconColor: '#10b981',
        railColor: '#10b981',
        badgeText: `${props.stats.revenueGrowthPercent >= 0 ? '+' : ''}${props.stats.revenueGrowthPercent}%`,
        badgeSeverity:
            props.stats.revenueGrowthPercent >= 0 ? 'success' : 'warning',
    },
    {
        label: 'Patient retention',
        value: `${props.stats.retentionRate}%`,
        valueSuffix: '',
        note: `${props.stats.totalPatients ?? 0} total registered patients`,
        icon: 'pi pi-users',
        iconColor: '#06b6d4',
        railColor: '#06b6d4',
        badgeText: `${props.stats.ratingScore.toFixed(1)}★ score`,
        badgeSeverity: 'cyan',
    },
]);

// 1. Main Trend Chart (Line for Revenue, Grouped Bar for Appointments)
const mainChartData = computed(() => {
    const labels = props.chartData.map((d) => d.label);

    if (activeTab.value === 'revenue') {
        return {
            labels,
            datasets: [
                {
                    label: 'Daily Revenue (₹)',
                    data: props.chartData.map((d) => d.revenue),
                    fill: true,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.14)',
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.35,
                },
            ],
        };
    }

    return {
        labels,
        datasets: [
            {
                label: 'Morning Session',
                backgroundColor: '#3b82f6',
                borderRadius: 6,
                data: props.chartData.map(
                    (d) => d.morning ?? Math.ceil(d.appointments / 2),
                ),
            },
            {
                label: 'Evening Session',
                backgroundColor: '#06b6d4',
                borderRadius: 6,
                data: props.chartData.map(
                    (d) => d.evening ?? Math.floor(d.appointments / 2),
                ),
            },
        ],
    };
});

const mainChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: {
            display: activeTab.value === 'appointments',
            position: 'top',
            labels: {
                usePointStyle: true,
                boxWidth: 8,
                font: { size: 12, weight: '600' },
            },
        },
        tooltip: {
            backgroundColor: '#0f172a',
            padding: 10,
            cornerRadius: 8,
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { font: { size: 12, weight: '600' } },
        },
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(148, 163, 184, 0.15)',
            },
            ticks: {
                font: { size: 11 },
                callback: (value: number) =>
                    activeTab.value === 'revenue'
                        ? `₹${formatNumber(Number(value))}`
                        : value,
            },
        },
    },
}));

// 2. Queue Status Breakdown Horizontal Bar / Doughnut
const queueChartData = computed(() => ({
    labels: ['Waiting', 'On Hold', 'At Pharmacy', 'Completed'],
    datasets: [
        {
            data: [
                props.queueBreakdown.pending,
                props.queueBreakdown.onHold,
                props.queueBreakdown.pendingDispense,
                props.queueBreakdown.completed,
            ],
            backgroundColor: ['#3b82f6', '#f59e0b', '#8b5cf6', '#10b981'],
            borderWidth: 0,
            hoverOffset: 6,
        },
    ],
}));

// 3. Session & Visit Mix Doughnut
const mixChartData = computed(() => ({
    labels: ['Morning', 'Evening', 'New Visits', 'Follow-Up'],
    datasets: [
        {
            data: [
                props.sessionSplit.morning,
                props.sessionSplit.evening,
                props.visitTypeSplit.newVisits,
                props.visitTypeSplit.followUpVisits,
            ],
            backgroundColor: ['#3b82f6', '#06b6d4', '#10b981', '#6366f1'],
            borderWidth: 0,
            hoverOffset: 6,
        },
    ],
}));

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '66%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                boxWidth: 8,
                padding: 14,
                font: { size: 11, weight: '600' },
            },
        },
    },
};

// 4. Payment Methods Bar Chart
const paymentChartData = computed(() => {
    const rows =
        props.paymentBreakdown.length > 0
            ? props.paymentBreakdown
            : [
                  { method: 'Cash', amount: 0, count: 0 },
                  { method: 'Online', amount: 0, count: 0 },
              ];

    return {
        labels: rows.map((r) => r.method),
        datasets: [
            {
                label: 'Revenue (₹)',
                data: rows.map((r) => r.amount),
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6'],
                borderRadius: 6,
                barThickness: 26,
            },
        ],
    };
});

const paymentChartOptions = {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
    scales: {
        x: {
            beginAtZero: true,
            grid: { color: 'rgba(148, 163, 184, 0.14)' },
            ticks: {
                font: { size: 11 },
                callback: (v: number) => `₹${formatNumber(Number(v))}`,
            },
        },
        y: {
            grid: { display: false },
            ticks: { font: { size: 12, weight: '600' } },
        },
    },
};

const quickShortcuts = [
    {
        label: 'Booking Desk',
        desc: 'Live queue & consultations',
        href: booking().url,
        icon: 'pi pi-desktop',
        color: '#3b82f6',
    },
    {
        label: 'Vitals Tracking',
        desc: 'Record BP, SpO2 & weight',
        href: vitalsTracking().url,
        icon: 'pi pi-wave-pulse',
        color: '#10b981',
    },
    {
        label: 'Medicine Dispatch',
        desc: 'Pharmacy & billing queue',
        href: medicineTracking().url,
        icon: 'pi pi-box',
        color: '#8b5cf6',
    },
    {
        label: 'Patient Registry',
        desc: 'Search & manage records',
        href: patients().url,
        icon: 'pi pi-users',
        color: '#06b6d4',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <div class="page-grid admin-dashboard-page">
        <!-- Top Shortcut & Overview Header -->
        <section class="page-card dashboard-top-bar">
            <div class="dashboard-top-bar__copy">
                <span class="dashboard-eyebrow">Clinic Command Center</span>
                <h2 class="dashboard-main-title">
                    Operations & Analytics Overview
                </h2>
            </div>

            <div class="dashboard-shortcuts">
                <Link
                    v-for="item in quickShortcuts"
                    :key="item.label"
                    :href="item.href"
                    class="shortcut-pill"
                >
                    <span
                        class="shortcut-pill__icon"
                        :style="{
                            background: `color-mix(in srgb, ${item.color} 14%, transparent)`,
                            color: item.color,
                        }"
                    >
                        <i :class="item.icon" />
                    </span>
                    <span class="shortcut-pill__text">
                        <strong>{{ item.label }}</strong>
                        <small>{{ item.desc }}</small>
                    </span>
                </Link>
            </div>
        </section>

        <!-- 4 KPI Stat Cards -->
        <section class="admin-dashboard-stats-grid">
            <article
                v-for="stat in heroStats"
                :key="stat.label"
                class="stat-card admin-dashboard-stat-card"
            >
                <div
                    class="admin-dashboard-stat-card__rail"
                    :style="{ background: stat.railColor }"
                />
                <div class="stat-card-header">
                    <p class="stat-label">{{ stat.label }}</p>
                    <div
                        class="stat-icon-wrapper"
                        :style="{
                            background: `color-mix(in srgb, ${stat.iconColor} 12%, transparent)`,
                            color: stat.iconColor,
                        }"
                    >
                        <i :class="stat.icon" />
                    </div>
                </div>

                <div class="stat-value-container">
                    <h2 class="stat-value">
                        {{ stat.value }}
                    </h2>
                    <span
                        :class="[
                            'stat-badge',
                            `stat-badge--${stat.badgeSeverity}`,
                        ]"
                    >
                        {{ stat.badgeText }}
                    </span>
                </div>

                <p class="stat-note">{{ stat.note }}</p>
            </article>
        </section>

        <!-- Primary Trend Chart + Today's Queue Doughnut -->
        <section class="primary-analytics-grid">
            <article class="page-card chart-panel">
                <div class="chart-panel__header">
                    <div>
                        <h3 class="panel-title">
                            7-Day Revenue & Appointment Trend
                        </h3>
                        <p class="panel-subtitle">
                            Switch between daily clinic revenue and session-wise
                            patient volume.
                        </p>
                    </div>

                    <div class="analytics-tabs">
                        <button
                            type="button"
                            class="analytics-tab-btn"
                            :class="{ 'is-active': activeTab === 'revenue' }"
                            @click="activeTab = 'revenue'"
                        >
                            <i class="pi pi-indian-rupee" />
                            <span>Revenue</span>
                        </button>
                        <button
                            type="button"
                            class="analytics-tab-btn"
                            :class="{
                                'is-active': activeTab === 'appointments',
                            }"
                            @click="activeTab = 'appointments'"
                        >
                            <i class="pi pi-calendar" />
                            <span>Appointments</span>
                        </button>
                    </div>
                </div>

                <div class="analytics-summary-row">
                    <div class="analytics-summary-item">
                        <span class="stat-label">7-Day Total</span>
                        <strong class="analytics-summary-value">
                            {{
                                activeTab === 'revenue'
                                    ? `₹${formatNumber(props.analyticsSummary.weeklyRevenueTotal)}`
                                    : props.analyticsSummary
                                          .weeklyAppointmentsTotal
                            }}
                        </strong>
                    </div>
                    <div class="analytics-summary-item">
                        <span class="stat-label">Daily Average</span>
                        <strong class="analytics-summary-value">
                            {{
                                activeTab === 'revenue'
                                    ? `₹${formatNumber(props.analyticsSummary.dailyRevenueAverage)}`
                                    : props.analyticsSummary
                                          .dailyAppointmentsAverage
                            }}
                        </strong>
                    </div>
                    <div class="analytics-summary-item">
                        <span class="stat-label">Peak Day</span>
                        <strong class="analytics-summary-value">
                            {{
                                activeTab === 'revenue'
                                    ? `₹${formatNumber(props.analyticsSummary.peakRevenue)} (${props.analyticsSummary.peakRevenueDay})`
                                    : `${props.analyticsSummary.peakAppointments} visits (${props.analyticsSummary.peakAppointmentsDay})`
                            }}
                        </strong>
                    </div>
                </div>

                <div class="main-chart-box">
                    <Chart
                        :key="activeTab"
                        :type="activeTab === 'revenue' ? 'line' : 'bar'"
                        :data="mainChartData"
                        :options="mainChartOptions"
                        class="h-full w-full"
                    />
                </div>
            </article>

            <!-- Today's Queue Status Doughnut Chart -->
            <article class="page-card chart-panel">
                <div class="chart-panel__header">
                    <div>
                        <h3 class="panel-title">Today's Queue Flow</h3>
                        <p class="panel-subtitle">
                            Live status for {{ props.referenceDateLabel }}
                        </p>
                    </div>
                    <span class="badge-live">Live</span>
                </div>

                <div class="doughnut-chart-box">
                    <Chart
                        type="doughnut"
                        :data="queueChartData"
                        :options="doughnutOptions"
                        class="h-full w-full"
                    />
                </div>

                <div class="mini-Breakdown-grid">
                    <div class="mini-breakdown-pill">
                        <span>Waiting</span>
                        <strong>{{ props.queueBreakdown.pending }}</strong>
                    </div>
                    <div class="mini-breakdown-pill">
                        <span>On Hold</span>
                        <strong>{{ props.queueBreakdown.onHold }}</strong>
                    </div>
                    <div class="mini-breakdown-pill">
                        <span>Pharmacy</span>
                        <strong>{{
                            props.queueBreakdown.pendingDispense
                        }}</strong>
                    </div>
                    <div class="mini-breakdown-pill">
                        <span>Completed</span>
                        <strong>{{ props.queueBreakdown.completed }}</strong>
                    </div>
                </div>
            </article>
        </section>

        <!-- Secondary Charts + Operational Widgets -->
        <section class="secondary-analytics-grid">
            <!-- Session & Visit Mix Doughnut -->
            <article class="page-card chart-panel">
                <div class="chart-panel__header">
                    <div>
                        <h3 class="panel-title">Session & Visit Mix</h3>
                        <p class="panel-subtitle">
                            Morning vs Evening & New vs Follow-Up
                        </p>
                    </div>
                </div>

                <div class="doughnut-chart-box">
                    <Chart
                        type="doughnut"
                        :data="mixChartData"
                        :options="doughnutOptions"
                        class="h-full w-full"
                    />
                </div>
            </article>

            <!-- Revenue by Payment Mode Bar Chart -->
            <article class="page-card chart-panel">
                <div class="chart-panel__header">
                    <div>
                        <h3 class="panel-title">Collections by Payment Mode</h3>
                        <p class="panel-subtitle">
                            Breakdown of recorded payments
                        </p>
                    </div>
                </div>

                <div class="doughnut-chart-box">
                    <Chart
                        type="bar"
                        :data="paymentChartData"
                        :options="paymentChartOptions"
                        class="h-full w-full"
                    />
                </div>
            </article>

            <!-- Low Stock Pharmacy Alerts & Recent Queue -->
            <article class="page-card chart-panel">
                <div class="chart-panel__header">
                    <div>
                        <h3 class="panel-title">Low Stock Pharmacy Alerts</h3>
                        <p class="panel-subtitle">
                            Medicines with 20 or fewer units left
                        </p>
                    </div>
                    <Link :href="medicines().url" class="panel-link">
                        Manage Stock →
                    </Link>
                </div>

                <div
                    v-if="props.lowStockMedicines.length > 0"
                    class="alerts-stack"
                >
                    <div
                        v-for="item in props.lowStockMedicines"
                        :key="item.id"
                        class="stock-alert-row"
                    >
                        <div class="stock-alert-row__info">
                            <strong>{{ item.name }}</strong>
                            <span>
                                {{
                                    [item.category, item.size]
                                        .filter(Boolean)
                                        .join(' · ') || 'Standard'
                                }}
                            </span>
                        </div>
                        <Tag
                            :severity="
                                item.quantity <= 5 ? 'danger' : 'warn'
                            "
                            :value="`${item.quantity} left`"
                        />
                    </div>
                </div>
                <div v-else class="empty-widget-state">
                    <i class="pi pi-check-circle" />
                    <span>All medicine stock levels are healthy.</span>
                </div>
            </article>
        </section>

        <!-- Recent Appointments Table Snapshot -->
        <section class="page-card admin-table-card recent-appointments-panel">
            <div class="recent-appointments-header">
                <div>
                    <h3 class="panel-title">
                        Queue Snapshot ({{ props.referenceDateLabel }})
                    </h3>
                    <p class="panel-subtitle">
                        Latest scheduled patients for the active clinic day
                    </p>
                </div>
                <Link :href="appointments().url" class="panel-link">
                    View All Appointments →
                </Link>
            </div>

            <div
                v-if="props.recentAppointments.length > 0"
                class="recent-table-wrap"
            >
                <table class="recent-apt-table">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Patient</th>
                            <th>Session</th>
                            <th>Visit Type</th>
                            <th>Fee</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="apt in props.recentAppointments"
                            :key="apt.id"
                        >
                            <td>
                                <span class="admin-token-chip">
                                    #{{ apt.token ?? '-' }}
                                </span>
                            </td>
                            <td>
                                <strong>{{ apt.patientName }}</strong>
                            </td>
                            <td>
                                <span
                                    class="admin-session-badge"
                                    :class="`admin-session-badge--${apt.session.toLowerCase()}`"
                                >
                                    <i
                                        :class="
                                            apt.session.toLowerCase() ===
                                            'morning'
                                                ? 'pi pi-sun'
                                                : 'pi pi-moon'
                                        "
                                    ></i>
                                    {{ apt.session }}
                                </span>
                            </td>
                            <td>
                                <span
                                    class="admin-type-badge"
                                    :class="`admin-type-badge--${apt.type.toLowerCase().replace(' ', '-')}`"
                                >
                                    {{ apt.type }}
                                </span>
                            </td>
                            <td>
                                <strong class="fee-cell">{{
                                    apt.amount ? `₹${apt.amount}` : '—'
                                }}</strong>
                            </td>
                            <td>
                                <Tag
                                    :severity="
                                        apt.status === 'Complete'
                                            ? 'success'
                                            : apt.status === 'On Hold'
                                              ? 'warn'
                                              : 'info'
                                    "
                                    :value="apt.status"
                                    rounded
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="empty-widget-state empty-widget-state--padded">
                <i class="pi pi-calendar" />
                <span>No appointments recorded for this date yet.</span>
            </div>
        </section>
    </div>
</template>

<style scoped>
.admin-dashboard-page {
    display: grid;
    gap: 1.25rem;
}

/* Top Command Bar */
.dashboard-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    flex-wrap: wrap;
    padding: 1.25rem 1.5rem;
}

.dashboard-eyebrow {
    display: block;
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--primary-color);
}

.dashboard-main-title {
    margin: 0.2rem 0 0;
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--text-color);
}

.dashboard-shortcuts {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.shortcut-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.55rem 0.9rem;
    border-radius: 10px;
    border: 1px solid var(--surface-border);
    background: var(--surface-ground);
    text-decoration: none;
    transition: all 0.18s ease;
}

.shortcut-pill:hover {
    border-color: var(--primary-color);
    transform: translateY(-1px);
}

.shortcut-pill__icon {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
}

.shortcut-pill__text {
    display: flex;
    flex-direction: column;
}

.shortcut-pill__text strong {
    font-size: 0.85rem;
    color: var(--text-color);
}

.shortcut-pill__text small {
    font-size: 0.72rem;
    color: var(--text-secondary-color);
}

/* 4 KPI Stat Cards */
.admin-dashboard-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.admin-dashboard-stat-card {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 1.2rem 1.3rem;
    min-height: 8.5rem;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 12px;
}

.admin-dashboard-stat-card__rail {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.stat-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.stat-icon-wrapper {
    width: 2.3rem;
    height: 2.3rem;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.stat-value-container {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    margin: 0.5rem 0 0.35rem;
}

.stat-value {
    margin: 0;
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--text-color);
}

.stat-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
}

.stat-badge--success {
    background: rgba(16, 185, 129, 0.14);
    color: #059669;
}

.stat-badge--warning {
    background: rgba(245, 158, 11, 0.14);
    color: #d97706;
}

.stat-badge--info {
    background: rgba(59, 130, 246, 0.14);
    color: #2563eb;
}

.stat-badge--cyan {
    background: rgba(6, 182, 212, 0.14);
    color: #0891b2;
}

.stat-note {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-secondary-color);
}

/* Primary Analytics Grid */
.primary-analytics-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.85fr) minmax(20rem, 1fr);
    gap: 1.25rem;
}

.chart-panel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.35rem 1.5rem;
}

.chart-panel__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.panel-link {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--primary-color);
    text-decoration: none;
}

.analytics-tabs {
    display: inline-flex;
    gap: 0.35rem;
    padding: 0.25rem;
    border-radius: 8px;
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
}

.analytics-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: var(--text-secondary-color);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.analytics-tab-btn.is-active {
    background: var(--surface-card);
    color: var(--primary-color);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.analytics-summary-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: 10px;
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
}

.analytics-summary-item {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.analytics-summary-value {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-color);
}

.main-chart-box {
    height: 17.5rem;
    position: relative;
}

.doughnut-chart-box {
    height: 14.5rem;
    position: relative;
}

.mini-Breakdown-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.mini-breakdown-pill {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    background: var(--surface-ground);
    font-size: 0.82rem;
}

/* Secondary 3-Column Row */
.secondary-analytics-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1.25rem;
}

.alerts-stack {
    display: grid;
    gap: 0.6rem;
}

.stock-alert-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
}

.stock-alert-row__info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.stock-alert-row__info strong {
    font-size: 0.88rem;
    color: var(--text-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stock-alert-row__info span {
    font-size: 0.75rem;
    color: var(--text-secondary-color);
}

.empty-widget-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-height: 8rem;
    border-radius: 10px;
    background: var(--surface-ground);
    color: var(--text-secondary-color);
    font-size: 0.88rem;
}

/* Recent Appointments Table */
.recent-appointments-panel {
    padding: 0;
    overflow: hidden;
}

.recent-appointments-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 1.15rem 1.35rem;
    border-bottom: 1px solid var(--surface-border);
    background: var(--surface-card);
}

.recent-table-wrap {
    overflow-x: auto;
}

.recent-apt-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 0.875rem;
}

.recent-apt-table th {
    padding: 0.9rem 1.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary-color);
    background: color-mix(in srgb, var(--surface-50) 80%, var(--surface-card));
    border-bottom: 1px solid var(--surface-border);
    white-space: nowrap;
}

.recent-apt-table td {
    padding: 0.95rem 1.25rem;
    border-bottom: 1px solid var(--surface-border);
    color: var(--text-color);
    vertical-align: middle;
}

.recent-apt-table tbody tr {
    border-left: 3px solid transparent;
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.recent-apt-table tbody tr:nth-child(odd) {
    background: color-mix(in srgb, var(--surface-card) 96%, var(--surface-50));
}

.recent-apt-table tbody tr:hover {
    background: color-mix(in srgb, var(--surface-hover) 78%, var(--surface-card));
    border-left-color: color-mix(in srgb, var(--primary-color) 45%, transparent);
}

.fee-cell {
    font-variant-numeric: tabular-nums;
}

.empty-widget-state--padded {
    margin: 1.25rem;
}

@media (max-width: 1200px) {
    .admin-dashboard-stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .primary-analytics-grid,
    .secondary-analytics-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .admin-dashboard-stats-grid,
    .analytics-summary-row {
        grid-template-columns: 1fr;
    }
}
</style>
