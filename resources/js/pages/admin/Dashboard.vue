<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { booking, dashboard } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';

const props = defineProps<{
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
    };
    chartData: Array<{
        label: string;
        revenue: number;
        appointments: number;
    }>;
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
}>();

const activeTab = ref<'revenue' | 'appointments'>('revenue');
const hoveredIndex = ref<number | null>(null);

const computedPoints = computed(() => {
    const values = props.chartData.map((d) => activeTab.value === 'revenue' ? d.revenue : d.appointments);
    const maxVal = Math.max(...values) * 1.15; // 15% top padding
    const minVal = 0;
    
    const width = 500;
    const height = 200;
    const paddingX = 40;
    const paddingY = 25;
    
    const drawWidth = width - paddingX * 2;
    const drawHeight = height - paddingY * 2;
    
    return props.chartData.map((d, index) => {
        const val = activeTab.value === 'revenue' ? d.revenue : d.appointments;
        const x = paddingX + (index * (drawWidth / (props.chartData.length - 1)));
        const y = (height - paddingY) - ((val - minVal) / (maxVal - minVal)) * drawHeight;
        
        return { x, y, val, label: d.label };
    });
});

const linePath = computed(() => {
    const points = computedPoints.value;
    if (points.length === 0) {
        return '';
    }
    
    return points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
});

const fillPath = computed(() => {
    const points = computedPoints.value;
    if (points.length === 0) {
        return '';
    }
    const first = points[0];
    const last = points[points.length - 1];
    
    const height = 200;
    const paddingY = 25;
    const bottomY = height - paddingY;
    
    return `M ${first.x} ${bottomY} ` + 
           points.map((p) => `L ${p.x} ${p.y}`).join(' ') + 
           ` L ${last.x} ${bottomY} Z`;
});

const yTicks = computed(() => {
    const values = props.chartData.map((d) => activeTab.value === 'revenue' ? d.revenue : d.appointments);
    const maxVal = Math.max(...values);
    
    return [
        Math.round(maxVal),
        Math.round(maxVal * 0.66),
        Math.round(maxVal * 0.33),
        0
    ];
});

function formatNumber(num: number): string {
    if (num >= 1000) {
        return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    }
    
    return num.toString();
}

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

const heroStats = computed(() => [
    {
        label: 'Today appointments',
        value: props.stats.todayAppointments.toString(),
        valueSuffix: '',
        note: `${props.stats.todayCompleted} completed check-ins`,
        icon: 'pi pi-calendar',
        iconColor: 'var(--primary-color)',
        railColor: 'var(--primary-color)',
        cardClass: 'appointment-card',
        badgeText: 'Active',
        badgeSeverity: 'info',
        badgeIcon: '',
    },
    {
        label: 'Front desk load',
        value: props.stats.frontDeskLoad.toString().padStart(2, '0'),
        valueSuffix: '',
        note: 'Waiting/Hold items in backlog',
        icon: 'pi pi-clock',
        iconColor: 'var(--p-orange-500)',
        railColor: 'var(--p-orange-500)',
        cardClass: 'load-card',
        badgeText: props.stats.frontDeskLoad > 0 ? 'Action required' : 'Clear',
        badgeSeverity: props.stats.frontDeskLoad > 0 ? 'warning' : 'success',
        badgeIcon: '',
    },
    {
        label: 'Revenue pulse',
        value: `₹${formatNumber(props.stats.referenceDateRevenue)}`,
        valueSuffix: '',
        note: `Target day: ${props.referenceDateLabel}`,
        icon: 'pi pi-indian-rupee',
        iconColor: 'var(--p-green-500)',
        railColor: 'var(--p-green-500)',
        cardClass: 'revenue-card',
        badgeText: `${props.stats.revenueGrowthPercent >= 0 ? '+' : ''}${props.stats.revenueGrowthPercent}%`,
        badgeSeverity: props.stats.revenueGrowthPercent >= 0 ? 'success' : 'warning',
        badgeIcon: props.stats.revenueGrowthPercent >= 0 ? 'pi pi-arrow-up-right' : 'pi pi-arrow-down-left',
    },
    {
        label: 'Patient rating',
        value: props.stats.ratingScore.toFixed(1),
        valueSuffix: '/5',
        note: `${props.stats.retentionRate}% patient retention rate`,
        icon: 'pi pi-star-fill',
        iconColor: 'var(--p-cyan-500)',
        railColor: 'var(--p-cyan-500)',
        cardClass: 'rating-card',
        badgeText: props.stats.ratingScore >= 4.5 ? 'Excellent' : 'Good',
        badgeSeverity: 'cyan',
        badgeIcon: '',
    },
]);

