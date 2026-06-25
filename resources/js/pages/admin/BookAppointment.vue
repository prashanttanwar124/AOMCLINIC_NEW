<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import http from '@/lib/http';
import { dashboard, booking } from '@/routes';
import PatientController from '@/actions/App/Http/Controllers/Admin/PatientController';
import AppointmentBookingController from '@/actions/App/Http/Controllers/Admin/AppointmentBookingController';

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
    dateOptions: DateOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Booking Desk', href: booking() },
            { title: 'Book Appointment', href: '#' },
        ],
    },
});

const selectedPatient = ref<any>(null);
const patientSuggestions = ref<any[]>([]);

const form = useForm({
    patient_id: null as number | null,
    appointment_date: props.dateOptions.find((option) => !option.disabled)?.value ?? props.dateOptions[0]?.value ?? '',
    appointment_session: '',
    appointment_number: null as number | null,
    appointment_type: props.appointmentTypes[0]?.value ?? 'new',
    reason_for_visit: '',
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

const availableTokens = computed<number[]>(() => {
    return selectedSession.value?.availableTokens ?? [];
});

const selectedTokenLabel = computed(() => {
    return form.appointment_number
        ? `Token ${form.appointment_number}`
        : 'Not selected';
});

watch(
    availableTokens,
    (tokens) => {
        const activeToken = tokens.find(
            (token) => token === form.appointment_number,
        );

        if (activeToken) {
            return;
        }

        form.appointment_number = tokens[0] ?? null;
    },
    { immediate: true },
);

watch(selectedPatient, (val) => {
    if (val && typeof val === 'object' && 'id' in val) {
        form.patient_id = val.id;
    } else {
        form.patient_id = null;
    }
});

const searchPatients = async (event: { query: string }) => {
    try {
        const { data } = await http.get(PatientController.search().url, {
            params: { query: event.query },
        });
        patientSuggestions.value = data;
    } catch (e) {
        console.error(e);
    }
};

const chooseSession = (session: SessionOption): void => {
    if (session.disabled) {
        return;
    }

    form.appointment_session = session.value;
};

const chooseToken = (token: number): void => {
    form.appointment_number = token;
};

const submit = (): void => {
    form.post(AppointmentBookingController.store().url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Book Appointment" />

    <div class="booking-page">
        <section class="booking-hero">
            <div class="booking-hero__content">
                <p class="booking-kicker">Staff Portal booking</p>
                <h2 class="booking-hero__title">
                    Schedule and assign patient queue spots directly
                </h2>
                <p class="booking-hero__subtitle">
                    Select a patient, pick the booking day, select the morning or evening session, and claim a token.
                </p>
            </div>

            <div class="booking-hero__aside">
                <div class="booking-highlight">
                    <span class="booking-highlight__label">Assigned spot</span>
                    <strong class="booking-highlight__value">{{ selectedTokenLabel }}</strong>
                </div>

                <div class="booking-highlight">
                    <span class="booking-highlight__label">Available spots</span>
                    <strong class="booking-highlight__value">
                        {{ selectedSession ? `${selectedSession.remaining} open` : 'Select session' }}
                    </strong>
                </div>
            </div>
        </section>

        <div class="booking-layout">
            <Card>
                <template #title>
                    <div class="booking-card-header">
                        <div>
                            <span class="booking-card-header__eyebrow">Booking details</span>
                            <div class="booking-card-header__title">Schedule patient visit</div>
                        </div>
                    </div>
                </template>

                <template #content>
                    <form @submit.prevent="submit" class="booking-form">
                        <!-- Patient Search field -->
                        <div class="booking-section">
                            <div class="booking-section__header">
                                <div>
                                    <p class="booking-section__title">Select Patient</p>
                                    <p class="booking-section__hint">
                                        Type to search existing patient records by name, email, phone, or ID.
                                    </p>
                                </div>
                            </div>

                            <div class="field">
                                <AutoComplete
                                    v-model="selectedPatient"
                                    :suggestions="patientSuggestions"
                                    option-label="label"
                                    placeholder="Start typing patient details..."
                                    fluid
                                    @complete="searchPatients"
                                />
                                <InputError :message="form.errors.patient_id" />
                            </div>

                            <!-- Patient Info Panel -->
                            <div v-if="selectedPatient && typeof selectedPatient === 'object' && selectedPatient.id" class="patient-details-card">
                                <div class="patient-details-header">
                                    <i class="pi pi-user-check"></i>
                                    <span>Selected Patient Details</span>
                                </div>
                                <div class="patient-details-body">
                                    <div><strong>Name:</strong> {{ selectedPatient.name }}</div>
                                    <div><strong>Patient ID:</strong> #{{ selectedPatient.id }}</div>
                                    <div><strong>Phone:</strong> {{ selectedPatient.phone || 'N/A' }}</div>
                                    <div><strong>Email:</strong> {{ selectedPatient.email || 'N/A' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="booking-form-grid">
                            <div class="field">
                                <label for="appointment_type">Appointment type</label>
                                <Select
                                    id="appointment_type"
                                    v-model="form.appointment_type"
                                    :options="appointmentTypes"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Select appointment type"
                                    fluid
                                />
                                <InputError :message="form.errors.appointment_type" />
                            </div>

                            <div class="field">
                                <label for="appointment_date">Booking date</label>
                                <Select
                                    id="appointment_date"
                                    v-model="form.appointment_date"
                                    :options="dateOptions"
                                    option-label="label"
                                    option-value="value"
                                    option-disabled="disabled"
                                    placeholder="Select a date"
                                    fluid
                                />
                                <InputError :message="form.errors.appointment_date" />
                            </div>
                        </div>

                        <div class="booking-section">
                            <div class="booking-section__header">
                                <div>
                                    <p class="booking-section__title">Choose a session</p>
                                    <p class="booking-section__hint">
                                        Enforce capacities according to the active schedules.
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
                                        'session-card--selected': option.value === form.appointment_session,
                                        'session-card--disabled': option.disabled,
                                    }"
                                    :disabled="option.disabled"
                                    @click="chooseSession(option)"
                                >
                                    <div class="session-card__top">
                                        <strong>{{ option.label }}</strong>
                                        <Tag
                                            :severity="option.disabled ? 'danger' : 'success'"
                                            :value="option.disabled ? 'Unavailable' : `${option.remaining} left`"
                                        />
                                    </div>

                                    <p class="session-card__copy">
                                        {{
                                            option.disabled
                                                ? 'This session is closed or fully booked.'
                                                : `Free tokens start at ${option.availableTokens[0] ?? '-'}`
                                        }}
                                    </p>
                                </button>
                            </div>

                            <InputError :message="form.errors.appointment_session" />
                        </div>

                        <div class="booking-section">
                            <div class="booking-section__header">
                                <div>
                                    <p class="booking-section__title">Pick your token</p>
                                    <p class="booking-section__hint">
                                        Select the exact token number for this appointment.
                                    </p>
                                </div>

                                <div v-if="availableTokens.length > 0" class="booking-section__meta">
                                    {{ availableTokens.length }} tokens available
                                </div>
                            </div>

                            <div v-if="availableTokens.length > 0" class="token-grid">
                                <button
                                    v-for="token in availableTokens"
                                    :key="token"
                                    type="button"
                                    class="token-pill"
                                    :class="{
                                        'token-pill--selected': token === form.appointment_number,
                                    }"
                                    @click="chooseToken(token)"
                                >
                                    Token {{ token }}
                                </button>
                            </div>

                            <div v-else class="token-empty">
                                Select an available session first to see free tokens.
                            </div>

                            <InputError :message="form.errors.appointment_number" />
                        </div>

                        <div class="booking-section">
                            <div class="booking-section__header">
                                <div>
                                    <p class="booking-section__title">Reason for visit</p>
                                    <p class="booking-section__hint">
                                        Add a brief clinical note explaining the main complaints.
                                    </p>
                                </div>
                            </div>

                            <div class="field">
                                <label for="reason_for_visit">Clinical notes / main concern</label>
                                <Textarea
                                    id="reason_for_visit"
                                    v-model="form.reason_for_visit"
                                    rows="4"
                                    placeholder="Enter details of symptoms or consultation reasons"
                                    auto-resize
                                    fluid
                                />
                                <InputError :message="form.errors.reason_for_visit" />
                            </div>
                        </div>

                        <div class="booking-actions">
                            <div class="booking-actions__copy">
                                Confirming will directly insert this appointment into the active queue.
                            </div>

                            <Button
                                type="submit"
                                label="Schedule appointment"
                                icon="pi pi-check-circle"
                                :loading="form.processing"
                                :disabled="!form.patient_id || !form.appointment_date || !form.appointment_session || !form.appointment_number"
                            />
                        </div>
                    </form>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="booking-card-header">
                        <div>
                            <span class="booking-card-header__eyebrow">Booking summary</span>
                            <div class="booking-card-header__title">Administrative overview</div>
                        </div>
                    </div>
                </template>

                <template #content>
                    <div class="summary-panel">
                        <div class="summary-card summary-card--accent">
                            <p class="summary-card__label">Selected patient</p>
                            <div class="summary-card__value">
                                {{ selectedPatient?.name || 'No patient selected' }}
                            </div>
                            <p v-if="selectedPatient?.id" class="summary-card__hint">
                                Patient reference ID: #{{ selectedPatient.id }}
                            </p>
                        </div>

                        <div class="summary-card">
                            <p class="summary-card__label">Scheduled date</p>
                            <div class="summary-card__value">
                                {{ selectedDate?.label || 'Pick date' }}
                            </div>
                        </div>

                        <div class="summary-card">
                            <p class="summary-card__label">Queue slot</p>
                            <div class="summary-card__value">
                                {{ selectedSession?.label || 'Pick session' }}
                            </div>
                            <p class="summary-card__hint">
                                {{ selectedSession ? `${selectedSession.remaining} slots currently available` : 'Availability updates from clinic settings' }}
                            </p>
                        </div>

                        <div class="summary-card">
                            <p class="summary-card__label">Visit classification</p>
                            <div class="summary-card__value">
                                {{ selectedAppointmentType?.label || 'Choose type' }}
                            </div>
                        </div>

                        <div class="summary-card summary-card--token">
                            <p class="summary-card__label">Token number</p>
                            <div class="summary-card__value">
                                {{ selectedTokenLabel }}
                            </div>
                            <p class="summary-card__hint">
                                {{ form.appointment_number ? `Directly assigned spot in queue.` : 'Choose a session to allocate tokens.' }}
                            </p>
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
    font-size: 0.88rem;
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
    cursor: pointer;
}

.session-card:hover:not(:disabled) {
    border-color: rgba(15, 181, 186, 0.4);
    box-shadow: 0 4px 12px rgba(15, 181, 186, 0.06);
    transform: translateY(-1px);
}

.session-card--selected {
    border-color: #0fb5ba !important;
    background: rgba(15, 181, 186, 0.05) !important;
}

.session-card--disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.session-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.session-card__top strong {
    color: #10242e;
    font-size: 1.05rem;
}

.session-card__copy {
    margin: 0.6rem 0 0;
    font-size: 0.86rem;
    color: #54707a;
    line-height: 1.4;
}

.token-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(6rem, 1fr));
    gap: 0.65rem;
}

.token-pill {
    padding: 0.65rem 0.85rem;
    border-radius: var(--content-border-radius);
    background: var(--surface-card);
    border: 1px solid rgba(15, 181, 186, 0.12);
    color: #10242e;
    font-weight: 700;
    font-size: 0.9rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.15s ease;
}

.token-pill:hover:not(:disabled) {
    border-color: rgba(15, 181, 186, 0.4);
    background: rgba(15, 181, 186, 0.02);
}

.token-pill--selected {
    border-color: #0fb5ba !important;
    background: #0fb5ba !important;
    color: white !important;
}

.token-empty {
    padding: 1.5rem;
    text-align: center;
    color: #718d96;
    background: rgba(15, 181, 186, 0.02);
    border: 1px dashed rgba(15, 181, 186, 0.15);
    border-radius: var(--content-border-radius);
    font-size: 0.9rem;
}

.booking-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding-top: 0.75rem;
    flex-wrap: wrap;
}

.booking-actions__copy {
    flex: 1;
    min-width: 16rem;
    font-size: 0.86rem;
}

.summary-panel {
    display: grid;
    gap: 0.95rem;
}

.summary-card {
    padding: 0.9rem 1rem;
    border-radius: var(--content-border-radius);
    background: var(--surface-ground);
    border: 1px solid rgba(15, 181, 186, 0.08);
}

.summary-card--accent {
    background: rgba(15, 181, 186, 0.04);
    border-color: rgba(15, 181, 186, 0.15);
}

.summary-card--token {
    background: rgba(12, 110, 114, 0.05);
    border-color: rgba(12, 110, 114, 0.15);
}

.summary-card__label {
    margin: 0;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #60757d;
}

.summary-card__value {
    margin-top: 0.35rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: #10242e;
}

.patient-details-card {
    padding: 1rem;
    border-radius: var(--content-border-radius);
    background: rgba(12, 110, 114, 0.05);
    border: 1px solid rgba(12, 110, 114, 0.15);
    margin-top: 1rem;
}

.patient-details-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: #0c6e72;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.patient-details-body {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
    gap: 0.5rem;
    font-size: 0.88rem;
    color: #10242e;
}
</style>
