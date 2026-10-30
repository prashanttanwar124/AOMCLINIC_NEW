<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import Button from 'primevue/button';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { computed, reactive, watch } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Patient/PatientAppointmentController';
import InputError from '@/components/InputError.vue';
import http, { extractErrors, pushToast } from '@/lib/http';
import { dashboard } from '@/routes/patient';

type SessionOption = {
    availableTokens: number[];
    closed: boolean;
    disabled: boolean;
    label: string;
    remaining: number;
    value: string;
};

type DateOption = {
    label: string;
    sessions: SessionOption[];
    value: string;
    disabled: boolean;
};

type AppointmentTypeOption = {
    label: string;
    value: string;
};

type TokenGroupOption = {
    label: string;
    token: number;
};

const props = defineProps<{
    appointmentTypes: AppointmentTypeOption[];
    bookablePatients: Array<{ id: number; name: string }>;
    defaultPatientId: number;
    dateOptions: DateOption[];
    bookingEnabled: boolean;
    noticeEnabled: boolean;
    noticeText: string | null;
}>();

defineOptions({
    layout: {
        title: 'Book appointment',
    },
});

const toast = useToast();

useEchoPublic('live-queue', 'QueueUpdated', () => {
    router.reload({
        only: ['dateOptions', 'bookingEnabled', 'noticeEnabled', 'noticeText'],
    });
});

const form = reactive({
    patient_id: props.defaultPatientId,
    appointment_date:
        props.dateOptions.find((option) => !option.disabled)?.value ??
        props.dateOptions[0]?.value ??
        '',
    appointment_session: '',
    appointment_number: null as number | null,
    appointment_type: props.appointmentTypes[0]?.value ?? 'new',
    reason_for_visit: '',
    errors: {} as Record<string, string>,
    processing: false,
});

const selectedPatientName = computed(() => {
    const found = props.bookablePatients.find((p) => p.id === form.patient_id);
    return found ? found.name : 'Selected Patient';
});

const selectedDate = computed(() => {
    return (
        props.dateOptions.find(
            (option) => option.value === form.appointment_date,
        ) ?? null
    );
});

const sessionOptions = computed(() => selectedDate.value?.sessions ?? []);

watch(
    sessionOptions,
    (options) => {
        const activeSession = options.find(
            (option) =>
                option.value === form.appointment_session && !option.disabled,
        );

        if (activeSession) {
            return;
        }

        form.appointment_session =
            options.find((option) => !option.disabled)?.value ?? '';
    },
    { immediate: true },
);

const selectedSession = computed(() => {
    return (
        sessionOptions.value.find(
            (option) => option.value === form.appointment_session,
        ) ?? null
    );
});

const selectedAppointmentType = computed(() => {
    return (
        props.appointmentTypes.find(
            (option) => option.value === form.appointment_type,
        ) ?? null
    );
});

const tokenGroupOptions = computed<TokenGroupOption[]>(() => {
    const groups = new Map<number, number[]>();

    for (const token of selectedSession.value?.availableTokens ?? []) {
        const groupStart = Math.floor((token - 1) / 5) * 5 + 1;
        const availableTokens = groups.get(groupStart) ?? [];

        availableTokens.push(token);
        groups.set(groupStart, availableTokens);
    }

    return [...groups.entries()].map(([, availableTokens]) => {
        const minToken = Math.min(...availableTokens);
        const maxToken = Math.max(...availableTokens);
        const label =
            minToken === maxToken ? `${minToken}` : `${minToken}-${maxToken}`;
        return {
            label,
            token: minToken,
        };
    });
});

const selectedTokenLabel = computed(() => {
    return form.appointment_number
        ? `Token #${form.appointment_number}`
        : 'Not selected';
});

const selectedTokenGroupLabel = computed(() => {
    if (!form.appointment_number) {
        return 'None';
    }

    const start = Math.floor((form.appointment_number - 1) / 5) * 5 + 1;

    return `${start}–${start + 4}`;
});

watch(
    tokenGroupOptions,
    (options) => {
        const activeTokenGroup = options.find(
            (option) => option.token === form.appointment_number,
        );

        if (activeTokenGroup) {
            return;
        }

        form.appointment_number = options[0]?.token ?? null;
    },
    { immediate: true },
);

const chooseDate = (dateOption: DateOption): void => {
    if (!props.bookingEnabled || dateOption.disabled) {
        return;
    }
    form.appointment_date = dateOption.value;
};

