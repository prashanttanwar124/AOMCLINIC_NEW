<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import Divider from 'primevue/divider';
import Tag from 'primevue/tag';
import DatePicker from 'primevue/datepicker';
import InputError from '@/components/InputError.vue';
import Textarea from 'primevue/textarea';
import { dashboard } from '@/routes';
import { bookingSettings } from '@/routes/admin';

defineOptions({
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Booking Settings', href: '/booking-settings' },
        ],
    },
});

type ClosureItem = {
    date: string;
    slot: string[];
};

type SettingsData = {
    morning_slot_capacity: number;
    evening_slot_capacity: number;
    booking_enabled: boolean;
    booking_open_days: number;
    morning_opening_time: string | null;
    morning_closing_time: string | null;
    evening_opening_time: string | null;
    evening_closing_time: string | null;
    clinic_closures: ClosureItem[];
    closed_days: number[];
    notice_enabled: boolean;
    notice_text: string | null;
};

const props = defineProps<{
    settings: SettingsData;
}>();

const form = useForm<SettingsData>({
    morning_slot_capacity: props.settings.morning_slot_capacity,
    evening_slot_capacity: props.settings.evening_slot_capacity,
    booking_enabled: props.settings.booking_enabled,
    booking_open_days: props.settings.booking_open_days,
    morning_opening_time: props.settings.morning_opening_time,
    morning_closing_time: props.settings.morning_closing_time,
    evening_opening_time: props.settings.evening_opening_time,
    evening_closing_time: props.settings.evening_closing_time,
    clinic_closures: [...props.settings.clinic_closures],
    closed_days: props.settings.closed_days ? [...props.settings.closed_days] : [],
    notice_enabled: props.settings.notice_enabled,
    notice_text: props.settings.notice_text,
});

const daysOfWeek = [
    { label: 'Sun', value: 0 },
    { label: 'Mon', value: 1 },
    { label: 'Tue', value: 2 },
    { label: 'Wed', value: 3 },
    { label: 'Thu', value: 4 },
    { label: 'Fri', value: 5 },
    { label: 'Sat', value: 6 },
];

function toggleClosedDay(dayValue: number) {
    const index = form.closed_days.indexOf(dayValue);
    if (index > -1) {
        form.closed_days.splice(index, 1);
    } else {
        form.closed_days.push(dayValue);
    }
}

const newClosureDate = ref<Date | string | null>(null);
const newClosureSlots = ref<string[]>([]);

function parseTimeToDate(timeStr: string | null): Date | null {
    if (!timeStr) {
        return null;
    }
    const parts = timeStr.split(':');
    if (parts.length < 2) {
        return null;
    }
    const hours = parseInt(parts[0], 10);
    const minutes = parseInt(parts[1], 10);
    if (isNaN(hours) || isNaN(minutes)) {
        return null;
    }
    const date = new Date();
    date.setHours(hours, minutes, 0, 0);
    return date;
}

function formatTimeToStr(date: Date | null): string | null {
    if (!date) {
        return null;
    }
    const hours = `${date.getHours()}`.padStart(2, '0');
    const minutes = `${date.getMinutes()}`.padStart(2, '0');
    return `${hours}:${minutes}`;
}

