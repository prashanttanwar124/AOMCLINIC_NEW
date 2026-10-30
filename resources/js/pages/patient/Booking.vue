<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import Button from 'primevue/button';
import Message from 'primevue/message';
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
    return found ? found.name.replace(/\s*\(Self\)$/i, '') : 'You';
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

    <div class="senior-booking-page">
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

        <form @submit.prevent="submit" class="page-card senior-booking-card">
            <!-- Step 1: Patient (only shown prominently, very easy to tap) -->
            <div v-if="bookablePatients.length > 1" class="step-block">
                <h2 class="step-heading">1. Who is visiting the doctor?</h2>
                <div class="big-choice-grid">
                    <button
                        v-for="patient in bookablePatients"
                        :key="patient.id"
                        type="button"
                        class="big-choice-btn"
                        :class="{
                            'big-choice-btn--selected':
                                form.patient_id === patient.id,
                        }"
                        :disabled="!bookingEnabled"
                        @click="form.patient_id = patient.id"
                    >
                        <i
                            :class="
                                form.patient_id === patient.id
                                    ? 'pi pi-check-circle'
                                    : 'pi pi-circle'
                            "
                        />
                        <span>{{ patient.name }}</span>
                    </button>
                </div>
                <InputError :message="form.errors.patient_id" />
            </div>

            <!-- Step 2: Visit Day -->
            <div class="step-block">
                <h2 class="step-heading">
                    {{ bookablePatients.length > 1 ? '2.' : '1.' }} Which day do
                    you want to visit?
                </h2>
                <div class="day-grid">
                    <button
                        v-for="(option, index) in dateOptions"
                        :key="option.value"
                        type="button"
                        class="day-btn"
                        :class="{
                            'day-btn--selected':
                                option.value === form.appointment_date,
                            'day-btn--disabled': option.disabled,
                        }"
                        :disabled="!bookingEnabled || option.disabled"
                        @click="chooseDate(option)"
                    >
                        <span class="day-btn__when">
                            {{
                                index === 0
                                    ? 'Today'
                                    : index === 1
                                      ? 'Tomorrow'
                                      : option.label.split(',')[0]
                            }}
                        </span>
                        <strong class="day-btn__date">
                            {{
                                option.label
                                    .replace(/^[^,]+,\s*/, '')
                                    .replace(/\s+\d{4}$/, '')
                            }}
                        </strong>
                        <span v-if="option.disabled" class="day-btn__closed">
                            Closed
                        </span>
                    </button>
                </div>
                <InputError :message="form.errors.appointment_date" />
            </div>

            <!-- Step 3: Morning or Evening -->
            <div class="step-block">
                <h2 class="step-heading">
                    {{ bookablePatients.length > 1 ? '3.' : '2.' }} Morning or
                    Evening?
                </h2>
                <div class="session-grid">
                    <button
                        v-for="option in sessionOptions"
                        :key="option.value"
                        type="button"
                        class="session-big-btn"
                        :class="{
                            'session-big-btn--selected':
                                option.value === form.appointment_session,
                            'session-big-btn--disabled': option.disabled,
                        }"
                        :disabled="option.disabled"
                        @click="chooseSession(option)"
                    >
                        <div class="session-big-btn__left">
                            <i
                                :class="
                                    option.value === 'morning'
                                        ? 'pi pi-sun'
                                        : 'pi pi-moon'
                                "
                            />
                            <div>
                                <strong>{{ option.label }}</strong>
                                <span>
                                    {{
                                        option.disabled
                                            ? 'Not available'
                                            : `${option.remaining} seats open`
                                    }}
                                </span>
                            </div>
                        </div>
                        <i
                            v-if="option.value === form.appointment_session"
                            class="pi pi-check-circle session-check"
                        />
                    </button>
                </div>
                <InputError :message="form.errors.appointment_session" />
            </div>

            <!-- Step 4: Token Number -->
            <div class="step-block">
                <h2 class="step-heading">
                    {{ bookablePatients.length > 1 ? '4.' : '3.' }} Choose Token
                    Number
                </h2>
                <p class="step-subtext">
                    We have already selected the earliest available token for
                    you. Tap another group if you prefer a later number.
                </p>

                <div v-if="tokenGroupOptions.length > 0" class="token-grid">
                    <button
                        v-for="tokenGroup in tokenGroupOptions"
                        :key="tokenGroup.label"
                        type="button"
                        class="token-big-btn"
                        :class="{
                            'token-big-btn--selected':
                                tokenGroup.token === form.appointment_number,
                        }"
                        @click="chooseTokenGroup(tokenGroup)"
                    >
                        <span class="token-big-btn__range"
                            >Tokens {{ tokenGroup.label }}</span
                        >
                        <strong class="token-big-btn__gets"
                            >Token #{{ tokenGroup.token }}</strong
                        >
                    </button>
                </div>

                <div v-else class="empty-box">
                    No tokens available for this session. Please choose another
                    day or session above.
                </div>

                <InputError :message="form.errors.appointment_number" />
            </div>

            <!-- Step 5: Visit Type & Optional Note -->
            <div class="step-block">
                <h2 class="step-heading">
                    {{ bookablePatients.length > 1 ? '5.' : '4.' }} Visit Type
                </h2>
                <div class="big-choice-grid">
                    <button
                        v-for="typeOption in appointmentTypes"
                        :key="typeOption.value"
                        type="button"
                        class="big-choice-btn"
                        :class="{
                            'big-choice-btn--selected':
                                form.appointment_type === typeOption.value,
                        }"
                        :disabled="!bookingEnabled"
                        @click="form.appointment_type = typeOption.value"
                    >
                        <i
                            :class="
                                form.appointment_type === typeOption.value
                                    ? 'pi pi-check-circle'
                                    : 'pi pi-circle'
                            "
                        />
                        <span>{{ typeOption.label }}</span>
                    </button>
                </div>
                <InputError :message="form.errors.appointment_type" />

                <div class="note-box">
                    <label for="reason_for_visit" class="note-label">
                        Health problem or note for doctor (Optional)
                    </label>
                    <Textarea
                        id="reason_for_visit"
                        v-model="form.reason_for_visit"
                        rows="2"
                        placeholder="Optional: Write your health concern here..."
                        :disabled="!bookingEnabled"
                        auto-resize
                        fluid
                        class="senior-textarea"
                    />
                    <InputError :message="form.errors.reason_for_visit" />
                </div>
            </div>

            <!-- Final Confirmation Box -->
            <div class="confirm-box">
                <div v-if="canSubmit" class="confirm-summary">
                    <div class="confirm-summary__label">
                        Your Selected Appointment:
                    </div>
                    <div class="confirm-summary__details">
                        <strong>{{ selectedPatientName }}</strong> ·
                        <strong>{{ selectedDate?.label }}</strong> ·
                        <strong>{{ selectedSession?.label }}</strong> ·
                        <span class="confirm-token-highlight">
                            Token #{{ form.appointment_number }}
                        </span>
                    </div>
                </div>
                <div v-else class="confirm-summary">
                    <div class="confirm-summary__details">
                        Please select an open day and session above.
                    </div>
                </div>

                <Button
                    type="submit"
                    label="Confirm & Book Appointment"
                    icon="pi pi-check-circle"
                    class="confirm-submit-btn"
                    :loading="form.processing"
                    :disabled="!canSubmit"
                />
            </div>
        </form>
    </div>