const timeline = [
    {
        time: '09:15',
        title: 'Morning queue opened',
        detail: 'Front desk released 24 new slots for same-day bookings.',
    },
    {
        time: '10:00',
        title: 'Staff review block',
        detail: 'Three appointment edits still need admin confirmation.',
    },
    {
        time: '11:30',
        title: 'Follow-up calls',
        detail: 'Team scheduled callbacks for pending lab review patients.',
    },
];

const focusCards = [
    {
        title: 'Booking desk',
        value: '15 pending',
        note: 'Same-day demand still high across morning sessions.',
    },
    {
        title: 'Care coordination',
        value: '08 tasks',
        note: 'Prescription and callback work stacked for noon handoff.',
    },
    {
        title: 'Staff coverage',
        value: 'Full team',
        note: 'No shift gaps reported for afternoon clinic operations.',
    },
];

const quickActions = [
    {
        label: 'Open booking desk',
        note: 'Create or review admin bookings.',
        href: booking().url,
        icon: 'pi pi-calendar-plus',
    },
    {
        label: 'Review profile',
        note: 'Update staff account and contact details.',
        href: editProfile().url,
        icon: 'pi pi-user-edit',
    },
    {
        label: 'Tune appearance',
        note: 'Adjust workspace presentation settings.',
        href: editAppearance().url,
        icon: 'pi pi-palette',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <div class="page-grid admin-dashboard-page">
        <!-- Admin Hero Page Card -->
        <section class="admin-hero page-card admin-dashboard-hero">
            <div class="admin-hero__copy admin-dashboard-hero__copy">
                <p class="stat-label admin-dashboard-hero__eyebrow">
                    Operations overview
                </p>
                <h2 class="admin-hero__title admin-dashboard-hero__title">
                    Keep front desk, scheduling, and patient flow moving.
                </h2>
                <p class="panel-subtitle admin-dashboard-hero__subtitle">
                    This admin dashboard is tuned for fast triage, live
                    scheduling decisions, and staff handoff visibility.
                </p>
            </div>

            <div class="admin-hero__actions admin-dashboard-hero__actions">
                <Link
                    :href="booking().url"
                    class="admin-dashboard-hero__action-link"
                >
                    <button
                        class="admin-cta admin-cta--primary admin-dashboard-hero__button"
                    >
                        <i class="pi pi-calendar-plus"></i>
                        <span>New booking</span>
                    </button>
                </Link>

                <Link
                    :href="dashboard().url"
                    class="admin-dashboard-hero__action-link"
                >
                    <button class="admin-cta admin-dashboard-hero__button">
                        <i class="pi pi-chart-bar"></i>
                        <span>Refresh view</span>
                    </button>
                </Link>
            </div>
        </section>

        <!-- Stats Grid -->
        <section class="admin-dashboard-stats-grid">
            <article
                v-for="stat in heroStats"
                :key="stat.label"
                class="stat-card admin-dashboard-stat-card"
                :class="stat.cardClass"
            >
                <div class="admin-dashboard-stat-card__rail" :style="{ background: stat.railColor }"></div>
                <div class="stat-card-header">
                    <p class="stat-label admin-dashboard-stat-card__label">
                        {{ stat.label }}
                    </p>
                    <div class="stat-icon-wrapper" :style="{ background: `color-mix(in srgb, ${stat.iconColor} 12%, transparent)`, color: stat.iconColor }">
                        <i :class="stat.icon" class="stat-card-icon"></i>
                    </div>
                </div>
                <div class="stat-value-container">
                    <h2 class="stat-value admin-dashboard-stat-card__value">
                        {{ stat.value }}<span v-if="stat.valueSuffix" class="stat-value-small">{{ stat.valueSuffix }}</span>
                    </h2>
                    <span v-if="stat.badgeText" :class="['stat-badge', `stat-badge--${stat.badgeSeverity}`]">
                        <i v-if="stat.badgeIcon" :class="stat.badgeIcon" class="mr-1"></i>
                        {{ stat.badgeText }}
                    </span>
                </div>
                
                <div class="stat-visual-container">
                    <template v-if="stat.label === 'Today appointments'">
                        <div class="stat-progress-bar-bg">
                            <div class="stat-progress-bar-fill" :style="{ width: (props.stats.todayAppointments > 0 ? (props.stats.todayCompleted / props.stats.todayAppointments) * 100 : 0) + '%', background: 'var(--primary-color)' }"></div>
                        </div>
                        <span class="stat-progress-text">{{ props.stats.todayCompleted }}/{{ props.stats.todayAppointments }} in</span>
                    </template>
                    <template v-else-if="stat.label === 'Front desk load'">
                        <span class="stat-metric-pill" :style="{ background: props.stats.frontDeskLoad > 0 ? 'color-mix(in srgb, var(--p-orange-500) 10%, transparent)' : 'color-mix(in srgb, var(--p-green-500) 10%, transparent)', color: props.stats.frontDeskLoad > 0 ? 'var(--p-orange-500)' : 'var(--p-green-500)' }">
                            <i class="pi pi-check-circle mr-1" v-if="props.stats.frontDeskLoad === 0"></i>
                            <i class="pi pi-exclamation-circle mr-1" v-else></i>
                            {{ props.stats.frontDeskLoad === 0 ? 'All caught up' : `${props.stats.frontDeskLoad} pending tasks` }}
                        </span>
                    </template>
                    <template v-else-if="stat.label === 'Revenue pulse'">
                        <div class="mini-sparkline">
                            <span class="spark-dot"></span>
                            <span class="spark-text">₹{{ formatNumber(props.analyticsSummary.dailyRevenueAverage) }} avg active day</span>
                        </div>
                    </template>
                    <template v-else-if="stat.label === 'Patient rating'">
                        <div class="stars-row">
                            <i class="pi pi-star-fill star-active" v-for="i in Math.round(props.stats.ratingScore)" :key="i"></i>
                            <i class="pi pi-star star-inactive" v-for="i in (5 - Math.round(props.stats.ratingScore))" :key="i + 5"></i>
                            <span class="stars-label ml-1">{{ props.stats.satisfactionPercentage }}% positive</span>
                        </div>
                    </template>
                </div>

                <p class="stat-note admin-dashboard-stat-card__note">
                    {{ stat.note }}
                </p>
            </article>
        </section>

        <!-- Dashboard Split Columns -->
        <section class="admin-dashboard-content-grid">
            <!-- Left Column: Analytics & Clinic Timeline -->
            <div class="admin-dashboard-main-column">
                <!-- Analytics Card -->
                <article class="page-card admin-dashboard-panel admin-analytics-card">
                    <div class="admin-section-header admin-dashboard-section-header">
                        <div>
                            <h3 class="panel-title admin-dashboard-section-title">
                                Clinic Analytics
                            </h3>
                            <p class="panel-subtitle admin-dashboard-section-copy">
                                Daily performance metrics showing revenue and appointment loads.
                            </p>
                        </div>

                        <div class="analytics-tabs">
                            <button
                                type="button"
                                class="analytics-tab-btn"
                                :class="{ 'is-active': activeTab === 'revenue' }"
                                @click="activeTab = 'revenue'"
                            >
                                <i class="pi pi-indian-rupee mr-1"></i> Revenue
                            </button>
                            <button
                                type="button"
                                class="analytics-tab-btn"
                                :class="{ 'is-active': activeTab === 'appointments' }"
                                @click="activeTab = 'appointments'"
                            >
                                <i class="pi pi-calendar mr-1"></i> Appointments
                            </button>
                        </div>
                    </div>

                    <div class="analytics-summary-row">
                        <div class="analytics-summary-item">
                            <span class="stat-label">Weekly Total</span>
                            <strong class="analytics-summary-value">
                                {{ activeTab === 'revenue' ? '₹' + formatNumber(props.analyticsSummary.weeklyRevenueTotal) : props.analyticsSummary.weeklyAppointmentsTotal }}
                            </strong>
                            <span class="analytics-summary-trend positive">
                                <i class="pi pi-arrow-up-right"></i> +12.4%
                            </span>
                        </div>
                        <div class="analytics-summary-item">
                            <span class="stat-label">Daily Average</span>
                            <strong class="analytics-summary-value">
                                {{ activeTab === 'revenue' ? '₹' + formatNumber(props.analyticsSummary.dailyRevenueAverage) : props.analyticsSummary.dailyAppointmentsAverage }}
                            </strong>
                            <span class="analytics-summary-trend positive">
                                <i class="pi pi-arrow-up-right"></i> +8.1%
                            </span>
                        </div>
                        <div class="analytics-summary-item">
                            <span class="stat-label">Peak Performance</span>
                            <strong class="analytics-summary-value">
                                {{ activeTab === 'revenue' ? '₹' + formatNumber(props.analyticsSummary.peakRevenue) + ' (' + props.analyticsSummary.peakRevenueDay + ')' : props.analyticsSummary.peakAppointments + ' (' + props.analyticsSummary.peakAppointmentsDay + ')' }}
                            </strong>
                        </div>
                    </div>

                    <div class="chart-container">
                        <svg viewBox="0 0 500 200" width="100%" height="100%" class="analytics-svg-chart">
                            <defs>
                                <!-- Area Gradient -->
                                <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="var(--primary-color)" stop-opacity="0.25" />
                                    <stop offset="100%" stop-color="var(--primary-color)" stop-opacity="0.00" />
                                </linearGradient>
                                <!-- Bar Gradient -->
                                <linearGradient id="barGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="var(--primary-color)" />
                                    <stop offset="100%" stop-color="color-mix(in srgb, var(--primary-color) 40%, transparent)" />
                                </linearGradient>
                            </defs>

                            <!-- Horizontal Grid Lines -->
                            <line
                                v-for="y in [25, 75, 125, 175]"
                                :key="y"
                                x1="40"
                                :y1="y"
                                x2="475"
                                :y2="y"
                                stroke="var(--surface-border)"
                                stroke-width="1"
                                stroke-dasharray="3 3"
                            />

                            <!-- Y Axis Ticks -->
                            <text
                                v-for="(tick, idx) in yTicks"
                                :key="idx"
                                x="32"
                                :y="[25, 75, 125, 175][idx] + 3"
                                text-anchor="end"
                                font-size="9"
                                fill="var(--text-secondary-color)"
                                font-weight="600"
                            >
                                {{ activeTab === 'revenue' ? '₹' + formatNumber(tick) : tick }}
                            </text>

                            <!-- Area under Line (Revenue only) -->
                            <path
                                v-if="activeTab === 'revenue' && fillPath"
                                :d="fillPath"
                                fill="url(#areaGrad)"
                                class="chart-area-path"
                            />

                            <!-- Line path (Revenue only) -->
                            <path
                                v-if="activeTab === 'revenue' && linePath"
                                :d="linePath"
                                fill="none"
                                stroke="var(--primary-color)"
                                stroke-width="3"
                                stroke-linejoin="round"
                                stroke-linecap="round"
                                class="chart-line-path"
                            />

                            <!-- Bar Chart (Appointments only) -->
                            <g v-if="activeTab === 'appointments'">
                                <rect
                                    v-for="(p, index) in computedPoints"
                                    :key="index"
                                    :x="p.x - 12"
                                    :y="p.y"
                                    width="24"
                                    :height="175 - p.y"
                                    rx="4"
                                    fill="url(#barGradient)"
                                    class="chart-bar-rect"
                                    @mouseenter="hoveredIndex = index"
                                    @mouseleave="hoveredIndex = null"
                                />
                            </g>

                            <!-- Interactive circles (Revenue only) -->
                            <g v-if="activeTab === 'revenue'">
                                <circle
                                    v-for="(p, index) in computedPoints"
                                    :key="index"
                                    :cx="p.x"
                                    :cy="p.y"
                                    :r="hoveredIndex === index ? 6 : 4"
                                    :fill="hoveredIndex === index ? 'var(--primary-color)' : 'var(--surface-card)'"
                                    stroke="var(--primary-color)"
                                    stroke-width="2.5"
                                    class="chart-point-circle"
                                    @mouseenter="hoveredIndex = index"
                                    @mouseleave="hoveredIndex = null"
                                />
                            </g>

                            <!-- X Axis Labels -->
                            <text
                                v-for="(p, index) in computedPoints"
                                :key="index"
                                :x="p.x"
                                y="192"
                                text-anchor="middle"
                                font-size="9.5"
                                fill="var(--text-secondary-color)"
                                font-weight="600"
                            >
                                {{ p.label }}
                            </text>

                            <!-- Tooltip Box (Visible on Hover) -->
                            <g v-if="hoveredIndex !== null" class="chart-tooltip-group">
                                <rect
                                    :x="computedPoints[hoveredIndex].x - 32"
                                    :y="computedPoints[hoveredIndex].y - 30"
                                    width="64"
                                    height="22"
                                    rx="4"
                                    fill="var(--surface-card)"
                                    stroke="var(--surface-border)"
                                    stroke-width="1.5"
                                    class="tooltip-bg"
                                />
                                <text
                                    :x="computedPoints[hoveredIndex].x"
                                    :y="computedPoints[hoveredIndex].y - 15"
                                    text-anchor="middle"
                                    font-size="9.5"
                                    font-weight="700"
                                    fill="var(--text-color)"
                                >
                                    {{ activeTab === 'revenue' ? '₹' + computedPoints[hoveredIndex].val : computedPoints[hoveredIndex].val }}
                                </text>
                            </g>
                        </svg>
                    </div>
                </article>

                <!-- Clinic Timeline Card -->
                <article
                    class="page-card admin-dashboard-panel admin-dashboard-panel--wide"
                >
                    <div
                        class="admin-section-header admin-dashboard-section-header"
                    >
                        <div>
                            <h3 class="panel-title admin-dashboard-section-title">
                                Clinic timeline
                            </h3>
                            <p class="panel-subtitle admin-dashboard-section-copy">
                                Key activity blocks for today’s admin workflow.
                            </p>
                        </div>

                        <span class="badge-live admin-dashboard-live-badge">
                            Live
                        </span>
                    </div>

                    <div class="admin-timeline admin-dashboard-timeline">
                        <div
                            v-for="item in timeline"
                            :key="item.time"
                            class="admin-timeline__item admin-dashboard-timeline__item"
                        >
                            <div
                                class="admin-timeline__time admin-dashboard-timeline__time"
                            >
                                {{ item.time }}
                            </div>
                            <div
                                class="admin-timeline__content admin-dashboard-timeline__content"
                            >
                                <strong class="admin-dashboard-timeline__title">{{
                                    item.title
                                }}</strong>
                                <p class="admin-dashboard-timeline__detail">
                                    {{ item.detail }}
                                </p>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Right Column: Focus Areas & System Status -->
            <div class="admin-dashboard-side-column">
                <article class="page-card admin-dashboard-panel">
                    <div
                        class="admin-section-header admin-dashboard-section-header admin-dashboard-section-header--compact"
                    >
                        <h3 class="panel-title admin-dashboard-section-title">
                            Focus areas
                        </h3>
                        <p class="panel-subtitle admin-dashboard-section-copy">
                            What deserves attention right now.
                        </p>
                    </div>

                    <div class="admin-focus-list admin-dashboard-focus-list">
                        <div
                            v-for="card in focusCards"
                            :key="card.title"
                            class="admin-focus-card admin-dashboard-focus-card"
                        >
                            <span
                                class="stat-label admin-dashboard-focus-card__label"
                                >{{ card.title }}</span
                            >
                            <strong
                                class="stat-value admin-dashboard-focus-card__value"
                                >{{ card.value }}</strong
                            >
                            <p class="admin-dashboard-focus-card__note">
                                {{ card.note }}
                            </p>
                        </div>
                    </div>
                </article>

                <article
                    class="page-card system-card admin-dashboard-system-card"
                >
                    <span class="admin-dashboard-system-card__label"
                        >System status</span
                    >
                    <h3 class="panel-title admin-dashboard-system-card__title">
                        <i
                            class="pi pi-check-circle admin-dashboard-system-card__icon"
                        ></i>
                        <span>All critical services healthy</span>
                    </h3>
                    <p class="admin-dashboard-system-card__copy">
                        Scheduling, profile settings, and core admin shell are
                        ready for production data wiring.
                    </p>
                </article>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="page-card admin-dashboard-panel">
            <div class="admin-section-header admin-dashboard-section-header">
                <h3 class="panel-title admin-dashboard-section-title">
                    Quick actions
                </h3>
                <p class="panel-subtitle admin-dashboard-section-copy">
                    Shortcuts for busiest admin tasks.
                </p>
            </div>

            <div class="admin-dashboard-actions-grid">
                <Link
                    v-for="action in quickActions"
                    :key="action.label"
                    :href="action.href"
                    class="admin-action-card admin-dashboard-action-card"
                >
                    <i
                        :class="action.icon"
                        class="admin-dashboard-action-card__icon"
                    ></i>
                    <strong class="admin-dashboard-action-card__title">{{
                        action.label
                    }}</strong>
                    <p class="admin-dashboard-action-card__copy">
                        {{ action.note }}
                    </p>
                </Link>
            </div>
        </section>
    </div>
</template>

<style scoped>
.admin-dashboard-page {
    gap: 1.5rem;
}

.admin-dashboard-hero {
    padding: 1.5rem;
}

.admin-dashboard-hero__copy {
    display: grid;
    gap: 0.5rem;
    max-width: 42rem;
}

.admin-dashboard-hero__eyebrow {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--primary-color);
}

.admin-dashboard-hero__title {
    font-size: clamp(1.8rem, 2.8vw, 2.35rem);
    font-weight: 800;
    line-height: 1.1;
    color: var(--text-color);
}

.admin-dashboard-hero__subtitle {
    font-size: 0.95rem;
    line-height: 1.6;
}

.admin-dashboard-hero__actions {
    display: flex;
    gap: 0.75rem;
    width: 100%;
    flex-wrap: wrap;
}

.admin-dashboard-hero__action-link {
    flex: 1 1 14rem;
}

.admin-dashboard-hero__button {
    width: 100%;
    gap: 0.5rem;
}

.admin-dashboard-stats-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.admin-dashboard-stat-card {
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    border: 1px solid var(--surface-border);
}

.admin-dashboard-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.08);
}

