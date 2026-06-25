<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
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

const form = reactive({
    patient_id: props.defaultPatientId,
    appointment_date: props.dateOptions.find((option) => !option.disabled)?.value ?? props.dateOptions[0]?.value ?? '',
    appointment_session: '',
    appointment_number: null as number | null,
    appointment_type: props.appointmentTypes[0]?.value ?? 'new',
    reason_for_visit: '',
    errors: {} as Record<string, string>,
    processing: false,
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

    return [...groups.entries()].map(([start, availableTokens]) => {
        const minToken = Math.min(...availableTokens);
        const maxToken = Math.max(...availableTokens);
        const label = minToken === maxToken ? `${minToken}` : `${minToken}-${maxToken}`;
        return {
            label,
            token: minToken,
        };
    });
});

const bookingSteps = computed(() => [
    {
        label: 'Choose date',
        ready: Boolean(form.appointment_date),
    },
    {
        label: 'Pick session',
        ready: Boolean(form.appointment_session),
    },
    {
        label: 'Claim token',
        ready: Boolean(form.appointment_number),
    },
]);

const selectedTokenLabel = computed(() => {
    return form.appointment_number
        ? `Token ${form.appointment_number}`
        : 'Not selected';
});

const selectedTokenGroupLabel = computed(() => {
    if (!form.appointment_number) {
        return 'Not selected';
    }

    const start = Math.floor((form.appointment_number - 1) / 5) * 5 + 1;

    return `${start}-${start + 4}`;
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

const chooseSession = (session: SessionOption): void => {
    if (session.disabled) {
        return;
    }

    form.appointment_session = session.value;
};

const chooseTokenGroup = (tokenGroup: TokenGroupOption): void => {
    form.appointment_number = tokenGroup.token;
};

const submit = async (): Promise<void> => {
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
        <Message v-if="noticeEnabled" severity="info" class="w-full" :closable="false">
            {{ noticeText || 'Special Notice: The clinic is currently experiencing high call volume. You can still book your slot online, but please expect slight delays.' }}
        </Message>

        <Message v-if="!bookingEnabled" severity="warn" class="w-full" :closable="false">
            Online appointment booking is currently closed. Please contact the clinic directly to schedule an appointment.
        </Message>

        <section class="booking-hero">
            <div class="booking-hero__content">
                <p class="booking-kicker">Patient booking</p>
                <h2 class="booking-hero__title">
                    Reserve your next clinic visit with confidence
                </h2>
                <p class="booking-hero__subtitle">
                    Pick the day that works for you, compare live slot
                    availability, and choose a 5-token group for automatic
                    assignment.
                </p>

                <div class="booking-steps">
                    <div
                        v-for="(step, index) in bookingSteps"
                        :key="step.label"
                        class="booking-step"
                        :class="{ 'booking-step--active': step.ready }"
                    >
                        <span class="booking-step__index">{{ index + 1 }}</span>
                        <span>{{ step.label }}</span>
                    </div>
                </div>
            </div>

            <div class="booking-hero__aside">
                <div class="booking-highlight">
                    <span class="booking-highlight__label">Selected token</span>
                    <strong class="booking-highlight__value">{{
                        selectedTokenLabel
                    }}</strong>
                    <span class="booking-highlight__meta">
                        Group {{ selectedTokenGroupLabel }}
                    </span>
                </div>

                <div class="booking-highlight">
                    <span class="booking-highlight__label"
                        >Session availability</span
                    >
                    <strong class="booking-highlight__value">
                        {{
                            selectedSession
                                ? `${selectedSession.remaining} open`
                                : 'Choose a session'
                        }}
                    </strong>
                </div>

                <Link :href="dashboard().url">
                    <Button
                        label="Back to dashboard"
                        outlined
                        severity="contrast"
                        class="booking-hero__action"
                    />
                </Link>
            </div>
        </section>

        <div class="booking-layout patient-dashboard-grid">
            <Card>
                <template #title>
                    <div class="booking-card-header">
                        <div>
                            <span class="booking-card-header__eyebrow"
                                >Booking details</span
                            >
                            <div class="booking-card-header__title">
                                Build your visit
                            </div>
                        </div>

                        <Tag
                            :severity="selectedSession ? 'success' : 'contrast'"
                            :value="
                                selectedSession
                                    ? `${selectedSession.remaining} left`
                                    : 'Waiting for slot'
                            "
                        />
                    </div>
                </template>

                <template #content>
                    <form @submit.prevent="submit" class="booking-form">
                        <div class="booking-patient-select field">
                            <label for="patient_id">Book appointment for</label>
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

                        <div class="booking-form-grid">
                            <div class="field">
                                <label for="appointment_type"
                                    >Appointment type</label
                                >
                                <Select
                                    id="appointment_type"
                                    v-model="form.appointment_type"
                                    :options="appointmentTypes"
                                    option-label="label"
                                    option-value="value"
                                    :disabled="!bookingEnabled"
                                    placeholder="Select appointment type"
                                    fluid
                                />
                                <InputError
                                    :message="form.errors.appointment_type"
                                />
                            </div>

                            <div class="field">
                                <label for="appointment_date"
                                    >Booking date</label
                                >
                                <Select
                                    id="appointment_date"
                                    v-model="form.appointment_date"
                                    :options="dateOptions"
                                    option-label="label"
                                    option-value="value"
                                    option-disabled="disabled"
                                    :disabled="!bookingEnabled"
                                    placeholder="Select a date"
                                    fluid
                                />
                                <InputError
                                    :message="form.errors.appointment_date"
                                />
                            </div>
                        </div>

                        <div class="booking-section">
                            <div class="booking-section__header">
                                <div>
                                    <p class="booking-section__title">
                                        Choose a session
                                    </p>
                                    <p class="booking-section__hint">
                                        Morning and evening slots update from
                                        live clinic capacity.
                                    </p>
                                </div>
                            </div>

                            <div class="session-grid">
                                <button
                                    v-for="option in sessionOptions"
                                    :key="option.value"
                                    type="button"
                                    class="session-card"
                                    :class="{
                                        'session-card--selected':
                                            option.value ===
                                            form.appointment_session,
                                        'session-card--disabled':
                                            option.disabled,
                                    }"
                                    :disabled="option.disabled"
                                    @click="chooseSession(option)"
                                >
                                    <div class="session-card__top">
                                        <strong>{{ option.label }}</strong>
                                        <Tag
                                            :severity="
                                                option.disabled
                                                    ? 'danger'
                                                    : 'success'
                                            "
                                            :value="
                                                option.disabled
                                                    ? 'Unavailable'
                                                    : `${option.remaining} left`
                                            "
                                        />
                                    </div>

                                    <p class="session-card__copy">
                                        {{
                                            option.disabled
                                                ? 'This session cannot be booked right now.'
                                                : `Free tokens start at ${option.availableTokens[0] ?? '-'}`
                                        }}
                                    </p>
                                </button>
                            </div>

                            <InputError
                                :message="form.errors.appointment_session"
                            />
                        </div>

                        <div class="booking-section">
                            <div class="booking-section__header">
                                <div>
                                    <p class="booking-section__title">
                                        Pick your token group
                                    </p>
                                    <p class="booking-section__hint">
                                        Choose a group of 5. We will assign the
                                        next free token from that range.
                                    </p>
                                </div>

                                <div
                                    v-if="tokenGroupOptions.length > 0"
                                    class="booking-section__meta"
                                >
                                    {{ tokenGroupOptions.length }} groups
                                    available
                                </div>
                            </div>

                            <div
                                v-if="tokenGroupOptions.length > 0"
                                class="token-grid"
                            >
                                <button
                                    v-for="tokenGroup in tokenGroupOptions"
                                    :key="tokenGroup.label"
                                    type="button"
                                    class="token-pill"
                                    :class="{
                                        'token-pill--selected':
                                            tokenGroup.token ===
                                            form.appointment_number,
                                    }"
                                    @click="chooseTokenGroup(tokenGroup)"
                                >
                                    {{ tokenGroup.label }}
                                </button>
                            </div>

                            <div v-else class="token-empty">
                                Select an available session first to see free
                                token groups.
                            </div>

                            <InputError
                                :message="form.errors.appointment_number"
                            />
                        </div>

                        <div class="booking-section">
                            <div class="booking-section__header">
                                <div>
                                    <p class="booking-section__title">
                                        Reason for visit
                                    </p>
                                    <p class="booking-section__hint">
                                        A short note helps the clinic prepare
                                        before your visit.
                                    </p>
                                </div>
                            </div>

                            <div class="field">
                                <label for="reason_for_visit"
                                    >Share the main concern</label
                                >
                                <Textarea
                                    id="reason_for_visit"
                                    v-model="form.reason_for_visit"
                                    rows="4"
                                    placeholder="Tell clinic what you need help with"
                                    :disabled="!bookingEnabled"
                                    auto-resize
                                    fluid
                                />
                                <InputError
                                    :message="form.errors.reason_for_visit"
                                />
                            </div>
                        </div>

                        <div class="booking-actions">
                            <div class="booking-actions__copy">
                                Your booking will be confirmed with the token
                                shown in the summary.
                            </div>

                            <Button
                                type="submit"
                                label="Confirm booking"
                                icon="pi pi-check-circle"
                                :loading="form.processing"
                                :disabled="
                                    !bookingEnabled ||
                                    !form.appointment_date ||
                                    !form.appointment_session ||
                                    !form.appointment_number
                                "
                            />
                        </div>
                    </form>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="booking-card-header">
                        <div>
                            <span class="booking-card-header__eyebrow"
                                >Booking summary</span
                            >
                            <div class="booking-card-header__title">
                                Review before confirming
                            </div>
                        </div>
                    </div>
                </template>

                <template #content>
                    <div class="summary-panel">
                        <div class="summary-card summary-card--accent">
                            <p class="summary-card__label">Selected day</p>
                            <div class="summary-card__value">
                                {{ selectedDate?.label ?? 'Pick date' }}
                            </div>
                        </div>

                        <div class="summary-card">
                            <p class="summary-card__label">Selected slot</p>
                            <div class="summary-card__value">
                                {{ selectedSession?.label ?? 'Pick session' }}
                            </div>
                            <p class="summary-card__hint">
                                {{
                                    selectedSession
                                        ? `${selectedSession.remaining} slots currently available`
                                        : 'Availability updates from clinic settings'
                                }}
                            </p>
                        </div>

                        <div class="summary-card">
                            <p class="summary-card__label">Appointment type</p>
                            <div class="summary-card__value">
                                {{
                                    selectedAppointmentType?.label ??
                                    'Choose type'
                                }}
                            </div>
                        </div>

                        <div class="summary-card summary-card--token">
                            <p class="summary-card__label">Token assignment</p>
                            <div class="summary-card__value">
                                {{ selectedTokenLabel }}
                            </div>
                            <p class="summary-card__hint">
                                {{
                                    form.appointment_number
                                        ? `Assigned from group ${selectedTokenGroupLabel}. We reserve the next free token in that range.`
                                        : 'Pick a token group for your selected slot.'
                                }}
                            </p>
                        </div>

                        <div class="summary-note">
                            Booking windows and token availability follow the
                            clinic’s active schedule settings.
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </div>
</template>

<style scoped>
.booking-page {
    display: grid;
    gap: 1.5rem;
}

.booking-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(18rem, 0.75fr);
    gap: 1.25rem;
    padding: 1.6rem;
    border-radius: var(--content-border-radius);
    background: var(--surface-card);
    border: 1px solid rgba(15, 181, 186, 0.12);
    box-shadow: var(--card-shadow);
}