function toDateString(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const morningOpeningTimeModel = computed<Date | null>({
    get: () => parseTimeToDate(form.morning_opening_time),
    set: (val) => {
        form.morning_opening_time = formatTimeToStr(val);
    },
});

const morningClosingTimeModel = computed<Date | null>({
    get: () => parseTimeToDate(form.morning_closing_time),
    set: (val) => {
        form.morning_closing_time = formatTimeToStr(val);
    },
});

const eveningOpeningTimeModel = computed<Date | null>({
    get: () => parseTimeToDate(form.evening_opening_time),
    set: (val) => {
        form.evening_opening_time = formatTimeToStr(val);
    },
});

const eveningClosingTimeModel = computed<Date | null>({
    get: () => parseTimeToDate(form.evening_closing_time),
    set: (val) => {
        form.evening_closing_time = formatTimeToStr(val);
    },
});

function toggleNewClosureSlot(slotName: string) {
    const index = newClosureSlots.value.indexOf(slotName);
    if (index > -1) {
        newClosureSlots.value.splice(index, 1);
    } else {
        newClosureSlots.value.push(slotName);
    }
}

function addClosure() {
    if (!newClosureDate.value) {
        return;
    }
    if (newClosureSlots.value.length === 0) {
        return;
    }

    const dateStr = newClosureDate.value instanceof Date
        ? toDateString(newClosureDate.value)
        : String(newClosureDate.value);

    const existingIndex = form.clinic_closures.findIndex(
        (c) => c.date === dateStr
    );

    const closures = [...form.clinic_closures];

    if (existingIndex > -1) {
        const merged = Array.from(
            new Set([
                ...closures[existingIndex].slot,
                ...newClosureSlots.value,
            ])
        );
        closures[existingIndex] = {
            ...closures[existingIndex],
            slot: merged,
        };
    } else {
        closures.push({
            date: dateStr,
            slot: [...newClosureSlots.value],
        });
    }

    closures.sort((a, b) => a.date.localeCompare(b.date));
    form.clinic_closures = closures;

    newClosureDate.value = null;
    newClosureSlots.value = [];
}

function removeClosure(index: number) {
    form.clinic_closures = form.clinic_closures.filter((_, i) => i !== index);
}

function submitSettings() {
    form.patch(bookingSettings().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Booking Settings" />

    <Teleport to="#admin-header-actions">
        <Button
            label="Save Settings"
            icon="pi pi-save"
            severity="primary"
            class="font-semibold px-4"
            :loading="form.processing"
            @click="submitSettings"
        />
    </Teleport>

    <div class="page-grid booking-settings-page">


        <!-- Main Form Grid -->
        <div class="settings-grid">
            <!-- Left Column: Settings Cards -->
            <div class="settings-column">
                <!-- General Parameters -->
                <article class="page-card form-section-card">
                    <h3 class="card-title">General Scheduling Rules</h3>
                    <p class="card-subtitle">Control the status and window of appointment bookings.</p>
                    <Divider class="my-3" />

                    <div class="flex flex-col gap-4">
                        <!-- Toggle Card for Booking Enabled -->
                        <div>
                            <div
                                class="premium-toggle-card"
                                :class="{ 'premium-toggle-card--active': form.booking_enabled }"
                                @click="form.booking_enabled = !form.booking_enabled"
                            >
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex flex-col gap-1 pr-4">
                                        <span class="toggle-card-label">Enable Online Booking</span>
                                        <span class="toggle-card-description">
                                            Allow patients to search for tokens and complete bookings. If disabled, new online appointments cannot be registered.
                                        </span>
                                    </div>
                                    <div class="custom-switch">
                                        <span class="custom-switch-slider"></span>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="form.errors.booking_enabled" class="mt-1" />
                        </div>

                        <!-- Toggle Card for Notice Banner -->
                        <div>
                            <div
                                class="premium-toggle-card"
                                :class="{ 'premium-toggle-card--active': form.notice_enabled }"
                                @click="form.notice_enabled = !form.notice_enabled"
                            >
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex flex-col gap-1 pr-4">
                                        <span class="toggle-card-label">Show Special Notice Banner</span>
                                        <span class="toggle-card-description">
                                            Display an announcement or important message to patients during checkout.
                                        </span>
                                    </div>
                                    <div class="custom-switch">
                                        <span class="custom-switch-slider"></span>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="form.errors.notice_enabled" class="mt-1" />

                            <!-- Dynamic Notice Message -->
                            <div v-if="form.notice_enabled" class="flex flex-col gap-2 p-4 rounded-xl border border-surface-border bg-surface-card mt-2">
                                <label for="notice_text" class="text-sm font-bold text-color">
                                    Notice Message
                                </label>
                                <span class="text-xs text-secondary-color -mt-1">
                                    This message will be shown to patients on the booking page.
                                </span>
                                <Textarea
                                    id="notice_text"
                                    v-model="form.notice_text"
                                    rows="3"
                                    placeholder="e.g. Clinic closed on Sunday due to weekly maintenance."
                                    class="w-full mt-2 text-sm"
                                    fluid
                                />
                                <InputError :message="form.errors.notice_text" class="mt-1" />
                            </div>
                        </div>

                        <!-- Weekly Closed Days -->
                        <div class="flex flex-col gap-2 p-4 rounded-xl border border-surface-border bg-surface-card mt-1">
                            <label class="text-sm font-bold text-color">
                                Weekly Closed Days
                            </label>
                            <span class="text-xs text-secondary-color -mt-1">
                                Select the days of the week when the clinic is closed.
                            </span>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <button
                                    v-for="day in daysOfWeek"
                                    :key="day.value"
                                    type="button"
                                    class="day-toggle-btn"
                                    :class="{ 'day-toggle-btn--active': form.closed_days.includes(day.value) }"
                                    @click="toggleClosedDay(day.value)"
                                >
                                    {{ day.label }}
                                </button>
                            </div>
                            <InputError :message="form.errors.closed_days" class="mt-1" />
                        </div>

                        <!-- Booking Open Days -->
                        <div class="flex flex-col gap-2 p-4 rounded-xl border border-surface-border bg-surface-card mt-1">
                            <label for="booking_open_days" class="text-sm font-bold text-color">
                                Booking Window (Days)
                            </label>
                            <span class="text-xs text-secondary-color -mt-1">
                                Maximum number of days in advance patients can select for bookings.
                            </span>
                            <div class="flex items-center gap-4 mt-2">
                                <InputNumber
                                    id="booking_open_days"
                                    v-model="form.booking_open_days"
                                    :min="1"
                                    :max="30"
                                    showButtons
                                    class="w-48"
                                />
                                <div class="text-xs font-semibold text-secondary-color flex items-center gap-1.5">
                                    <i class="pi pi-info-circle"></i>
                                    <span>Active booking range: Today through {{ form.booking_open_days }} days from now.</span>
                                </div>
                            </div>
                            <InputError :message="form.errors.booking_open_days" class="mt-1" />
                        </div>
                    </div>
                </article>

                <!-- Session capacities & timings -->
                <article class="page-card form-section-card">
                    <h3 class="card-title">Session Constraints & Timings</h3>
                    <p class="card-subtitle">Manage token capacities and active hours for morning/evening slots.</p>
                    <Divider class="my-3" />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Morning Slot -->
                        <div class="session-card session-card--morning border border-surface-border">
                            <div class="session-card-header">
                                <h4 class="text-sm font-bold text-color flex items-center gap-2">
                                    <i class="pi pi-sun text-amber-500 text-lg"></i> Morning Session
                                </h4>
                            </div>
                            <div class="session-card-body flex flex-col gap-4">
                                <div class="flex flex-col gap-1.5">
                                    <label for="morning_slot_capacity" class="text-xs font-bold text-color">
                                        Token Slot Capacity
                                    </label>
                                    <InputNumber
                                        id="morning_slot_capacity"
                                        v-model="form.morning_slot_capacity"
                                        :min="0"
                                        class="w-full"
                                    />
                                    <InputError :message="form.errors.morning_slot_capacity" />
                                    <span class="text-xxs text-secondary-color">Maximum tokens issues per morning.</span>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label for="morning_opening_time" class="text-xs font-bold text-color">
                                        Opening Time
                                    </label>
                                    <DatePicker
                                        id="morning_opening_time"
                                        v-model="morningOpeningTimeModel"
                                        timeOnly
                                        hourFormat="12"
                                        placeholder="hh:mm AM/PM"
                                        fluid
                                    />
                                    <InputError :message="form.errors.morning_opening_time" />
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label for="morning_closing_time" class="text-xs font-bold text-color">
                                        Closing Time
                                    </label>
                                    <DatePicker
                                        id="morning_closing_time"
                                        v-model="morningClosingTimeModel"
                                        timeOnly
                                        hourFormat="12"
                                        placeholder="hh:mm AM/PM"
                                        fluid
                                    />
                                    <InputError :message="form.errors.morning_closing_time" />
                                </div>
                            </div>
                        </div>

                        <!-- Evening Slot -->
                        <div class="session-card session-card--evening border border-surface-border">
                            <div class="session-card-header">
                                <h4 class="text-sm font-bold text-color flex items-center gap-2">
                                    <i class="pi pi-moon text-indigo-500 text-lg"></i> Evening Session
                                </h4>
                            </div>
                            <div class="session-card-body flex flex-col gap-4">
                                <div class="flex flex-col gap-1.5">
                                    <label for="evening_slot_capacity" class="text-xs font-bold text-color">
                                        Token Slot Capacity
                                    </label>
                                    <InputNumber
                                        id="evening_slot_capacity"
                                        v-model="form.evening_slot_capacity"
                                        :min="0"
                                        class="w-full"
                                    />
                                    <InputError :message="form.errors.evening_slot_capacity" />
                                    <span class="text-xxs text-secondary-color">Maximum tokens issues per evening.</span>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label for="evening_opening_time" class="text-xs font-bold text-color">
                                        Opening Time
                                    </label>
                                    <DatePicker
                                        id="evening_opening_time"
                                        v-model="eveningOpeningTimeModel"
                                        timeOnly
                                        hourFormat="12"
                                        placeholder="hh:mm AM/PM"
                                        fluid
                                    />
                                    <InputError :message="form.errors.evening_opening_time" />
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label for="evening_closing_time" class="text-xs font-bold text-color">
                                        Closing Time
                                    </label>
                                    <DatePicker
                                        id="evening_closing_time"
                                        v-model="eveningClosingTimeModel"
                                        timeOnly
                                        hourFormat="12"
                                        placeholder="hh:mm AM/PM"
                                        fluid
                                    />
                                    <InputError :message="form.errors.evening_closing_time" />
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Right Column: Clinic Closures -->
            <div class="settings-column">
                <article class="page-card form-section-card height-matching-card">
                    <h3 class="card-title">Clinic Closures</h3>
                    <p class="card-subtitle">Block specific dates and sessions from patient booking.</p>
                    <Divider class="my-3" />

                    <!-- Add Closure -->
                    <div class="p-4 rounded-xl border border-surface-border bg-surface-hover mb-4">
                        <h4 class="text-xs font-bold mb-3 uppercase tracking-wider text-secondary-color">
                            Block Slot Date
                        </h4>

                        <div class="flex flex-col gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold text-color">Closure Date</label>
                                <DatePicker
                                    v-model="newClosureDate"
                                    dateFormat="yy-mm-dd"
                                    placeholder="Select Date"
                                    fluid
                                />
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold text-color">Closed Sessions</label>
                                <div class="flex gap-2 py-1">
                                    <button
                                        type="button"
                                        class="session-toggle-btn"
                                        :class="{ 'session-toggle-btn--active session-toggle-btn--morning': newClosureSlots.includes('Morning') }"
                                        @click="toggleNewClosureSlot('Morning')"
                                    >
                                        <i class="pi pi-sun"></i>
                                        <span>Morning</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="session-toggle-btn"
                                        :class="{ 'session-toggle-btn--active session-toggle-btn--evening': newClosureSlots.includes('Evening') }"
                                        @click="toggleNewClosureSlot('Evening')"
                                    >
                                        <i class="pi pi-moon"></i>
                                        <span>Evening</span>
                                    </button>
                                </div>
                            </div>

                            <Button
                                label="Add Closure Rule"
                                icon="pi pi-plus"
                                size="small"
                                severity="secondary"
                                class="mt-2 w-full font-semibold"
                                :disabled="!newClosureDate || newClosureSlots.length === 0"
                                @click="addClosure"
                            />
                        </div>
                    </div>
                    <InputError :message="form.errors.clinic_closures" class="mb-3" />

                    <!-- Closure List -->
                    <div class="closures-list-container">
                        <h4 class="text-xs font-bold mb-3 uppercase tracking-wider text-secondary-color">
                            Scheduled Closure Rules
                        </h4>

                        <ul v-if="form.clinic_closures.length > 0" class="closures-list">
                            <li
                                v-for="(closure, index) in form.clinic_closures"
                                :key="closure.date"
                                class="closure-item"
                            >
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-bold text-sm text-color">{{ closure.date }}</span>
                                    <div class="flex gap-1 mt-1">
                                        <Tag
                                            v-for="s in closure.slot"
                                            :key="s"
                                            :value="s"
                                            :severity="s === 'Morning' ? 'warning' : 'info'"
                                            rounded
                                            class="text-xxs px-2 py-0.5 font-bold"
                                        />
                                    </div>
                                    <!-- Nested Validation Error Display -->
                                    <InputError :message="form.errors[`clinic_closures.${index}.date`]" />
                                    <InputError :message="form.errors[`clinic_closures.${index}.slot`]" />
                                </div>
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    rounded
                                    size="small"
                                    @click="removeClosure(index)"
                                />
                            </li>
                        </ul>

                        <div v-else class="closures-empty">
                            <i class="pi pi-calendar-minus text-4xl text-secondary-color opacity-50 mb-2"></i>
                            <p class="text-sm text-secondary-color italic font-medium">No closure dates set</p>
                            <p class="text-xxs text-secondary-color opacity-70 mt-1 max-w-xs">
                                Add closure rules above to block specific calendar days or slots from booking list.
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <!-- Submit Bar -->
        <section class="page-card flex justify-end gap-3 mt-4">
            <Button
                label="Save Settings"
                icon="pi pi-save"
                severity="primary"
                class="font-semibold px-4"
                :loading="form.processing"
                @click="submitSettings"
            />
        </section>
    </div>
</template>

<style scoped>
.booking-settings-page {
    gap: 1.5rem;
}

.settings-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

@media (min-width: 1024px) {
    .settings-grid {
        grid-template-columns: 1.25fr 1fr;
    }
}

.settings-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.card-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-color);
}

.card-subtitle {
    font-size: 0.825rem;
    color: var(--text-secondary-color);
    margin-top: 0.25rem;
}

/* Premium Settings Toggle Cards */
.premium-toggle-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.15rem;
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    background: var(--surface-card);
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.premium-toggle-card:hover {
    border-color: var(--primary-color);
    background: var(--surface-hover);
}

.premium-toggle-card--active {
    border-color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 4%, var(--surface-card));
}

