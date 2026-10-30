<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { computed, reactive, watch } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Patient/PatientAppointmentController';
import InputError from '@/components/InputError.vue';
import http, { extractErrors, pushToast } from '@/lib/http';
import { dashboard, dependents } from '@/routes/patient';

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
    rangeLabel: string;
    token: number;
    availableCount: number;
};

const QUICK_REASONS = [
    'Follow-up & medicine refill',
    'Fever, cold or cough',
    'Skin / allergy concern',
    'Digestive / stomach issue',
    'Joint or body pain',
    'Routine health consultation',
];

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

function parsePatientOption(patient: { id: number; name: string }): {
    id: number;
    cleanName: string;
    badge: string;
    initials: string;
} {
    let cleanName = patient.name;
    let badge = 'Family Member';

    if (patient.name.endsWith(' (Self)')) {
        cleanName = patient.name.replace(/ \(Self\)$/, '');
        badge = 'Self';
    } else if (patient.name.endsWith(' (Account Holder)')) {
        cleanName = patient.name.replace(/ \(Account Holder\)$/, '');
        badge = 'Account Holder';
    }

    const parts = cleanName.trim().split(/\s+/);
    const initials =
        parts.length >= 2
            ? `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase()
            : cleanName.slice(0, 2).toUpperCase();

    return {
        id: patient.id,
        cleanName,
        badge,
        initials,
    };
}

const patientCards = computed(() =>
    props.bookablePatients.map((p) => parsePatientOption(p)),
);

const selectedPatientCard = computed(
    () =>
        patientCards.value.find((p) => p.id === form.patient_id) ??
        patientCards.value[0] ??
        null,
);

function parseDateCard(option: DateOption, index: number): {
    value: string;
    label: string;
    dayBadge: string;
    dayNum: string;
    monthYear: string;
    totalRemaining: number;
    disabled: boolean;
} {
    // option.label format from backend: "Fri, 30 Oct 2026"
    const [weekdayPart, restPart] = option.label.split(',').map((s) => s.trim());
    const dateParts = (restPart || option.label).split(' ');
    const dayNum = dateParts[0] || '';
    const monthYear = dateParts.slice(1, 3).join(' ');

    let dayBadge = weekdayPart || '';
    if (index === 0) {
        dayBadge = 'Today';
    } else if (index === 1) {
        dayBadge = 'Tomorrow';
    }

    const totalRemaining = option.sessions.reduce(
        (sum, s) => sum + (s.disabled ? 0 : s.remaining),
        0,
    );

    return {
        value: option.value,
        label: option.label,
        dayBadge,
        dayNum,
        monthYear,
        totalRemaining,
        disabled: option.disabled,
    };
}

const dateCards = computed(() =>
    props.dateOptions.map((option, index) => parseDateCard(option, index)),
);

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
        const label =
            minToken === maxToken ? `${minToken}` : `${minToken}-${maxToken}`;
        return {
            label,
            rangeLabel: `${start}–${start + 4}`,
            token: minToken,
            availableCount: availableTokens.length,
        };
    });
});

const bookingSteps = computed(() => [
    {
        label: 'Patient & Type',
        ready: Boolean(form.patient_id && form.appointment_type),
    },
    {
        label: 'Date & Session',
        ready: Boolean(form.appointment_date && form.appointment_session),
    },
    {
        label: 'Token & Confirm',
        ready: Boolean(form.appointment_number),
    },
]);

const selectedTokenLabel = computed(() => {
    return form.appointment_number
        ? `Token #${form.appointment_number}`
        : 'Not selected';
});