.appointment-card {
    background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 4%, var(--surface-card)) 0%, var(--surface-card) 100%) !important;
}
.appointment-card:hover {
    border-color: var(--primary-color) !important;
}

.load-card {
    background: linear-gradient(135deg, color-mix(in srgb, var(--p-orange-500) 4%, var(--surface-card)) 0%, var(--surface-card) 100%) !important;
}
.load-card:hover {
    border-color: var(--p-orange-500) !important;
}

.revenue-card {
    background: linear-gradient(135deg, color-mix(in srgb, var(--p-green-500) 4%, var(--surface-card)) 0%, var(--surface-card) 100%) !important;
}
.revenue-card:hover {
    border-color: var(--p-green-500) !important;
}

.rating-card {
    background: linear-gradient(135deg, color-mix(in srgb, var(--p-cyan-500) 4%, var(--surface-card)) 0%, var(--surface-card) 100%) !important;
}
.rating-card:hover {
    border-color: var(--p-cyan-500) !important;
}

.admin-dashboard-stat-card__rail {
    position: absolute;
    top: 0;
    left: 0;
    width: 0.25rem;
    height: 100%;
}

.stat-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.stat-icon-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50%;
    transition: transform 0.2s ease;
}

.admin-dashboard-stat-card:hover .stat-icon-wrapper {
    transform: scale(1.1);
}