</template>

<style scoped>
.senior-booking-page {
    display: grid;
    gap: 1.25rem;
    max-width: 50rem;
}

.senior-booking-card {
    display: grid;
    gap: 1.75rem;
    padding: 1.75rem;
    background: #ffffff;
}

.step-block {
    display: grid;
    gap: 0.75rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e2ecef;
}

.step-heading {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 800;
    color: #10242e;
}

.step-subtext {
    margin: -0.25rem 0 0;
    font-size: 0.98rem;
    color: #475569;
    line-height: 1.45;
}

/* Big Choice Buttons (Patient & Visit Type) */
.big-choice-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
    gap: 0.75rem;
}

.big-choice-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-height: 3.5rem;
    padding: 0.85rem 1.15rem;
    border-radius: 0.75rem;
    border: 2px solid #cbd5e1;
    background: #ffffff;
    color: #1e293b;
    font-size: 1.05rem;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
}

.big-choice-btn i {
    font-size: 1.25rem;
    color: #94a3b8;
}

.big-choice-btn--selected {
    border-color: #0c6e72;
    background: #eefaf9;
    color: #0c6e72;
}

.big-choice-btn--selected i {
    color: #0c6e72;
}

/* Day Buttons */
.day-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr));
    gap: 0.75rem;
}