const selectedTokenGroupLabel = computed(() => {
    if (!form.appointment_number) {
        return 'Not selected';
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

const choosePatient = (patientId: number): void => {
    if (!props.bookingEnabled) {
        return;
    }
    form.patient_id = patientId;
    delete form.errors.patient_id;
};

const chooseAppointmentType = (typeValue: string): void => {
    if (!props.bookingEnabled) {
        return;
    }
    form.appointment_type = typeValue;
    delete form.errors.appointment_type;
};

const chooseDate = (dateOption: { value: string; disabled: boolean }): void => {
    if (!props.bookingEnabled || dateOption.disabled) {
        return;
    }
    form.appointment_date = dateOption.value;
    delete form.errors.appointment_date;
};

const chooseSession = (session: SessionOption): void => {
    if (session.disabled) {
        return;
    }

    form.appointment_session = session.value;
    delete form.errors.appointment_session;
};

const chooseTokenGroup = (tokenGroup: TokenGroupOption): void => {
    form.appointment_number = tokenGroup.token;
    delete form.errors.appointment_number;
};

const isReasonChipActive = (reason: string): boolean => {
    return form.reason_for_visit
        .toLowerCase()
        .includes(reason.toLowerCase());
};

const toggleQuickReason = (reason: string): void => {
    if (!props.bookingEnabled) {
        return;
    }

    const current = form.reason_for_visit.trim();
    if (!current) {
        form.reason_for_visit = reason;
        delete form.errors.reason_for_visit;
        return;
    }

    const parts = current
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
    const existingIndex = parts.findIndex(
        (p) => p.toLowerCase() === reason.toLowerCase(),
    );

    if (existingIndex !== -1) {
        parts.splice(existingIndex, 1);
        form.reason_for_visit = parts.join(', ');
    } else {
        form.reason_for_visit = `${current}, ${reason}`;
    }

    delete form.errors.reason_for_visit;
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

        <!-- Compact Header Bar -->
        <section class="booking-compact-header">
            <div class="booking-compact-header__main">
                <div>
                    <p class="booking-kicker">Quick Online Booking</p>
                    <h2 class="booking-compact-header__title">
                        Schedule your clinic visit in seconds
                    </h2>
                </div>

                <div class="booking-steps">
                    <div
                        v-for="(step, index) in bookingSteps"
                        :key="step.label"
                        class="booking-step"
                        :class="{ 'booking-step--active': step.ready }"
                    >
                        <span class="booking-step__index">
                            <i
                                v-if="step.ready"
                                class="pi pi-check"
                                style="font-size: 0.7rem"
                            />
                            <template v-else>{{ index + 1 }}</template>
                        </span>
                        <span>{{ step.label }}</span>
                    </div>
                </div>
            </div>

            <div class="booking-compact-header__actions">
                <div class="booking-live-pill">
                    <span class="booking-live-pill__dot" />
                    <span>
                        {{
                            selectedSession
                                ? `${selectedSession.remaining} slots open · ${selectedTokenLabel}`
                                : 'Select an open session'
                        }}
                    </span>
                </div>

                <Link :href="dashboard().url">
                    <Button
                        label="Back to dashboard"
                        icon="pi pi-arrow-left"
                        outlined
                        severity="secondary"
                        size="small"
                    />
                </Link>
            </div>
        </section>

        <div class="booking-layout">
            <Card class="booking-main-card">
                <template #content>
                    <form @submit.prevent="submit" class="booking-form">
                        <!-- Step 1: Patient & Visit Type Cards -->
                        <div class="booking-section">
                            <div class="booking-section__header">
                                <div class="booking-section__title-row">
                                    <span class="step-badge">1</span>
                                    <div>
                                        <p class="booking-section__title">
                                            Who is visiting & consultation type
                                        </p>
                                        <p class="booking-section__hint">
                                            Tap a family member and choose
                                            whether this is a new or follow-up
                                            visit.
                                        </p>
                                    </div>
                                </div>

                                <Link
                                    :href="dependents().url"
                                    class="family-manage-link"
                                >
                                    <i class="pi pi-users" />
                                    <span>Manage family</span>
                                </Link>
                            </div>

                            <div class="field">
                                <label class="section-sublabel">
                                    Patient
                                </label>
                                <div class="patient-cards-grid">
                                    <button
                                        v-for="patient in patientCards"
                                        :key="patient.id"
                                        type="button"
                                        class="patient-choice-card"
                                        :class="{
                                            'patient-choice-card--selected':
                                                patient.id === form.patient_id,
                                        }"
                                        :disabled="!bookingEnabled"
                                        @click="choosePatient(patient.id)"
                                    >
                                        <span class="patient-choice-avatar">
                                            {{ patient.initials }}
                                        </span>
                                        <span class="patient-choice-body">
                                            <strong
                                                class="patient-choice-name"
                                                >{{ patient.cleanName }}</strong
                                            >
                                            <span
                                                class="patient-choice-badge"
                                                >{{ patient.badge }}</span
                                            >
                                        </span>
                                        <i
                                            v-if="
                                                patient.id === form.patient_id
                                            "
                                            class="pi pi-check-circle patient-choice-check"
                                        />
                                    </button>
                                </div>
                                <InputError :message="form.errors.patient_id" />
                            </div>

                            <div class="field" style="margin-top: 1rem">
                                <label class="section-sublabel">
                                    Consultation Type
                                </label>
                                <div class="type-cards-grid">
                                    <button
                                        v-for="typeOption in appointmentTypes"
                                        :key="typeOption.value"
                                        type="button"
                                        class="type-choice-card"
                                        :class="{
                                            'type-choice-card--selected':
                                                typeOption.value ===
                                                form.appointment_type,
                                        }"
                                        :disabled="!bookingEnabled"
                                        @click="
                                            chooseAppointmentType(
                                                typeOption.value,
                                            )
                                        "
                                    >
                                        <div class="type-choice-icon">
                                            <i
                                                :class="
                                                    typeOption.value === 'new'
                                                        ? 'pi pi-user-plus'
                                                        : 'pi pi-refresh'
                                                "
                                            />
                                        </div>
                                        <div class="type-choice-content">
                                            <strong>{{
                                                typeOption.label
                                            }}</strong>
                                            <span>
                                                {{
                                                    typeOption.value === 'new'
                                                        ? 'First visit or a new health concern'
                                                        : 'Reviewing ongoing treatment or refill'
                                                }}
                                            </span>
                                        </div>
                                        <i
                                            v-if="
                                                typeOption.value ===
                                                form.appointment_type
                                            "
                                            class="pi pi-check-circle type-choice-check"
                                        />
                                    </button>
                                </div>
                                <InputError
                                    :message="form.errors.appointment_type"
                                />
                            </div>
                        </div>

                        <!-- Step 2: Visual Date Cards & Session Selection -->
                        <div class="booking-section">
                            <div class="booking-section__header">
                                <div class="booking-section__title-row">
                                    <span class="step-badge">2</span>
                                    <div>
                                        <p class="booking-section__title">
                                            Pick booking date & session
                                        </p>
                                        <p class="booking-section__hint">
                                            Select an open day below, then tap
                                            Morning or Evening.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="field">
                                <label class="section-sublabel">
                                    Available Days
                                </label>
                                <div class="date-cards-strip">
                                    <button
                                        v-for="dateCard in dateCards"
                                        :key="dateCard.value"
                                        type="button"
                                        class="date-choice-card"
                                        :class="{
                                            'date-choice-card--selected':
                                                dateCard.value ===
                                                form.appointment_date,
                                            'date-choice-card--disabled':
                                                dateCard.disabled,
                                        }"
                                        :disabled="
                                            !bookingEnabled || dateCard.disabled
                                        "
                                        @click="chooseDate(dateCard)"
                                    >
                                        <span class="date-choice-badge">{{
                                            dateCard.dayBadge
                                        }}</span>
                                        <strong class="date-choice-day">{{
                                            dateCard.dayNum
                                        }}</strong>
                                        <span class="date-choice-month">{{
                                            dateCard.monthYear
                                        }}</span>
                                        <span
                                            class="date-choice-slots"
                                            :class="{
                                                'is-closed': dateCard.disabled,
                                            }"
                                        >
                                            {{
                                                dateCard.disabled
                                                    ? 'Closed'
                                                    : `${dateCard.totalRemaining} open`
                                            }}
                                        </span>
                                    </button>
                                </div>
                                <InputError
                                    :message="form.errors.appointment_date"
                                />
                            </div>

                            <div class="field" style="margin-top: 1.1rem">
                                <label class="section-sublabel">
                                    Clinic Session
                                </label>
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
                                            <div class="session-card__title">
                                                <i
                                                    :class="
                                                        option.value ===
                                                        'morning'
                                                            ? 'pi pi-sun'
                                                            : 'pi pi-moon'
                                                    "
                                                />
                                                <strong>{{
                                                    option.label
                                                }}</strong>
                                            </div>
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
                                                    ? 'This session is closed or full.'
                                                    : `Next free token starts at #${option.availableTokens[0] ?? '-'}`
                                            }}
                                        </p>
                                    </button>
                                </div>

                                <InputError
                                    :message="form.errors.appointment_session"
                                />
                            </div>
                        </div>

                        <!-- Step 3: Token Group Selection -->
                        <div class="booking-section">
                            <div class="booking-section__header">
                                <div class="booking-section__title-row">
                                    <span class="step-badge">3</span>
                                    <div>
                                        <p class="booking-section__title">
                                            Choose your token range
                                        </p>
                                        <p class="booking-section__hint">
                                            Pick an early or later 5-token
                                            group. We reserve the earliest free
                                            token in that group.
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="tokenGroupOptions.length > 0"
                                    class="booking-section__meta"
                                >
                                    {{ tokenGroupOptions.length }} groups open
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
                                    <span class="token-pill__range">
                                        Tokens {{ tokenGroup.label }}
                                    </span>
                                    <span class="token-pill__assigned">
                                        Gets #{{ tokenGroup.token }} ·
                                        {{ tokenGroup.availableCount }} free
                                    </span>
                                </button>
                            </div>

                            <div v-else class="token-empty">
                                Select an available date and session above to
                                see open token groups.
                            </div>

                            <InputError
                                :message="form.errors.appointment_number"
                            />
                        </div>

                        <!-- Step 4: Quick Reason Chips + Optional Note -->
                        <div class="booking-section">
                            <div class="booking-section__header">
                                <div class="booking-section__title-row">
                                    <span class="step-badge">4</span>
                                    <div>
                                        <p class="booking-section__title">
                                            Reason for visit
                                            <span class="optional-tag"
                                                >(Optional)</span
                                            >
                                        </p>
                                        <p class="booking-section__hint">
                                            Tap a quick reason below or write a
                                            short note for the doctor.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="quick-reasons-wrap">
                                <button
                                    v-for="reason in QUICK_REASONS"
                                    :key="reason"
                                    type="button"
                                    class="quick-reason-chip"
                                    :class="{
                                        'quick-reason-chip--active':
                                            isReasonChipActive(reason),
                                    }"
                                    :disabled="!bookingEnabled"
                                    @click="toggleQuickReason(reason)"
                                >
                                    <i
                                        :class="
                                            isReasonChipActive(reason)
                                                ? 'pi pi-check'
                                                : 'pi pi-plus'
                                        "
                                    />
                                    <span>{{ reason }}</span>
                                </button>
                            </div>

                            <div class="field" style="margin-top: 0.85rem">
                                <Textarea
                                    id="reason_for_visit"
                                    v-model="form.reason_for_visit"
                                    rows="3"
                                    placeholder="Or type any symptoms, questions, or medication refill details..."
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
                                Instant confirmation for
                                <strong>{{
                                    selectedPatientCard?.cleanName ?? 'Patient'
                                }}</strong>
                                with
                                <strong>{{ selectedTokenLabel }}</strong>
                            </div>

                            <Button
                                type="submit"
                                label="Confirm booking"
                                icon="pi pi-check-circle"
                                :loading="form.processing"
                                :disabled="!canSubmit"
                            />
                        </div>
                    </form>
                </template>
            </Card>

            <!-- Sticky Desktop Summary Ticket -->
            <div class="booking-summary-aside">
                <Card class="summary-sticky-card">
                    <template #title>
                        <div class="booking-card-header">
                            <div>
                                <span class="booking-card-header__eyebrow"
                                    >Appointment pass</span
                                >
                                <div class="booking-card-header__title">
                                    Review & Confirm
                                </div>
                            </div>
                            <Tag
                                :severity="
                                    selectedSession ? 'success' : 'contrast'
                                "
                                :value="
                                    selectedSession
                                        ? 'Ready to book'
                                        : 'Incomplete'
                                "
                            />
                        </div>
                    </template>

                    <template #content>
                        <div class="summary-panel">
                            <div class="summary-token-banner">
                                <div>
                                    <span class="summary-token-banner__label"
                                        >Assigned Queue Token</span
                                    >
                                    <strong
                                        class="summary-token-banner__value"
                                        >{{ selectedTokenLabel }}</strong
                                    >
                                </div>
                                <span class="summary-token-banner__group">
                                    Group {{ selectedTokenGroupLabel }}
                                </span>
                            </div>

                            <div class="summary-rows">
                                <div class="summary-row">
                                    <span class="summary-row__label">
                                        <i class="pi pi-user" /> Patient
                                    </span>
                                    <strong class="summary-row__value">
                                        {{
                                            selectedPatientCard?.cleanName ??
                                            'Select patient'
                                        }}
                                    </strong>
                                </div>

                                <div class="summary-row">
                                    <span class="summary-row__label">
                                        <i class="pi pi-tag" /> Visit Type
                                    </span>
                                    <strong class="summary-row__value">
                                        {{
                                            selectedAppointmentType?.label ??
                                            'Choose type'
                                        }}
                                    </strong>
                                </div>

                                <div class="summary-row">
                                    <span class="summary-row__label">
                                        <i class="pi pi-calendar" /> Date
                                    </span>
                                    <strong class="summary-row__value">
                                        {{ selectedDate?.label ?? 'Pick date' }}
                                    </strong>
                                </div>

                                <div class="summary-row">
                                    <span class="summary-row__label">
                                        <i class="pi pi-clock" /> Session
                                    </span>
                                    <strong class="summary-row__value">
                                        {{
                                            selectedSession?.label ??
                                            'Pick session'
                                        }}
                                    </strong>
                                </div>
                            </div>

                            <Button
                                type="button"
                                label="Confirm booking"
                                icon="pi pi-check-circle"
                                class="w-full"
                                :loading="form.processing"
                                :disabled="!canSubmit"
                                @click="submit"
                            />

                            <div class="summary-note">
                                Live queue updates automatically. You can track
                                your token status anytime from your dashboard.
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Sticky Mobile Bottom Confirm Bar -->
        <div class="mobile-sticky-confirm">
            <div class="mobile-sticky-confirm__info">
                <span class="mobile-sticky-confirm__meta">
                    {{ selectedDate?.label ?? 'Pick date' }} ·
                    {{ selectedSession?.label ?? 'Session' }}
                </span>
                <strong class="mobile-sticky-confirm__token">
                    {{ selectedTokenLabel }}
                    <span v-if="selectedPatientCard">
                        for {{ selectedPatientCard.cleanName }}
                    </span>
                </strong>
            </div>

            <Button
                type="button"
                label="Confirm"
                icon="pi pi-check-circle"
                :loading="form.processing"
                :disabled="!canSubmit"
                @click="submit"
            />
        </div>
    </div>