.toggle-card-label {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-color);
}

.toggle-card-description {
    font-size: 0.775rem;
    color: var(--text-secondary-color);
    line-height: 1.4;
    margin-top: 0.15rem;
}

/* Custom Switch styling */
.custom-switch {
    width: 44px;
    height: 24px;
    background: var(--surface-border);
    border-radius: 99px;
    position: relative;
    flex-shrink: 0;
    transition: background-color 0.25s ease;
}

.premium-toggle-card--active .custom-switch {
    background: var(--primary-color);
}

.custom-switch-slider {
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    position: absolute;
    top: 3px;
    left: 3px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.premium-toggle-card--active .custom-switch-slider {
    transform: translateX(20px);
}

/* Custom Session Cards */
.session-card {
    border-radius: 12px;
    overflow: hidden;
    background: var(--surface-card);
}

.session-card-header {
    padding: 0.85rem 1rem;
    background: var(--surface-hover);
    border-bottom: 1px solid var(--surface-border);
}

.session-card--morning {
    border-top: 4px solid #f59e0b;
}

.session-card--evening {
    border-top: 4px solid #6366f1;
}

.session-card-body {
    padding: 1.25rem 1rem;
}

/* Text & Date Inputs */
.custom-time-input,
.custom-date-input {
    width: 100%;
    padding: 0.55rem 0.85rem;
    font-size: 0.875rem;
    font-weight: 500;
    background: var(--surface-input);
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    color: var(--text-color);
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.custom-time-input:focus,
.custom-date-input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary-color) 20%, transparent);
}