.stat-card-icon {
    font-size: 1.1rem;
}

.admin-dashboard-stat-card__label {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.stat-value-container {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    width: 100%;
    margin-top: 0.5rem;
}

.admin-dashboard-stat-card__value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-color);
    line-height: 1;
}

.stat-value-small {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-secondary-color);
    margin-left: 0.1rem;
}

.stat-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 4px;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.stat-badge--success {
    background: color-mix(in srgb, var(--p-green-500) 12%, transparent);
    color: var(--p-green-500);
}

.stat-badge--warning {
    background: color-mix(in srgb, var(--p-orange-500) 12%, transparent);
    color: var(--p-orange-500);
}

.stat-badge--info {
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
}

.stat-badge--cyan {
    background: color-mix(in srgb, var(--p-cyan-500) 12%, transparent);
    color: var(--p-cyan-500);
}

.stat-visual-container {
    margin-top: 0.75rem;
    height: 1.5rem;
    display: flex;
    align-items: center;
}

.stat-progress-bar-bg {
    width: 50%;
    height: 6px;
    background: var(--surface-border);
    border-radius: 999px;
    overflow: hidden;
    margin-right: 0.5rem;
}

.stat-progress-bar-fill {
    height: 100%;
    border-radius: 999px;
}

.stat-progress-text {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-secondary-color);
}