</template>

<style scoped>
.booking-page {
    display: grid;
    gap: 1.25rem;
    padding-bottom: 1rem;
}

.booking-compact-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    flex-wrap: wrap;
    padding: 1.25rem 1.5rem;
    border-radius: var(--content-border-radius);
    background: #ffffff;
    border: 1px solid rgba(15, 181, 186, 0.16);
    box-shadow: var(--card-shadow);
}

.booking-compact-header__main {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.booking-kicker {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.72rem;
    font-weight: 700;
    color: #0c6e72;
}

.booking-compact-header__title {
    margin: 0.25rem 0 0;
    font-size: clamp(1.25rem, 2vw, 1.55rem);
    font-weight: 800;
    line-height: 1.2;
    color: #10242e;
}

.booking-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
}

.booking-step {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.8rem;
    border-radius: 999px;
    background: #f4f8f9;
    color: #5d747b;
    font-size: 0.82rem;
    font-weight: 600;
    border: 1px solid rgba(15, 181, 186, 0.12);
}

.booking-step--active {
    background: rgba(15, 181, 186, 0.14);
    color: #0c6e72;
    border-color: rgba(15, 181, 186, 0.3);
}

.booking-step__index {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 999px;
    background: #0c6e72;
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 700;
}

.booking-compact-header__actions {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.booking-live-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.9rem;
    border-radius: 999px;
    background: #f0fafa;
    border: 1px solid rgba(15, 181, 186, 0.24);
    color: #0c6e72;
    font-size: 0.84rem;
    font-weight: 700;
}