.booking-kicker {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.72rem;
    font-weight: 700;
    color: #0c6e72;
}

.booking-hero__title {
    margin: 0.45rem 0 0;
    font-size: clamp(1.7rem, 2.5vw, 2.35rem);
    line-height: 1.1;
    color: #10242e;
}

.booking-hero__subtitle {
    margin: 0.8rem 0 0;
    max-width: 42rem;
    color: #54707a;
    line-height: 1.65;
}

.booking-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.25rem;
}

.booking-step {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.7rem 0.95rem;
    border-radius: var(--content-border-radius);
    background: var(--surface-ground);
    color: #5d747b;
    border: 1px solid rgba(15, 181, 186, 0.1);
}

.booking-step--active {
    background: rgba(15, 181, 186, 0.14);
    color: #0c6e72;
}

.booking-step__index {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.55rem;
    height: 1.55rem;
    border-radius: var(--content-border-radius);
    background: #10242e;
    color: white;
    font-size: 0.78rem;
    font-weight: 700;
}

.booking-hero__aside {
    display: grid;
    gap: 0.9rem;
    align-content: start;
}

.booking-highlight {
    padding: 1rem 1.05rem;
    border-radius: var(--content-border-radius);
    background: var(--surface-ground);
    border: 1px solid rgba(15, 181, 186, 0.1);
}