.stat-metric-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.45rem;
    font-size: 0.72rem;
    font-weight: 700;
    background: color-mix(in srgb, var(--p-orange-500) 10%, transparent);
    color: var(--p-orange-500);
    border-radius: 4px;
}

.mini-sparkline {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.spark-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--p-green-500);
    box-shadow: 0 0 8px var(--p-green-500);
}

.spark-text {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-secondary-color);
}

.stars-row {
    display: flex;
    align-items: center;
    gap: 0.15rem;
}

.star-active {
    color: var(--p-amber-500, #eab308);
    font-size: 0.75rem;
}

.stars-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-secondary-color);
}

.star-inactive {
    color: var(--surface-border);
    font-size: 0.75rem;
}

.admin-dashboard-stat-card__note {
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-secondary-color);
}

.admin-dashboard-content-grid {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
}

.admin-dashboard-main-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.admin-analytics-card {
    padding: 1.5rem;
}

.analytics-tabs {
    display: flex;
    gap: 0.35rem;
}

.analytics-tab-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 0.85rem;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary-color);
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    gap: 0.35rem;
}

.analytics-tab-btn i {
    font-size: 0.85rem;
}

.analytics-tab-btn:hover {
    color: var(--text-color);
    border-color: var(--primary-color);
}

.analytics-tab-btn.is-active {
    color: var(--primary-contrast-color);
    background: var(--primary-color);
    border-color: var(--primary-color);
}