.booking-live-pill__dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 999px;
    background: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
}

.booking-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(19rem, 0.85fr);
    gap: 1.35rem;
    align-items: start;
}

.booking-summary-aside {
    position: sticky;
    top: 1.25rem;
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
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #6b838a;
}

.booking-card-header__title {
    margin-top: 0.25rem;
    font-size: 1.12rem;
    font-weight: 700;
    color: #10242e;
}

.booking-form {
    display: grid;
    gap: 1.15rem;
}

.booking-section {
    padding: 1.15rem;
    border-radius: var(--content-border-radius);
    background: #f8fbfc;
    border: 1px solid rgba(15, 181, 186, 0.14);
}

.booking-section__header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.booking-section__title-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.step-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 0.5rem;
    background: #10242e;
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 700;
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.booking-section__title {
    margin: 0;
    font-weight: 700;
    font-size: 1rem;
    color: #10242e;
}

.optional-tag {
    font-weight: 500;
    font-size: 0.84rem;
    color: #64748b;
}

.booking-section__hint,
.booking-section__meta,
.booking-actions__copy,
.summary-note {
    margin: 0.25rem 0 0;
    color: #5e757d;
    font-size: 0.88rem;
    line-height: 1.5;
}

.booking-section__meta {
    margin-top: 0;
    font-weight: 600;
    color: #0c6e72;
}