.day-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 4.75rem;
    padding: 0.75rem;
    border-radius: 0.75rem;
    border: 2px solid #cbd5e1;
    background: #ffffff;
    cursor: pointer;
}

.day-btn__when {
    font-size: 0.88rem;
    font-weight: 700;
    color: #475569;
}

.day-btn__date {
    font-size: 1.15rem;
    font-weight: 800;
    color: #10242e;
    margin-top: 0.15rem;
}

.day-btn__closed {
    font-size: 0.8rem;
    font-weight: 700;
    color: #dc2626;
    margin-top: 0.15rem;
}

.day-btn--selected {
    border-color: #0c6e72;
    background: #0c6e72;
    color: #ffffff;
}

.day-btn--selected .day-btn__when,
.day-btn--selected .day-btn__date {
    color: #ffffff;
}

.day-btn--disabled {
    opacity: 0.45;
    cursor: not-allowed;
    background: #f8fafc;
}

/* Session Big Buttons */
.session-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.session-big-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 4.5rem;
    padding: 1rem 1.25rem;
    border-radius: 0.75rem;
    border: 2px solid #cbd5e1;
    background: #ffffff;
    text-align: left;
    cursor: pointer;
}

.session-big-btn__left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.session-big-btn__left i {
    font-size: 1.5rem;
    color: #0c6e72;
}

.session-big-btn__left strong {
    display: block;
    font-size: 1.15rem;
    color: #10242e;
}

.session-big-btn__left span {
    display: block;
    font-size: 0.92rem;
    color: #475569;
    margin-top: 0.1rem;
}

.session-big-btn--selected {
    border-color: #0c6e72;
    background: #eefaf9;
}

.session-check {
    font-size: 1.4rem;
    color: #0c6e72;
}

.session-big-btn--disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f8fafc;
}

/* Token Big Buttons */
.token-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr));
    gap: 0.75rem;
}

.token-big-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 4.25rem;
    padding: 0.75rem;
    border-radius: 0.75rem;
    border: 2px solid #cbd5e1;
    background: #ffffff;
    cursor: pointer;
}

.token-big-btn__range {
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
}

.token-big-btn__gets {
    font-size: 1.12rem;
    font-weight: 800;
    color: #10242e;
    margin-top: 0.15rem;
}

.token-big-btn--selected {
    border-color: #0c6e72;
    background: #0c6e72;
    color: #ffffff;
}

.token-big-btn--selected .token-big-btn__range,
.token-big-btn--selected .token-big-btn__gets {
    color: #ffffff;
}

.empty-box {
    padding: 1rem;
    border-radius: 0.75rem;
    background: #fef2f2;
    color: #991b1b;
    font-size: 1rem;
    font-weight: 600;
}

/* Note */
.note-box {
    display: grid;
    gap: 0.45rem;
    margin-top: 0.5rem;
}

.note-label {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
}

.senior-textarea {
    font-size: 1.05rem;
}

/* Confirm Box */
.confirm-box {
    display: grid;
    gap: 1rem;
    padding: 1.25rem;
    border-radius: 0.85rem;
    background: #f0fdfa;
    border: 2px solid #0c6e72;
}

.confirm-summary__label {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0c6e72;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.confirm-summary__details {
    font-size: 1.15rem;
    color: #10242e;
    margin-top: 0.25rem;
    line-height: 1.5;
}

.confirm-token-highlight {
    display: inline-block;
    padding: 0.15rem 0.6rem;
    border-radius: 0.4rem;
    background: #0c6e72;
    color: #ffffff;
    font-weight: 800;
}

.confirm-submit-btn {
    min-height: 3.6rem;
    font-size: 1.15rem;
    font-weight: 800;
}

@media (max-width: 640px) {
    .senior-booking-card {
        padding: 1.15rem;
    }

    .session-grid {
        grid-template-columns: 1fr;
    }
}
</style>