const chooseSession = (session: SessionOption): void => {
    if (session.disabled) {
        return;
    }

    form.appointment_session = session.value;
};

const chooseTokenGroup = (tokenGroup: TokenGroupOption): void => {
    form.appointment_number = tokenGroup.token;
};

const canSubmit = computed(
    () =>
        props.bookingEnabled &&
        Boolean(form.appointment_date) &&
        Boolean(form.appointment_session) &&
        Boolean(form.appointment_number),
);

const submit = async (): Promise<void> => {
    if (!canSubmit.value || form.processing) {
        return;
    }

    form.processing = true;
    form.errors = {};

    try {
        const { data } = await http.post(store().url, {
            patient_id: form.patient_id,
            appointment_date: form.appointment_date,
            appointment_session: form.appointment_session,
            appointment_number: form.appointment_number,
            appointment_type: form.appointment_type,
            reason_for_visit: form.reason_for_visit,
        });

        pushToast(toast, data.toast);
        router.visit(data.redirect ?? dashboard().url);
    } catch (error) {
        form.errors = extractErrors(error);
    } finally {
        form.processing = false;
    }
};
</script>

<template>
    <Head title="Book Appointment" />

    <div class="booking-page">
        <Message
            v-if="noticeEnabled"
            severity="info"
            class="w-full"
            :closable="false"
        >
            {{
                noticeText ||
                'Special Notice: The clinic is currently experiencing high call volume. You can still book your slot online, but please expect slight delays.'
            }}
        </Message>

        <Message
            v-if="!bookingEnabled"
            severity="warn"
            class="w-full"
            :closable="false"
        >
            Online appointment booking is currently closed. Please contact the
            clinic directly to schedule an appointment.
        </Message>

        <form @submit.prevent="submit" class="booking-split-layout">
            <!-- Left Column: Main Booking Form Card -->
            <section class="page-card booking-form-card">
                <!-- Patient & Visit Type Row -->
                <div class="form-two-col">
                    <div class="field-group">
                        <label for="patient_id" class="field-title">
                            Book Appointment For
                        </label>
                        <Select
                            id="patient_id"
                            v-model="form.patient_id"
                            :options="bookablePatients"
                            option-label="name"
                            option-value="id"
                            :disabled="!bookingEnabled"
                            placeholder="Select patient"
                            fluid
                        />
                        <InputError :message="form.errors.patient_id" />
                    </div>

                    <div class="field-group">
                        <label class="field-title">Consultation Type</label>
                        <div class="type-toggle-row">
                            <button
                                v-for="typeOption in appointmentTypes"
                                :key="typeOption.value"
                                type="button"
                                class="type-option-btn"
                                :class="{
                                    'type-option-btn--active':
                                        form.appointment_type ===
                                        typeOption.value,
                                }"
                                :disabled="!bookingEnabled"
                                @click="
                                    form.appointment_type = typeOption.value
                                "
                            >
                                <i
                                    :class="
                                        typeOption.value === 'new'
                                            ? 'pi pi-user-plus'
                                            : 'pi pi-refresh'
                                    "
                                />
                                <span>{{ typeOption.label }}</span>
                            </button>
                        </div>
                        <InputError :message="form.errors.appointment_type" />
                    </div>
                </div>

                <!-- Date Picker Cards -->
                <div class="field-group">
                    <div class="field-title-row">
                        <label class="field-title">Select Visit Date</label>
                        <span class="field-hint">
                            Tap an open day to view session availability
                        </span>
                    </div>

                    <div class="date-cards-grid">
                        <button
                            v-for="(option, index) in dateOptions"
                            :key="option.value"
                            type="button"
                            class="date-card-btn"
                            :class="{
                                'date-card-btn--active':
                                    option.value === form.appointment_date,
                                'date-card-btn--disabled': option.disabled,
                            }"
                            :disabled="!bookingEnabled || option.disabled"
                            @click="chooseDate(option)"
                        >
                            <span class="date-card-btn__weekday">
                                {{
                                    index === 0
                                        ? 'Today'
                                        : index === 1
                                          ? 'Tomorrow'
                                          : option.label.split(',')[0]
                                }}
                            </span>
                            <strong class="date-card-btn__day">
                                {{
                                    option.label
                                        .replace(/^[^,]+,\s*/, '')
                                        .replace(/\s+\d{4}$/, '')
                                }}
                            </strong>
                            <span
                                class="date-card-btn__status"
                                :class="{ 'is-closed': option.disabled }"
                            >
                                {{ option.disabled ? 'Closed' : 'Open' }}
                            </span>
                        </button>
                    </div>
                    <InputError :message="form.errors.appointment_date" />
                </div>

                <!-- Session Selection -->
                <div class="field-group">
                    <div class="field-title-row">
                        <label class="field-title">Choose Session</label>
                        <span class="field-hint">
                            Live clinic capacity for
                            {{ selectedDate?.label ?? 'selected date' }}
                        </span>
                    </div>

                    <div class="session-cards-grid">
                        <button
                            v-for="option in sessionOptions"
                            :key="option.value"
                            type="button"
                            class="session-card-btn"
                            :class="{
                                'session-card-btn--active':
                                    option.value === form.appointment_session,
                                'session-card-btn--disabled': option.disabled,
                            }"
                            :disabled="option.disabled"
                            @click="chooseSession(option)"
                        >
                            <div class="session-card-btn__header">
                                <div class="session-card-btn__title">
                                    <span class="session-icon-box">
                                        <i
                                            :class="
                                                option.value === 'morning'
                                                    ? 'pi pi-sun'
                                                    : 'pi pi-moon'
                                            "
                                        />
                                    </span>
                                    <div>
                                        <strong>{{ option.label }}</strong>
                                        <p class="session-card-btn__sub">
                                            {{
                                                option.disabled
                                                    ? 'Unavailable right now'
                                                    : `Starts at Token #${option.availableTokens[0] ?? '-'}`
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <Tag
                                    :severity="
                                        option.disabled ? 'danger' : 'success'
                                    "
                                    :value="
                                        option.disabled
                                            ? 'Closed'
                                            : `${option.remaining} left`
                                    "
                                    rounded
                                />
                            </div>
                        </button>
                    </div>
                    <InputError :message="form.errors.appointment_session" />
                </div>

                <!-- Token Group Selection -->
                <div class="field-group">
                    <div class="field-title-row">
                        <label class="field-title">Pick Token Group</label>
                        <span class="field-hint">
                            We assign the first free token from your chosen
                            group of 5
                        </span>
                    </div>

                    <div
                        v-if="tokenGroupOptions.length > 0"
                        class="token-pills-grid"
                    >
                        <button
                            v-for="tokenGroup in tokenGroupOptions"
                            :key="tokenGroup.label"
                            type="button"
                            class="token-group-pill"
                            :class="{
                                'token-group-pill--active':
                                    tokenGroup.token ===
                                    form.appointment_number,
                            }"
                            @click="chooseTokenGroup(tokenGroup)"
                        >
                            <span class="token-group-pill__range">
                                {{ tokenGroup.label }}
                            </span>
                            <small class="token-group-pill__meta">
                                Token #{{ tokenGroup.token }}
                            </small>
                        </button>
                    </div>

                    <div v-else class="token-empty-notice">
                        Select an available session above to view open token
                        groups.
                    </div>
                    <InputError :message="form.errors.appointment_number" />
                </div>

                <!-- Optional Note -->
                <div class="field-group">
                    <div class="field-title-row">
                        <label for="reason_for_visit" class="field-title">
                            Reason for Visit
                        </label>
                        <span class="field-hint">Optional note for clinic</span>
                    </div>
                    <Textarea
                        id="reason_for_visit"
                        v-model="form.reason_for_visit"
                        rows="2"
                        placeholder="Share symptoms or reason for your visit (optional)..."
                        :disabled="!bookingEnabled"
                        auto-resize
                        fluid
                    />
                    <InputError :message="form.errors.reason_for_visit" />
                </div>
            </section>

            <!-- Right Column: Live Ticket Summary & Confirm Action -->
            <aside class="booking-summary-column">
                <section class="page-card summary-ticket-card">
                    <!-- Gradient Token Hero matching Dashboard primary-stat-card -->
                    <div class="ticket-hero">
                        <div class="ticket-hero__top">
                            <span class="ticket-hero__eyebrow">
                                Assigned Queue Spot
                            </span>
                            <span class="ticket-hero__group">
                                Group {{ selectedTokenGroupLabel }}
                            </span>
                        </div>
                        <div class="ticket-hero__token">
                            {{ selectedTokenLabel }}
                        </div>
                        <div class="ticket-hero__sub">
                            {{
                                selectedDate
                                    ? `${selectedDate.label} · ${selectedSession?.label ?? 'Select session'}`
                                    : 'Choose a date and session'
                            }}
                        </div>
                    </div>

                    <div class="ticket-details-list">
                        <div class="ticket-detail-row">
                            <span class="ticket-detail-row__label">
                                <i class="pi pi-user" /> Patient
                            </span>
                            <strong class="ticket-detail-row__value">
                                {{ selectedPatientName }}
                            </strong>
                        </div>

                        <div class="ticket-detail-row">
                            <span class="ticket-detail-row__label">
                                <i class="pi pi-briefcase" /> Visit Type
                            </span>
                            <strong class="ticket-detail-row__value">
                                {{
                                    selectedAppointmentType?.label ??
                                    'New consultation'
                                }}
                            </strong>
                        </div>

                        <div class="ticket-detail-row">
                            <span class="ticket-detail-row__label">
                                <i class="pi pi-calendar" /> Visit Date
                            </span>
                            <strong class="ticket-detail-row__value">
                                {{ selectedDate?.label ?? 'Not selected' }}
                            </strong>
                        </div>

                        <div class="ticket-detail-row">
                            <span class="ticket-detail-row__label">
                                <i class="pi pi-clock" /> Session
                            </span>
                            <strong class="ticket-detail-row__value">
                                {{
                                    selectedSession
                                        ? `${selectedSession.label} (${selectedSession.remaining} open)`
                                        : 'Not selected'
                                }}
                            </strong>
                        </div>
                    </div>

                    <div class="ticket-actions">
                        <Button
                            type="submit"
                            label="Confirm Booking"
                            icon="pi pi-check-circle"
                            class="w-full confirm-booking-btn"
                            :loading="form.processing"
                            :disabled="!canSubmit"
                        />

                        <Link :href="dashboard().url" class="w-full">
                            <Button
                                type="button"
                                label="Back to Dashboard"
                                icon="pi pi-arrow-left"
                                severity="secondary"
                                outlined
                                class="w-full"
                            />
                        </Link>
                    </div>
                </section>
            </aside>
        </form>
    </div>
</template>

<style scoped>
.booking-page {
    display: grid;
    gap: 1rem;
}

.booking-split-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(20rem, 0.95fr);
    gap: 1.25rem;
    align-items: start;
}