.family-manage-link {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.84rem;
    font-weight: 600;
    color: #0c6e72;
    text-decoration: none;
    padding: 0.35rem 0.7rem;
    border-radius: 0.5rem;
    background: rgba(15, 181, 186, 0.1);
    transition: background 0.15s ease;
}

.family-manage-link:hover {
    background: rgba(15, 181, 186, 0.18);
}

.section-sublabel {
    display: block;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #546e7a;
    margin-bottom: 0.55rem;
}

/* Patient Choice Cards */
.patient-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(13.5rem, 1fr));
    gap: 0.75rem;
}

.patient-choice-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.8rem 0.95rem;
    border-radius: 0.75rem;
    border: 1.5px solid #dbe7ea;
    background: #ffffff;
    text-align: left;
    cursor: pointer;
    transition: all 0.16s ease;
}

.patient-choice-card:hover:not(:disabled) {
    border-color: #0fb5ba;
    transform: translateY(-1px);
}

.patient-choice-card--selected {
    border-color: #0c6e72;
    background: #eefaf9;
    box-shadow: 0 6px 16px rgba(12, 110, 114, 0.08);
}

.patient-choice-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 999px;
    background: rgba(15, 181, 186, 0.15);
    color: #0c6e72;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.patient-choice-body {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.patient-choice-name {
    font-size: 0.94rem;
    color: #10242e;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.patient-choice-badge {
    font-size: 0.76rem;
    color: #60757d;
    margin-top: 0.1rem;
}