/* Custom Session Toggle Buttons in closures */
.session-toggle-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.15rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-card);
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    color: var(--text-color) !important;
    transition: all 0.2s ease;
}

.session-toggle-btn i,
.session-toggle-btn span {
    color: var(--text-color) !important;
}

.session-toggle-btn:hover {
    background: var(--surface-hover);
    border-color: var(--primary-color);
}

.session-toggle-btn--active {
    color: #fff !important;
}

.session-toggle-btn--active.session-toggle-btn--morning {
    background: #f59e0b !important;
    border-color: #f59e0b !important;
}

.session-toggle-btn--active.session-toggle-btn--evening {
    background: #6366f1 !important;
    border-color: #6366f1 !important;
}

.session-toggle-btn--active i,
.session-toggle-btn--active span {
    color: #fff !important;
}

.text-amber-500 {
    color: #f59e0b !important;
}

.text-indigo-500 {
    color: #6366f1 !important;
}

/* Closures list styling */
.closures-list-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    margin-top: 0.5rem;
}

.closures-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 0.5rem;
    max-height: 260px;
    overflow-y: auto;
}

.closure-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--surface-hover);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    transition: background-color 0.15s;
}

.closure-item:hover {
    background: color-mix(in srgb, var(--primary-color) 2%, var(--surface-hover));
}