.analytics-summary-row {
    display: flex;
    gap: 2rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    background: var(--surface-hover);
    padding: 1rem 1.25rem;
    border-radius: 6px;
    border: 1px solid var(--surface-border);
}

.analytics-summary-item {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 8rem;
}

.analytics-summary-value {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--text-color);
    line-height: 1.2;
}

.analytics-summary-trend {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    font-size: 0.76rem;
    font-weight: 700;
    margin-top: 0.1rem;
}

.analytics-summary-trend.positive {
    color: var(--p-green-500);
}

.chart-container {
    height: 220px;
    position: relative;
    width: 100%;
}

.analytics-svg-chart {
    width: 100%;
    height: 100%;
    overflow: visible;
}

.chart-bar-rect {
    cursor: pointer;
    transition: opacity 0.2s ease;
}

.chart-bar-rect:hover {
    opacity: 0.85;
}

.chart-point-circle {
    cursor: pointer;
    transition: r 0.15s ease, fill 0.15s ease;
}

.chart-line-path {
    stroke-dasharray: 1000;
    stroke-dashoffset: 1000;
    animation: drawLine 1.5s ease forwards;
}

.chart-area-path {
    animation: fadeIn 1s ease forwards;
    opacity: 0;
}

.tooltip-bg {
    filter: drop-shadow(0 2px 4px rgba(15, 23, 42, 0.04));
}