.patient-choice-check {
    color: #0c6e72;
    font-size: 1.1rem;
    flex-shrink: 0;
}

/* Consultation Type Cards */
.type-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
    gap: 0.75rem;
}

.type-choice-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border-radius: 0.75rem;
    border: 1.5px solid #dbe7ea;
    background: #ffffff;
    text-align: left;
    cursor: pointer;
    transition: all 0.16s ease;
}

.type-choice-card:hover:not(:disabled) {
    border-color: #0fb5ba;
    transform: translateY(-1px);
}

.type-choice-card--selected {
    border-color: #0c6e72;
    background: #eefaf9;
    box-shadow: 0 6px 16px rgba(12, 110, 114, 0.08);
}

.type-choice-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 0.65rem;
    background: rgba(15, 181, 186, 0.14);
    color: #0c6e72;
    font-size: 1rem;
    flex-shrink: 0;
}

.type-choice-content {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}

.type-choice-content strong {
    font-size: 0.95rem;
    color: #10242e;
}

.type-choice-content span {
    font-size: 0.79rem;
    color: #5e757d;
    margin-top: 0.15rem;
}

.type-choice-check {
    color: #0c6e72;
    font-size: 1.1rem;
    flex-shrink: 0;
}

/* Visual Date Strip */
.date-cards-strip {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(6.8rem, 1fr));
    gap: 0.65rem;
}