.closures-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1.5rem;
    border: 2px dashed var(--surface-border);
    border-radius: 12px;
    text-align: center;
}

.height-matching-card {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.text-xxs {
    font-size: 0.7rem;
}

/* Custom Spacing & Flexbox Utilities */
.flex {
    display: flex;
}

.flex-col {
    display: flex;
    flex-direction: column;
}

.flex-wrap {
    flex-wrap: wrap;
}

.items-center {
    align-items: center;
}

.justify-between {
    justify-content: space-between;
}

.justify-end {
    justify-content: flex-end;
}

.w-full {
    width: 100%;
}

.w-48 {
    width: 12rem;
}

.gap-0\.5 {
    gap: 0.125rem;
}

.gap-1 {
    gap: 0.25rem;
}

.gap-1\.5 {
    gap: 0.375rem;
}

.gap-2 {
    gap: 0.5rem;
}

.gap-3 {
    gap: 0.75rem;
}

.gap-4 {
    gap: 1rem;
}

.gap-6 {
    gap: 1.5rem;
}

.mt-1 {
    margin-top: 0.25rem;
}

.mt-2 {
    margin-top: 0.5rem;
}

.mt-4 {
    margin-top: 1rem;
}

.mb-3 {
    margin-bottom: 0.75rem;
}

.mb-4 {
    margin-bottom: 1rem;
}

.my-3 {
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}

.-mt-1 {
    margin-top: -0.25rem;
}

.pr-4 {
    padding-right: 1rem;
}

.py-1 {
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
}

.p-4 {
    padding: 1rem;
}

.grid {
    display: grid;
}

.grid-cols-1 {
    grid-template-columns: 1fr;
}

.rounded-xl {
    border-radius: 0.75rem;
}

.border {
    border-width: 1px;
}

.border-surface-border {
    border-color: var(--surface-border);
}

.bg-surface-card {
    background-color: var(--surface-card);
}

.bg-surface-hover {
    background-color: var(--surface-hover);
}

.text-sm {
    font-size: 0.875rem;
}

.text-xs {
    font-size: 0.75rem;
}

.font-semibold {
    font-weight: 600;
}

.font-bold {
    font-weight: 700;
}

.text-color {
    color: var(--text-color);
}

.text-secondary-color {
    color: var(--text-secondary-color);
}

.uppercase {
    text-transform: uppercase;
}

.tracking-wider {
    letter-spacing: 0.05em;
}

.italic {
    font-style: italic;
}

.opacity-50 {
    opacity: 0.5;
}

.opacity-70 {
    opacity: 0.7;
}

.max-w-xs {
    max-width: 20rem;
}

@media (min-width: 768px) {
    .md\:grid-cols-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.day-toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-color);
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.day-toggle-btn:hover {
    border-color: var(--primary-color);
    background: var(--surface-hover);
}

.day-toggle-btn--active {
    background: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: #fff !important;
}

</style>