.booking-form-card {
    display: grid;
    gap: 1.25rem;
    padding: 1.5rem;
}

.form-two-col {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.field-group {
    display: grid;
    gap: 0.55rem;
}

.field-title-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.field-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-color);
}

.field-hint {
    font-size: 0.82rem;
    color: var(--text-secondary-color);
}

/* Consultation Type Toggle */
.type-toggle-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.type-option-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-height: 2.75rem;
    padding: 0.55rem 0.85rem;
    border-radius: var(--content-border-radius);
    border: 1px solid var(--surface-border);
    background: var(--surface-ground);
    color: var(--text-color);
    font-size: 0.92rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s ease;
}

.type-option-btn:hover:not(:disabled) {
    border-color: var(--primary-color);
}

.type-option-btn--active {
    background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-card));
    border-color: var(--primary-color);
    color: var(--primary-color);
    font-weight: 700;
}

/* Date Cards Grid */
.date-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(6.8rem, 1fr));
    gap: 0.6rem;
}

.date-card-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.15rem;
    padding: 0.7rem 0.5rem;
    border-radius: var(--content-border-radius);
    border: 1px solid var(--surface-border);
    background: var(--surface-ground);
    cursor: pointer;
    transition: all 0.18s ease;
}

.date-card-btn:hover:not(:disabled) {
    border-color: var(--primary-color);
    transform: translateY(-1px);
}