.date-choice-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.75rem 0.6rem;
    border-radius: 0.75rem;
    border: 1.5px solid #dbe7ea;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.16s ease;
}

.date-choice-card:hover:not(:disabled) {
    border-color: #0fb5ba;
    transform: translateY(-1px);
}

.date-choice-card--selected {
    border-color: #10242e;
    background: #10242e;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(16, 36, 46, 0.16);
}

.date-choice-card--disabled {
    opacity: 0.55;
    cursor: not-allowed;
    background: #f1f5f7;
}

.date-choice-badge {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #0c6e72;
}

.date-choice-card--selected .date-choice-badge {
    color: #5eead4;
}

.date-choice-day {
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1.2;
    margin-top: 0.2rem;
    color: #10242e;
}

.date-choice-card--selected .date-choice-day {
    color: #ffffff;
}

.date-choice-month {
    font-size: 0.76rem;
    color: #60757d;
}

.date-choice-card--selected .date-choice-month {
    color: #cbd5e1;
}

.date-choice-slots {
    margin-top: 0.45rem;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    background: rgba(16, 185, 129, 0.12);
    color: #047857;
}

.date-choice-card--selected .date-choice-slots {
    background: rgba(255, 255, 255, 0.16);
    color: #ffffff;
}

.date-choice-slots.is-closed {
    background: rgba(239, 68, 68, 0.12);
    color: #b91c1c;
}

/* Session Grid */
.session-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
    gap: 0.8rem;
}

.session-card {
    padding: 0.95rem 1rem;
    border-radius: 0.75rem;
    border: 1.5px solid #dbe7ea;
    background: #ffffff;
    text-align: left;
    cursor: pointer;
    transition: all 0.16s ease;
}

.session-card:hover:not(:disabled) {
    border-color: #0fb5ba;
    transform: translateY(-1px);
}

.session-card--selected {
    border-color: #0c6e72;
    background: #eefaf9;
    box-shadow: 0 6px 16px rgba(12, 110, 114, 0.08);
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
}

.session-card__title {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #10242e;
    font-size: 0.98rem;
}

