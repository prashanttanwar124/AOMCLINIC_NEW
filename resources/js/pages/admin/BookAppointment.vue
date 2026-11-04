<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { computed, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import http, { extractErrors } from '@/lib/http';
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

const toast = useToast();
const selectedPatient = ref<any>(null);
const patientSuggestions = ref<any[]>([]);
const isDoubleBooking = ref(false);

useEchoPublic('live-queue', 'QueueUpdated', () => {
    router.reload({
        only: ['dateOptions'],
    });
});

const form = useForm({
    patient_id: null as number | null,
    appointment_date: props.dateOptions.find((option) => !option.disabled)?.value ?? props.dateOptions[0]?.value ?? '',
    appointment_session: '',
    appointment_number: null as number | null,
    second_appointment_number: null as number | null,
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
    if (!form.appointment_number) {
        return 'Not selected';
    }

    if (isDoubleBooking.value && form.second_appointment_number) {
        return `Tokens ${form.appointment_number} & ${form.second_appointment_number}`;
    }

    return `Token ${form.appointment_number}`;
});

watch(
    [availableTokens, isDoubleBooking],
    ([tokens, doubleMode]) => {
        if (!tokens.includes(form.appointment_number ?? -1)) {
            form.appointment_number = tokens[0] ?? null;
        }

        if (!doubleMode) {
            form.second_appointment_number = null;

            return;
        }

        if (
            !form.second_appointment_number ||
            form.second_appointment_number === form.appointment_number ||
            !tokens.includes(form.second_appointment_number)
        ) {
            form.second_appointment_number =
                tokens.find((t) => t !== form.appointment_number) ?? null;
        }
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

const selectDependentForBooking = (child: any) => {
    selectedPatient.value = {
        ...child,
        label: `${child.name} (#${child.id}) [Dependent of ${selectedPatient.value?.name ?? 'Parent'}]`,
        children: [],
    };
};

// Quick Create Patient Modal
const createPatientDialogVisible = ref(false);
const createPatientSubmitting = ref(false);
const createPatientErrors = ref<Record<string, string>>({});
const newPatientForm = reactive({
    name: '',
    phone: '',
    gender: '' as string | null,
    date_of_birth: '',
    city: '',
    address: '',
});

const genderOptions = [
    { label: 'Male', value: 'male' },
    { label: 'Female', value: 'female' },
    { label: 'Other', value: 'other' },
];

const openCreatePatientModal = (): void => {
    newPatientForm.name = '';
    newPatientForm.phone = '';
    newPatientForm.gender = null;
    newPatientForm.date_of_birth = '';
    newPatientForm.city = '';
    newPatientForm.address = '';
    createPatientErrors.value = {};
    createPatientDialogVisible.value = true;
};

const submitNewPatient = async (): Promise<void> => {
    createPatientSubmitting.value = true;
    createPatientErrors.value = {};

    try {
        const { data } = await http.post(PatientController.store().url, {
            ...newPatientForm,
        });
        if (data?.patient) {
            selectedPatient.value = data.patient;
            form.patient_id = data.patient.id;
        }
        createPatientDialogVisible.value = false;
        toast.add({
            severity: 'success',
            summary: 'Patient Added',
            detail: data?.message ?? 'New patient created and selected for booking.',
            life: 3500,
        });
    } catch (err) {
        createPatientErrors.value = extractErrors(err);
    } finally {
        createPatientSubmitting.value = false;
    }
};

const chooseSession = (session: SessionOption): void => {
    if (session.disabled) {
        return;
    }

    form.appointment_session = session.value;
};

const chooseToken = (token: number): void => {
    if (!isDoubleBooking.value) {
        form.appointment_number = token;

        return;
    }

    if (form.appointment_number === token) {
        if (form.second_appointment_number) {
            form.appointment_number = form.second_appointment_number;
            form.second_appointment_number = null;
        }

        return;
    }

    if (form.second_appointment_number === token) {
        form.second_appointment_number = null;

        return;
    }

    if (!form.appointment_number) {
        form.appointment_number = token;
    } else {
        form.second_appointment_number = token;
    }
};

const submit = (): void => {
    if (!isDoubleBooking.value) {
        form.second_appointment_number = null;
    }

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
                                        Type to search existing patients or dependents by name, phone, email, or ID.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    label="New Patient"
                                    icon="pi pi-user-plus"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    @click="openCreatePatientModal"
                                />
                            </div>

                            <div class="field">
                                <AutoComplete
                                    v-model="selectedPatient"
                                    :suggestions="patientSuggestions"
                                    option-label="label"
                                    placeholder="Start typing patient or dependent details..."
                                    fluid
                                    @complete="searchPatients"
                                />
                                <InputError :message="form.errors.patient_id" />
                            </div>

                            <!-- Patient Info Panel -->
                            <div v-if="selectedPatient && typeof selectedPatient === 'object' && selectedPatient.id" class="patient-details-card">
                                <div class="patient-details-header">
                                    <i class="pi pi-verified"></i>
                                    <span>Selected Patient Details</span>
                                    <Tag
                                        v-if="selectedPatient.parent_name"
                                        :value="`Dependent of ${selectedPatient.parent_name}`"
                                        severity="info"
                                    />
                                </div>
                                <div class="patient-details-body">
                                    <div><strong>Name:</strong> {{ selectedPatient.name }}</div>
                                    <div><strong>Patient ID:</strong> #{{ selectedPatient.id }}</div>
                                    <div><strong>Phone:</strong> {{ selectedPatient.phone || 'N/A' }}</div>
                                    <div><strong>Email:</strong> {{ selectedPatient.email || 'N/A' }}</div>
                                </div>

                                <div v-if="selectedPatient.children?.length" class="patient-dependents-switcher">
                                    <span class="patient-dependents-switcher__label">
                                        Or book for a dependent of {{ selectedPatient.name }}:
                                    </span>
                                    <div class="patient-dependents-switcher__chips">
                                        <button
                                            v-for="child in selectedPatient.children"
                                            :key="child.id"
                                            type="button"
                                            class="dependent-switch-chip"
                                            @click="selectDependentForBooking(child)"
                                        >
                                            <i class="pi pi-user"></i>
                                            {{ child.name }} (#{{ child.id }})
                                        </button>
                                    </div>
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
                                        {{
                                            isDoubleBooking
                                                ? 'Double Appointment enabled — click two open tokens to book 2 slots at once.'
                                                : 'Select the exact token number for this appointment.'
                                        }}
                                    </p>
                                </div>

                                <div class="token-header-controls">
                                    <button
                                        type="button"
                                        class="double-booking-toggle"
                                        :class="{ 'is-active': isDoubleBooking }"
                                        :disabled="availableTokens.length < 2"
                                        @click="isDoubleBooking = !isDoubleBooking"
                                    >
                                        <i class="pi pi-clone"></i>
                                        <span>Double Appointment (2 Tokens)</span>
                                    </button>
                                    <div v-if="availableTokens.length > 0" class="booking-section__meta">
                                        {{ availableTokens.length }} tokens available
                                    </div>
                                </div>
                            </div>

                            <div v-if="availableTokens.length > 0" class="token-grid">
                                <button
                                    v-for="token in availableTokens"
                                    :key="token"
                                    type="button"
                                    class="token-pill"
                                    :class="{
                                        'token-pill--selected':
                                            token === form.appointment_number ||
                                            (isDoubleBooking && token === form.second_appointment_number),
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
                            <InputError :message="form.errors.second_appointment_number" />
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

        <!-- Quick Create Patient Dialog -->
        <Dialog
            v-model:visible="createPatientDialogVisible"
            header="Add New Patient"
            modal
            :style="{ width: '32rem', maxWidth: '95vw' }"
        >
            <form class="quick-patient-form" @submit.prevent="submitNewPatient">
                <div class="booking-form-grid">
                    <div class="field">
                        <label for="qp_name">Full Name *</label>
                        <InputText
                            id="qp_name"
                            v-model="newPatientForm.name"
                            required
                            fluid
                            placeholder="e.g., Aarav Sharma"
                        />
                        <InputError :message="createPatientErrors.name" />
                    </div>
                    <div class="field">
                        <label for="qp_phone">Phone Number *</label>
                        <InputText
                            id="qp_phone"
                            v-model="newPatientForm.phone"
                            required
                            fluid
                            placeholder="9876543210"
                        />
                        <InputError :message="createPatientErrors.phone" />
                    </div>
                </div>
                <div class="booking-form-grid">
                    <div class="field">
                        <label for="qp_gender">Gender</label>
                        <Select
                            id="qp_gender"
                            v-model="newPatientForm.gender"
                            :options="genderOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Select gender"
                            show-clear
                            fluid
                        />
                    </div>
                    <div class="field">
                        <label for="qp_dob">Date of Birth</label>
                        <InputText
                            id="qp_dob"
                            v-model="newPatientForm.date_of_birth"
                            type="date"
                            fluid
                        />
                    </div>
                </div>
                <div class="booking-form-grid">
                    <div class="field">
                        <label for="qp_city">City</label>
                        <InputText
                            id="qp_city"
                            v-model="newPatientForm.city"
                            fluid
                            placeholder="e.g., Mumbai"
                        />
                    </div>
                    <div class="field">
                        <label for="qp_address">Address</label>
                        <InputText
                            id="qp_address"
                            v-model="newPatientForm.address"
                            fluid
                            placeholder="Street or landmark"
                        />
                    </div>
                </div>
                <div class="quick-patient-actions">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        outlined
                        @click="createPatientDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Create & Select Patient"
                        icon="pi pi-check"
                        :loading="createPatientSubmitting"
                    />
                </div>
            </form>
        </Dialog>
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

.patient-dependents-switcher {
    margin-top: 0.85rem;
    padding-top: 0.75rem;
    border-top: 1px dashed rgba(12, 110, 114, 0.2);
    display: grid;
    gap: 0.45rem;
}

.patient-dependents-switcher__label {
    font-size: 0.78rem;
    font-weight: 700;
    color: #0c6e72;
}

.patient-dependents-switcher__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.dependent-switch-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    background: #ffffff;
    color: #0c6e72;
    border: 1px solid rgba(12, 110, 114, 0.3);
    cursor: pointer;
    transition: all 0.15s ease;
}

.dependent-switch-chip:hover {
    background: #0c6e72;
    color: #ffffff;
}

.token-header-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.double-booking-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    border: 1px solid rgba(15, 181, 186, 0.35);
    background: #ffffff;
    color: #0c6e72;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.double-booking-toggle.is-active {
    background: #0c6e72;
    border-color: #0c6e72;
    color: #ffffff;
}

.double-booking-toggle:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.quick-patient-form {
    display: grid;
    gap: 1rem;
}

.quick-patient-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--surface-border);
}
</style>