.date-card-btn__weekday {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary-color);
}

.date-card-btn__day {
    font-size: 1rem;
    font-weight: 800;
    color: var(--text-color);
}

.date-card-btn__status {
    font-size: 0.7rem;
    font-weight: 700;
    color: #10b981;
    margin-top: 0.1rem;
}

.date-card-btn__status.is-closed {
    color: #ef4444;
}

.date-card-btn--active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: #ffffff;
    box-shadow: 0 6px 16px color-mix(in srgb, var(--primary-color) 28%, transparent);
}

.date-card-btn--active .date-card-btn__weekday,
.date-card-btn--active .date-card-btn__day,
.date-card-btn--active .date-card-btn__status {
    color: #ffffff;
}

.date-card-btn--disabled {
    opacity: 0.48;
    cursor: not-allowed;
}

/* Session Cards Grid */
.session-cards-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.session-card-btn {
    padding: 0.95rem 1.05rem;
    border-radius: var(--content-border-radius);
    border: 1px solid var(--surface-border);
    background: var(--surface-ground);
    text-align: left;
    cursor: pointer;
    transition: all 0.18s ease;
}

.session-card-btn:hover:not(:disabled) {
    border-color: var(--primary-color);
    transform: translateY(-1px);
}

.session-card-btn--active {
    background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-card));
    border-color: var(--primary-color);
    box-shadow: inset 0 0 0 1px var(--primary-color);
}