@keyframes drawLine {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes fadeIn {
    to {
        opacity: 1;
    }
}

.admin-dashboard-panel {
    padding: 1.25rem;
}

.admin-dashboard-panel--wide {
    min-width: 0;
}

.admin-dashboard-section-header {
    padding-bottom: 1rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--surface-border);
}

.admin-dashboard-section-header--compact {
    margin-bottom: 0.75rem;
}

.admin-dashboard-section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-color);
}

.admin-dashboard-section-copy {
    margin-top: 0.25rem;
    font-size: 0.8rem;
    color: var(--text-secondary-color);
}

.admin-dashboard-live-badge {
    border-radius: 9999px;
}

.admin-dashboard-timeline {
    gap: 1.25rem;
}

.admin-dashboard-timeline__item {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 1rem;
    align-items: start;
}

.admin-dashboard-timeline__time {
    flex-shrink: 0;
    padding: 0.35rem 0.65rem;
    background: color-mix(
        in srgb,
        var(--primary-color) 8%,
        var(--surface-card)
    );
    color: var(--primary-color);
    font-size: 0.75rem;
    font-weight: 700;
}

.admin-dashboard-timeline__content {
    display: grid;
    gap: 0.25rem;
}

.admin-dashboard-timeline__title {
    display: block;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-color);
}