.booking-highlight__label {
    display: block;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #60757d;
}

.booking-highlight__value {
    display: block;
    margin-top: 0.4rem;
    font-size: 1.15rem;
    color: #10242e;
}

.booking-highlight__meta {
    display: block;
    margin-top: 0.35rem;
    color: #60757d;
    font-size: 0.86rem;
}

.booking-hero__action {
    width: 100%;
}

.booking-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(18rem, 0.9fr);
    gap: 1.5rem;
}

.booking-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.booking-card-header__eyebrow {
    display: block;
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #6b838a;
}

.booking-card-header__title {
    margin-top: 0.35rem;
    font-size: 1.12rem;
    font-weight: 700;
    color: #10242e;
}

.booking-form {
    display: grid;
    gap: 1.25rem;
}

.booking-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.booking-section {
    padding: 1.1rem;
    border-radius: var(--content-border-radius);
    background: var(--surface-ground);
    border: 1px solid rgba(15, 181, 186, 0.1);
}

.booking-section__header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
    margin-bottom: 0.9rem;
    flex-wrap: wrap;
}

.booking-section__title {
    margin: 0;
    font-weight: 700;
    color: #10242e;
}

.booking-section__hint,
.booking-section__meta,
.booking-actions__copy,
.summary-card__hint,
.summary-note {
    margin: 0.35rem 0 0;
    color: #5e757d;
    line-height: 1.55;
}

