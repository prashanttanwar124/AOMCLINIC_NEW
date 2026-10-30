<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
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
    return found ? found.name.replace(/\s*\(Self\)$/i, '') : 'Patient';
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

    <div class="compact-booking-page">
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

        <form @submit.prevent="submit" class="page-card compact-booking-card">
            <!-- Row 1: Patient, Visit Type, and Session in one horizontal row -->
            <div class="top-controls-grid">
                <div class="control-block">
                    <label for="patient_id" class="control-label">
                        1. Patient
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

                <div class="control-block">
                    <label class="control-label">2. Visit Type</label>
                    <div class="pill-toggle-group">
                        <button
                            v-for="typeOption in appointmentTypes"
                            :key="typeOption.value"
                            type="button"
                            class="pill-toggle-btn"
                            :class="{
                                'pill-toggle-btn--active':
                                    form.appointment_type === typeOption.value,
                            }"
                            :disabled="!bookingEnabled"
                            @click="form.appointment_type = typeOption.value"
                        >
                            {{ typeOption.label }}
                        </button>
                    </div>
                    <InputError :message="form.errors.appointment_type" />
                </div>

                <div class="control-block">
                    <label class="control-label">3. Session</label>
                    <div class="pill-toggle-group">
                        <button
                            v-for="option in sessionOptions"
                            :key="option.value"
                            type="button"
                            class="pill-toggle-btn session-toggle-btn"
                            :class="{
                                'pill-toggle-btn--active':
                                    option.value === form.appointment_session,
                                'pill-toggle-btn--disabled': option.disabled,
                            }"
                            :disabled="option.disabled"
                            @click="chooseSession(option)"
                        >
                            <i
                                :class="
                                    option.value === 'morning'
                                        ? 'pi pi-sun'
                                        : 'pi pi-moon'
                                "
                            />
                            <span>{{ option.label }}</span>
                            <small class="session-badge">
                                {{
                                    option.disabled
                                        ? 'Full'
                                        : `${option.remaining} left`
                                }}
                            </small>
                        </button>
                    </div>
                    <InputError :message="form.errors.appointment_session" />
                </div>
            </div>

            <!-- Row 2: Date & Token side-by-side -->
            <div class="middle-controls-grid">
                <div class="control-block">
                    <label class="control-label">4. Select Date</label>
                    <div class="compact-date-strip">
                        <button
                            v-for="(option, index) in dateOptions"
                            :key="option.value"
                            type="button"
                            class="compact-date-btn"
                            :class="{
                                'compact-date-btn--active':
                                    option.value === form.appointment_date,
                                'compact-date-btn--disabled': option.disabled,
                            }"
                            :disabled="!bookingEnabled || option.disabled"
                            @click="chooseDate(option)"
                        >
                            <span class="compact-date-btn__day">
                                {{
                                    index === 0
                                        ? 'Today'
                                        : index === 1
                                          ? 'Tomorrow'
                                          : option.label.split(',')[0]
                                }}
                            </span>
                            <strong class="compact-date-btn__num">
                                {{
                                    option.label
                                        .replace(/^[^,]+,\s*/, '')
                                        .replace(/\s+\d{4}$/, '')
                                }}
                            </strong>
                        </button>
                    </div>
                    <InputError :message="form.errors.appointment_date" />
                </div>

                <div class="control-block">
                    <div class="control-label-row">
                        <label class="control-label" style="margin: 0">
                            5. Token Group
                        </label>
                        <span
                            v-if="form.appointment_number"
                            class="assigned-badge"
                        >
                            Gets Token #{{ form.appointment_number }}
                        </span>
                    </div>

                    <div
                        v-if="tokenGroupOptions.length > 0"
                        class="compact-token-strip"
                    >
                        <button
                            v-for="tokenGroup in tokenGroupOptions"
                            :key="tokenGroup.label"
                            type="button"
                            class="compact-token-btn"
                            :class="{
                                'compact-token-btn--active':
                                    tokenGroup.token ===
                                    form.appointment_number,
                            }"
                            @click="chooseTokenGroup(tokenGroup)"
                        >
                            {{ tokenGroup.label }}
                        </button>
                    </div>
                    <div v-else class="compact-empty">
                        No open tokens for this session
                    </div>

                    <InputError :message="form.errors.appointment_number" />
                </div>
            </div>

            <!-- Row 3: Reason & Confirm Action Bar in one row -->
            <div class="bottom-action-bar">
                <div class="reason-inline">
                    <InputText
                        id="reason_for_visit"
                        v-model="form.reason_for_visit"
                        placeholder="Optional note or symptoms for the doctor..."
                        :disabled="!bookingEnabled"
                        fluid
                    />
                    <InputError :message="form.errors.reason_for_visit" />
                </div>

                <div class="confirm-inline">
                    <div v-if="canSubmit" class="confirm-inline__summary">
                        <span>{{ selectedPatientName }}</span> ·
                        <strong>{{ selectedDate?.label.replace(/\s+\d{4}$/, '') }}</strong> ·
                        <strong>{{ selectedSession?.label }}</strong> ·
                        <span class="token-chip">
                            Token #{{ form.appointment_number }}
                        </span>
                    </div>

                    <Button
                        type="submit"
                        label="Confirm Booking"
                        icon="pi pi-check-circle"
                        class="confirm-btn"
                        :loading="form.processing"
                        :disabled="!canSubmit"
                    />
                </div>
            </div>
        </form>
    </div>