.session-card-btn--disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.session-card-btn__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.session-card-btn__title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.session-icon-box {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.65rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-card));
    color: var(--primary-color);
    font-size: 1.1rem;
    flex-shrink: 0;
}

.session-card-btn__title strong {
    display: block;
    font-size: 1rem;
    color: var(--text-color);
}

.session-card-btn__sub {
    margin: 0.15rem 0 0;
    font-size: 0.8rem;
    color: var(--text-secondary-color);
}

/* Token Group Pills */
.token-pills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(6.5rem, 1fr));
    gap: 0.6rem;
}

.token-group-pill {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.65rem 0.5rem;
    border-radius: var(--content-border-radius);
    border: 1px solid var(--surface-border);
    background: var(--surface-ground);
    color: var(--text-color);
    cursor: pointer;
    transition: all 0.18s ease;
}

.token-group-pill:hover {
    border-color: var(--primary-color);
    transform: translateY(-1px);
}

.token-group-pill__range {
    font-size: 0.98rem;
    font-weight: 800;
}

.token-group-pill__meta {
    font-size: 0.74rem;
    color: var(--text-secondary-color);
    margin-top: 0.1rem;
}

.token-group-pill--active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: #ffffff;
    box-shadow: 0 6px 16px color-mix(in srgb, var(--primary-color) 28%, transparent);
}

.token-group-pill--active .token-group-pill__meta {
    color: rgba(255, 255, 255, 0.88);
}

.token-empty-notice {
    padding: 0.85rem 1rem;
    border-radius: var(--content-border-radius);
    background: var(--surface-ground);
    color: var(--text-secondary-color);
    font-size: 0.9rem;
}

/* Right Column: Summary Ticket Card */
.booking-summary-column {
    position: sticky;
    top: 1.25rem;
}

.summary-ticket-card {
    display: grid;
    gap: 1.15rem;
    padding: 1.35rem;
}

.ticket-hero {
    padding: 1.2rem;
    border-radius: var(--content-border-radius);
    background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
    color: #ffffff;
    box-shadow: var(--card-shadow-strong);
}

.ticket-hero__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.ticket-hero__eyebrow {
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.86);
}

.ticket-hero__group {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
}

.ticket-hero__token {
    font-size: 2rem;
    font-weight: 800;
    margin-top: 0.35rem;
    line-height: 1.15;
}

.ticket-hero__sub {
    font-size: 0.88rem;
    margin-top: 0.35rem;
    color: rgba(255, 255, 255, 0.9);
}

.ticket-details-list {
    display: grid;
    gap: 0.65rem;
}

.ticket-detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.7rem 0.85rem;
    border-radius: var(--content-border-radius);
    background: var(--surface-ground);
    font-size: 0.9rem;
}

.ticket-detail-row__label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary-color);
}

.ticket-detail-row__label i {
    color: var(--primary-color);
}

.ticket-detail-row__value {
    color: var(--text-color);
    text-align: right;
}

.ticket-actions {
    display: grid;
    gap: 0.65rem;
}

.confirm-booking-btn {
    min-height: 3rem;
    font-size: 1rem;
    font-weight: 700;
}

@media (max-width: 991px) {
    .booking-split-layout {
        grid-template-columns: 1fr;
    }

    .booking-summary-column {
        position: static;
    }
}

@media (max-width: 640px) {
    .form-two-col,
    .session-cards-grid {
        grid-template-columns: 1fr;
    }
}
</style>