.booking-section__meta {
    margin-top: 0;
    font-size: 0.86rem;
}

.session-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
    gap: 0.9rem;
}

.session-card {
    padding: 1rem;
    border-radius: var(--content-border-radius);
    border: 1px solid rgba(15, 181, 186, 0.12);
    background: var(--surface-card);
    text-align: left;
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        border-color 0.18s ease;
}

.session-card:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 16px 26px rgba(15, 37, 47, 0.08);
}

.session-card--selected {
    border-color: rgba(15, 181, 186, 0.5);
    box-shadow: inset 0 0 0 1px rgba(15, 181, 186, 0.2);
    background: color-mix(
        in srgb,
        var(--surface-card) 86%,
        var(--primary-color) 14%
    );
}

.session-card--disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.session-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.session-card__copy {
    margin: 0.6rem 0 0;
    color: #5e757d;
    line-height: 1.5;
}

.token-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));
    gap: 0.7rem;
}

.token-pill {
    padding: 0.85rem 0.95rem;
    border-radius: var(--content-border-radius);
    border: 1px solid rgba(15, 181, 186, 0.14);
    background: var(--surface-card);
    color: #20404b;
    font-weight: 700;
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        border-color 0.18s ease,
        background 0.18s ease;
}

.token-pill:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 22px rgba(15, 37, 47, 0.08);
}

.token-pill--selected {
    background: #10242e;
    color: white;
    border-color: #10242e;
    box-shadow: none;
}

.token-empty {
    padding: 0.95rem 1rem;
    border-radius: var(--content-border-radius);
    background: rgba(15, 181, 186, 0.06);
    color: #60767d;
}

.booking-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.summary-panel {
    display: grid;
    gap: 1rem;
}

.summary-card {
    padding: 1rem;
    border-radius: var(--content-border-radius);
    border: 1px solid rgba(15, 181, 186, 0.08);
    background: var(--surface-card);
}

.summary-card--accent {
    background: color-mix(
        in srgb,
        var(--surface-card) 88%,
        var(--primary-color) 12%
    );
}

.summary-card--token {
    background: color-mix(in srgb, var(--surface-card) 92%, #10242e 8%);
}

.summary-card__label {
    margin: 0;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #627980;
}

.summary-card__value {
    margin-top: 0.4rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: #10242e;
}

.summary-note {
    padding: 0.95rem 1rem;
    border-radius: var(--content-border-radius);
    background: rgba(16, 36, 46, 0.04);
    margin-top: 0;
}

.booking-patient-select {
    margin-bottom: 1.5rem;
}

@media (max-width: 991px) {
    .booking-hero,
    .booking-layout,
    .booking-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