.session-card__title i {
    color: #0c6e72;
}

.session-card__copy {
    margin: 0.45rem 0 0;
    color: #5e757d;
    font-size: 0.84rem;
}

/* Token Group Grid */
.token-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr));
    gap: 0.7rem;
}

.token-pill {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 0.75rem 0.9rem;
    border-radius: 0.75rem;
    border: 1.5px solid #dbe7ea;
    background: #ffffff;
    color: #10242e;
    cursor: pointer;
    transition: all 0.16s ease;
}

.token-pill:hover {
    border-color: #0fb5ba;
    transform: translateY(-1px);
}

.token-pill--selected {
    background: #10242e;
    color: #ffffff;
    border-color: #10242e;
    box-shadow: 0 8px 18px rgba(16, 36, 46, 0.15);
}

.token-pill__range {
    font-weight: 800;
    font-size: 0.95rem;
}

.token-pill__assigned {
    font-size: 0.75rem;
    margin-top: 0.2rem;
    color: #0c6e72;
    font-weight: 600;
}

.token-pill--selected .token-pill__assigned {
    color: #5eead4;
}

.token-empty {
    padding: 0.95rem 1rem;
    border-radius: 0.75rem;
    background: rgba(15, 181, 186, 0.06);
    color: #60767d;
}

/* Quick Reason Chips */
.quick-reasons-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.quick-reason-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.8rem;
    border-radius: 999px;
    border: 1px solid #d5e3e6;
    background: #ffffff;
    color: #334e58;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.quick-reason-chip:hover:not(:disabled) {
    border-color: #0fb5ba;
    color: #0c6e72;
}

.quick-reason-chip--active {
    background: #0c6e72;
    border-color: #0c6e72;
    color: #ffffff;
}

.quick-reason-chip i {
    font-size: 0.72rem;
}

.booking-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding-top: 0.25rem;
}

/* Summary Ticket Panel */
.summary-panel {
    display: grid;
    gap: 1rem;
}

.summary-token-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem 1.1rem;
    border-radius: 0.75rem;
    background: #10242e;
    color: #ffffff;
}

.summary-token-banner__label {
    display: block;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
}

.summary-token-banner__value {
    display: block;
    font-size: 1.35rem;
    font-weight: 800;
    margin-top: 0.15rem;
    color: #5eead4;
}

.summary-token-banner__group {
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    font-size: 0.78rem;
    font-weight: 600;
}

.summary-rows {
    display: grid;
    gap: 0.65rem;
    padding: 0.95rem 1rem;
    border-radius: 0.75rem;
    background: #f8fbfc;
    border: 1px solid #e2ecef;
}

.summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.88rem;
}

.summary-row__label {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: #60757d;
}

.summary-row__label i {
    color: #0c6e72;
    font-size: 0.85rem;
}

.summary-row__value {
    color: #10242e;
    text-align: right;
}

.summary-note {
    padding: 0.85rem 0.95rem;
    border-radius: 0.75rem;
    background: #f4f8f9;
    margin-top: 0;
    font-size: 0.82rem;
}

/* Mobile Sticky Bottom Confirm Bar */
.mobile-sticky-confirm {
    display: none;
}

@media (max-width: 991px) {
    .booking-layout {
        grid-template-columns: 1fr;
    }

    .booking-summary-aside {
        position: static;
    }

    .booking-page {
        padding-bottom: 5.5rem;
    }

    .mobile-sticky-confirm {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 40;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.85rem 1.15rem;
        background: #ffffff;
        border-top: 1px solid #dbe7ea;
        box-shadow: 0 -8px 24px rgba(16, 36, 46, 0.1);
    }

    .mobile-sticky-confirm__info {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .mobile-sticky-confirm__meta {
        font-size: 0.75rem;
        color: #60757d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mobile-sticky-confirm__token {
        font-size: 0.95rem;
        color: #10242e;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
}
</style>