.admin-dashboard-timeline__detail {
    font-size: 0.8rem;
    line-height: 1.55;
    color: var(--text-secondary-color);
}

.admin-dashboard-side-column {
    display: grid;
    gap: 1.5rem;
}

.admin-dashboard-focus-list {
    gap: 0.75rem;
}

.admin-dashboard-focus-card {
    padding: 0.9rem;
}

.admin-dashboard-focus-card__label {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.admin-dashboard-focus-card__value {
    display: block;
    margin-top: 0.35rem;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-color);
}

.admin-dashboard-focus-card__note {
    margin-top: 0.35rem;
    font-size: 0.8rem;
    line-height: 1.55;
    color: var(--text-secondary-color);
}

.admin-dashboard-system-card {
    padding: 1.25rem;
}

.admin-dashboard-system-card__label {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.72);
}

.admin-dashboard-system-card__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.25rem;
    color: #fff;
}

.admin-dashboard-system-card__icon {
    color: #34d399;
}

.admin-dashboard-system-card__copy {
    margin-top: 0.75rem;
    font-size: 0.8rem;
    line-height: 1.55;
    color: rgba(255, 255, 255, 0.72);
}

.admin-dashboard-actions-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.admin-dashboard-action-card {
    padding: 1rem;
}

.admin-dashboard-action-card__icon {
    display: block;
    margin-bottom: 0.75rem;
    font-size: 1.4rem;
    color: var(--primary-color);
}

.admin-dashboard-action-card__title {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-color);
}

.admin-dashboard-action-card__copy {
    font-size: 0.8rem;
    line-height: 1.55;
    color: var(--text-secondary-color);
}

@media (max-width: 991px) {
    .admin-dashboard-stats-grid,
    .admin-dashboard-content-grid,
    .admin-dashboard-actions-grid {
        grid-template-columns: 1fr;
    }
}
</style>