</template>

<style scoped>
.compact-booking-page {
    display: grid;
    gap: 0.85rem;
}

.compact-booking-card {
    display: grid;
    gap: 1.15rem;
    padding: 1.25rem 1.4rem;
    background: #ffffff;
}

.control-block {
    display: grid;
    gap: 0.45rem;
    align-content: start;
}

.control-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.control-label {
    font-size: 0.92rem;
    font-weight: 800;
    color: #10242e;
}

.assigned-badge {
    font-size: 0.82rem;
    font-weight: 800;
    color: #0c6e72;
    background: #eefaf9;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    border: 1px solid rgba(15, 181, 186, 0.3);
}

/* Row 1: 3 Columns */
.top-controls-grid {
    display: grid;
    grid-template-columns: minmax(13rem, 1fr) minmax(14rem, 1.1fr) minmax(16rem, 1.3fr);
    gap: 1rem;
    align-items: start;
}

.pill-toggle-group {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.45rem;
}

.pill-toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    min-height: 2.75rem;
    padding: 0.45rem 0.7rem;
    border-radius: 0.6rem;
    border: 1.5px solid #cbd5e1;
    background: #ffffff;
    color: #1e293b;
    font-size: 0.92rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pill-toggle-btn:hover:not(:disabled) {
    border-color: #0fb5ba;
}

.pill-toggle-btn--active {
    background: #0c6e72;
    border-color: #0c6e72;
    color: #ffffff;
}

.pill-toggle-btn--disabled {
    opacity: 0.45;
    cursor: not-allowed;
    background: #f8fafc;
}

.session-badge {
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
    background: rgba(15, 181, 186, 0.14);
    color: #0c6e72;
}

.pill-toggle-btn--active .session-badge {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
}

/* Row 2: Date & Token side-by-side */
.middle-controls-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    gap: 1rem;
    padding-top: 0.95rem;
    border-top: 1px solid #e2ecef;
}

.compact-date-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(5.8rem, 1fr));
    gap: 0.5rem;
}

.compact-date-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 3.35rem;
    padding: 0.4rem 0.45rem;
    border-radius: 0.6rem;
    border: 1.5px solid #cbd5e1;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.15s ease;
}

.compact-date-btn:hover:not(:disabled) {
    border-color: #0fb5ba;
}

.compact-date-btn__day {
    font-size: 0.74rem;
    font-weight: 700;
    color: #64748b;
}

.compact-date-btn__num {
    font-size: 0.95rem;
    font-weight: 800;
    color: #10242e;
}

.compact-date-btn--active {
    background: #10242e;
    border-color: #10242e;
    color: #ffffff;
}

.compact-date-btn--active .compact-date-btn__day {
    color: #5eead4;
}

.compact-date-btn--active .compact-date-btn__num {
    color: #ffffff;
}

.compact-date-btn--disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: #f8fafc;
}

.compact-token-strip {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(4.6rem, 1fr));
    gap: 0.45rem;
}

.compact-token-btn {
    min-height: 3.35rem;
    padding: 0.4rem 0.5rem;
    border-radius: 0.6rem;
    border: 1.5px solid #cbd5e1;
    background: #ffffff;
    color: #10242e;
    font-size: 0.95rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}

.compact-token-btn:hover {
    border-color: #0fb5ba;
}

.compact-token-btn--active {
    background: #0c6e72;
    border-color: #0c6e72;
    color: #ffffff;
}

.compact-empty {
    display: flex;
    align-items: center;
    min-height: 3.35rem;
    padding: 0.5rem 0.85rem;
    border-radius: 0.6rem;
    background: #fef2f2;
    color: #991b1b;
    font-size: 0.88rem;
    font-weight: 600;
}

/* Row 3: Bottom Action Bar */
.bottom-action-bar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 1rem;
    align-items: center;
    padding-top: 0.95rem;
    border-top: 1px solid #e2ecef;
}

.confirm-inline {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.confirm-inline__summary {
    font-size: 0.92rem;
    color: #334155;
    background: #f0fdfa;
    border: 1px solid rgba(15, 181, 186, 0.3);
    padding: 0.55rem 0.85rem;
    border-radius: 0.6rem;
    white-space: nowrap;
}

.token-chip {
    display: inline-block;
    padding: 0.1rem 0.5rem;
    border-radius: 0.35rem;
    background: #0c6e72;
    color: #ffffff;
    font-weight: 800;
}

.confirm-btn {
    min-height: 2.85rem;
    font-size: 1rem;
    font-weight: 800;
    white-space: nowrap;
}

@media (max-width: 991px) {
    .top-controls-grid,
    .middle-controls-grid,
    .bottom-action-bar {
        grid-template-columns: 1fr;
    }

    .confirm-inline {
        justify-content: space-between;
    }

    .confirm-inline__summary {
        white-space: normal;
    }
}
</style>
