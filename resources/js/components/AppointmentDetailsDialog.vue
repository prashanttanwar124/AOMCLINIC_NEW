<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import AutoComplete from 'primevue/autocomplete';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { computed, reactive, ref, watch } from 'vue';
import {
    toggleHold as toggleHoldAction,
    update as updateAction,
} from '@/actions/App/Http/Controllers/Admin/CurrentAppointmentController';
import { search as searchMedicinesAction } from '@/routes/admin/medicines';
import { receipt } from '@/routes/admin/appointments';
import http, { extractErrors, pushToast } from '@/lib/http';

type Session = 'Morning' | 'Evening';

type AppointmentDetails = {
    associatedComplaint: string | null;
    chiefComplaint: string | null;
    presentingComplaint: string | null;
    medicalHistory: string | null;
    paternalFamilyHistory: string | null;
    maternalFamilyHistory: string | null;
    vaccinationHistory: string | null;
    addictionHistory: string | null;
    dietaryHabits: string | null;
    occupation: string | null;
    childrenCount: number | null;
    currentMedications: string | null;
    appetite: string | null;
    thirst: string | null;
    sleepPattern: string | null;
    urination: string | null;
    bowelMovements: string | null;
    physicalExamination: string | null;
    personalityNotes: string | null;
    temperament: string | null;
    anxietyNotes: string | null;
    fearNotes: string | null;
    generalNature: string | null;
    dreamNotes: string | null;
    desires: string | null;
    cravings: string | null;
    followUpDate: string | null;
    prescriptionDays: number | null;
    medicationInstructions: string | null;
    diagnosisNotes: string | null;
    treatmentNotes: string | null;
};

type EditableAppointment = {
    purpose_of_appointment: string | null;
    chief_complaint: string | null;
    present_complaint: string | null;
    associated_complaint: string | null;
    past_history: string | null;
    family_history_father_side: string | null;
    family_history_mother_side: string | null;
    history_of_vaccination: string | null;
    addiction: string | null;
    diet: string | null;
    occupation: string | null;
    number_of_children: string | null;
    medicine_taking: string | null;
    appetite: string | null;
    thirst: string | null;
    sleep: string | null;
    urine: string | null;
    stool: string | null;
    pysical_examination: string | null;
    as_a_person: string | null;
    nature_of_person: string | null;
    anxiety: string | null;
    fear: string | null;
    nature: string | null;
    dreams: string | null;
    desire: string | null;
    craving: string | null;
    diagnosis: string | null;
    treatment: string | null;
    medication_instructions: string | null;
    follow_up_day: string | null;
    days_prescription: number | null;
    amount: string | null;
    medicines: any[] | null;
    temperature: string | null;
    weight: string | null;
    blood_pressure: string | null;
    pulse_rate: string | null;
    spo2: string | null;
    notes: string | null;
};

type AppointmentHistoryItem = {
    id: number;
    appointmentDate: string;
    appointmentDateLabel: string;
    appointmentType: string;
    session: string;
    status: string;
    editable: EditableAppointment;
};

type AppointmentCard = {
    id: number;
    history: AppointmentHistoryItem[];
    appointmentDate: string;
    appointmentNumber: string | null;
    appointmentSequence: number;
    appointmentType: string;
    amount: string | null;
    patientName: string;
    patientNumber: string | null;
    patientId?: number | null;
    gender: string | null;
    age: number | null;
    dateOfBirth?: string | null;
    address?: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
    session: Session;
    onHold: boolean;
    holdOrder: number | null;
    queueStatus: 'running' | 'pending' | 'on_hold' | 'complete';
    status: string;
    reasonForVisit: string | null;
    details: AppointmentDetails;
    editable: EditableAppointment;
};

type EditableKey = keyof EditableAppointment;

const props = defineProps<{
    visible: boolean;
    appointment: AppointmentCard | null;
}>();

const emit = defineEmits<{
    (e: 'update:visible', val: boolean): void;
    (e: 'saved', appointmentId: number): void;
}>();

const dialogVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val),
});

type FieldType = 'text' | 'textarea' | 'date' | 'number' | 'chips';

type FormField = {
    key: EditableKey;
    label: string;
    type: FieldType;
    wide?: boolean;
};

type FormSection = {
    title: string;
    icon: string;
    fields: FormField[];
};

const FORM_SECTIONS: FormSection[] = [
    {
        title: 'Consultation',
        icon: 'pi pi-comment',
        fields: [
            {
                key: 'purpose_of_appointment',
                label: 'Purpose of Appointment',
                type: 'textarea',
                wide: true,
            },
        ],
    },
    {
        title: 'Vitals & Basic Info',
        icon: 'pi pi-heart',
        fields: [
            { key: 'temperature', label: 'Temperature (°F)', type: 'text' },
            { key: 'weight', label: 'Weight (kg)', type: 'text' },
            { key: 'blood_pressure', label: 'Blood Pressure (mmHg)', type: 'text' },
            { key: 'pulse_rate', label: 'Heart / Pulse Rate (bpm)', type: 'text' },
            { key: 'spo2', label: 'Oxygen Level (SpO2 %)', type: 'text' },
            { key: 'notes', label: 'Vitals / Basic Info Notes', type: 'textarea', wide: true },
        ],
    },
    {
        title: 'Complaint & History',
        icon: 'pi pi-info-circle',
        fields: [
            {
                key: 'chief_complaint',
                label: 'Chief Complaint',
                type: 'textarea',
                wide: true,
            },
            {
                key: 'present_complaint',
                label: 'Presenting Complaint',
                type: 'textarea',
                wide: true,
            },
            {
                key: 'associated_complaint',
                label: 'Associated Complaint',
                type: 'textarea',
                wide: true,
            },
            {
                key: 'past_history',
                label: 'Past History',
                type: 'textarea',
                wide: true,
            },
            {
                key: 'family_history_father_side',
                label: 'Family History Father Side',
                type: 'text',
            },
            {
                key: 'family_history_mother_side',
                label: 'Family History Mother Side',
                type: 'text',
            },
            {
                key: 'history_of_vaccination',
                label: 'Vaccination History',
                type: 'textarea',
                wide: true,
            },
        ],
    },
    {
        title: 'Personal History',
        icon: 'pi pi-user',
        fields: [
            { key: 'addiction', label: 'Addiction', type: 'text' },
            { key: 'diet', label: 'Diet', type: 'text' },
            { key: 'occupation', label: 'Occupation', type: 'text' },
            {
                key: 'number_of_children',
                label: 'Number of Children',
                type: 'text',
            },
        ],
    },
    {
        title: 'Medical History',
        icon: 'pi pi-heart',
        fields: [
            {
                key: 'medicine_taking',
                label: 'Medicine Taking Regularly / Past Any Illness',
                type: 'textarea',
                wide: true,
            },
        ],
    },
    {
        title: 'General Information',
        icon: 'pi pi-list',
        fields: [
            { key: 'appetite', label: 'Appetite', type: 'text' },
            { key: 'thirst', label: 'Thirst', type: 'text' },
            { key: 'sleep', label: 'Sleep', type: 'text' },
            { key: 'urine', label: 'Urination', type: 'text' },
            { key: 'stool', label: 'Bowel Movements', type: 'text' },
        ],
    },
    {
        title: 'Mind State',
        icon: 'pi pi-cog',
        fields: [
            {
                key: 'as_a_person',
                label: 'As a Person',
                type: 'textarea',
                wide: true,
            },
            {
                key: 'nature_of_person',
                label: 'Nature of Person',
                type: 'text',
            },
            { key: 'anxiety', label: 'Anxiety', type: 'text' },
            { key: 'fear', label: 'Fear', type: 'text' },
            { key: 'nature', label: 'General Nature', type: 'text' },
            { key: 'dreams', label: 'Dreams', type: 'textarea', wide: true },
            { key: 'desire', label: 'Desires', type: 'text' },
            { key: 'craving', label: 'Cravings', type: 'text' },
        ],
    },
    {
        title: 'Examination & Plan',
        icon: 'pi pi-file-edit',
        fields: [
            {
                key: 'pysical_examination',
                label: 'Physical Examination',
                type: 'textarea',
                wide: true,
            },
            {
                key: 'diagnosis',
                label: 'Diagnosis Notes',
                type: 'textarea',
                wide: true,
            },
            {
                key: 'treatment',
                label: 'Treatment Notes',
                type: 'textarea',
                wide: true,
            },
            {
                key: 'medicines',
                label: 'Prescribed Medicines (Multiple)',
                type: 'chips',
                wide: true,
            },
            {
                key: 'medication_instructions',
                label: 'Medication Instructions',
                type: 'textarea',
                wide: true,
            },
            { key: 'follow_up_day', label: 'Follow-up Date', type: 'date' },
            {
                key: 'days_prescription',
                label: 'Prescription Days',
                type: 'number',
            },
            {
                key: 'amount',
                label: 'Fee / Amount',
                type: 'text',
            },
        ],
    },
];

function emptyEditable(): EditableAppointment {
    return {
        purpose_of_appointment: '',
        chief_complaint: '',
        present_complaint: '',
        associated_complaint: '',
        past_history: '',
        family_history_father_side: '',
        family_history_mother_side: '',
        history_of_vaccination: '',
        addiction: '',
        diet: '',
        occupation: '',
        number_of_children: '',
        medicine_taking: '',
        appetite: '',
        thirst: '',
        sleep: '',
        urine: '',
        stool: '',
        pysical_examination: '',
        as_a_person: '',
        nature_of_person: '',
        anxiety: '',
        fear: '',
        nature: '',
        dreams: '',
        desire: '',
        craving: '',
        diagnosis: '',
        treatment: '',
        medication_instructions: '',
        follow_up_day: null,
        days_prescription: null,
        amount: '',
        medicines: [],
        temperature: '',
        weight: '',
        blood_pressure: '',
        pulse_rate: '',
        spo2: '',
        notes: '',
    };
}

const toast = useToast();

const EDITABLE_KEYS = Object.keys(emptyEditable()) as EditableKey[];

const form = reactive<
    EditableAppointment & {
        errors: Partial<Record<EditableKey, string>>;
        processing: boolean;
    }
>({
    ...emptyEditable(),
    errors: {},
    processing: false,
});

let formDefaults: EditableAppointment = emptyEditable();

const isDirty = computed(() =>
    EDITABLE_KEYS.some((key) => {
        if (key === 'medicines') {
            const current = form[key] || [];
            const defaults = formDefaults[key] || [];
            if (current.length !== defaults.length) {
                return true;
            }
            return JSON.stringify(current) !== JSON.stringify(defaults);
        }
        return form[key] !== formDefaults[key];
    }),
);

function formData(): EditableAppointment {
    return Object.fromEntries(
        EDITABLE_KEYS.map((key) => [key, form[key]]),
    ) as EditableAppointment;
}

const activeFormSectionIndex = ref(0);

function sectionHasData(section: FormSection): boolean {
    return section.fields.some((field) => {
        const val = form[field.key];
        return val !== null && val !== undefined && val !== '' && val !== 0;
    });
}

function parseDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function toDateString(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const activeHistoryAppointmentId = ref<number | null>(null);

const activeHistoryAppointment = computed<AppointmentHistoryItem | null>(() => {
    if (!props.appointment) {
        return null;
    }
    return (
        props.appointment.history?.find(
            (h) => h.id === activeHistoryAppointmentId.value,
        ) ?? null
    );
});

watch(
    () => props.appointment,
    (appointment) => {
        if (!appointment) {
            return;
        }
        activeHistoryAppointmentId.value = appointment.id;
        activeFormSectionIndex.value = 0;
    },
    { immediate: true },
);

watch(
    activeHistoryAppointment,
    (historyApt) => {
        if (!historyApt) {
            return;
        }
        formDefaults = { ...historyApt.editable };
        Object.assign(form, historyApt.editable);
        form.errors = {};
    },
    { immediate: true },
);

const followUpModel = computed<Date | null>({
    get: () => (form.follow_up_day ? parseDate(form.follow_up_day) : null),
    set: (value) => {
        form.follow_up_day = value ? toDateString(value) : null;
    },
});

const medicinesChips = computed<any[]>({
    get: () => (form.medicines || []).map((m: any) => {
        if (typeof m === 'string') {
            return { id: m, label: m };
        }
        return m;
    }),
    set: (value: any[]) => {
        form.medicines = value.map((m) => {
            if (typeof m === 'string') {
                return { id: m, label: m };
            }
            return m;
        });
    },
});

const filteredMedicines = ref<any[]>([]);

async function searchMedicines(event: { query: string }): Promise<void> {
    const query = event.query.trim();
    if (!query) {
        filteredMedicines.value = [];
        return;
    }
    try {
        const { data } = await http.get(searchMedicinesAction({ query: { query } }).url);
        filteredMedicines.value = data;
    } catch {
        filteredMedicines.value = [];
    }
}

function formatCurrency(amount: string | null): string | null {
    if (amount === null) {
        return null;
    }
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(Number(amount));
}

function formatValue(value: string | null | undefined): string {
    if (!value) {
        return 'Not added yet';
    }
    return value;
}

function formatDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-').map(Number);

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
}

const patientSummary = computed(() => {
    const appointment = props.appointment;
    if (!appointment) {
        return [];
    }

    return [
        { label: 'Patient ID', value: appointment.patientId ? `#${appointment.patientId}` : null, icon: 'pi pi-id-card' },
        { label: 'Gender', value: appointment.gender, icon: 'pi pi-user' },
        { label: 'Age', value: appointment.age !== null ? `${appointment.age}y` : null, icon: 'pi pi-calendar' },
        { label: 'Date of Birth', value: appointment.dateOfBirth ? formatDate(appointment.dateOfBirth) : null, icon: 'pi pi-calendar-plus' },
        { label: 'Phone', value: appointment.phone, icon: 'pi pi-phone' },
        { label: 'Email', value: appointment.email, icon: 'pi pi-envelope' },
        { label: 'City', value: appointment.city, icon: 'pi pi-map' },
        { label: 'Address', value: appointment.address, icon: 'pi pi-map-marker' },
        { label: 'Fee', value: formatCurrency(appointment.amount), icon: 'pi pi-money-bill' },
    ];
});

async function submitForm(complete: boolean): Promise<void> {
    const appointment = props.appointment;
    if (!appointment) {
        return;
    }

    form.processing = true;
    form.errors = {};

    try {
        const targetId = activeHistoryAppointmentId.value ?? appointment.id;
        const { data } = await http.patch(updateAction(targetId).url, {
            ...formData(),
            complete,
        });

        pushToast(toast, data.toast);
        dialogVisible.value = false;
        emit('saved', appointment.id);
    } catch (error) {
        form.errors = extractErrors(error);
    } finally {
        form.processing = false;
    }
}

function printReceipt(): void {
    if (!props.appointment) {
        return;
    }
    window.open(receipt(props.appointment.id).url, '_blank');
}

const toggleHold = async (appointment: AppointmentCard): Promise<void> => {
    try {
        const { data } = await http.patch(
            toggleHoldAction(appointment.id).url,
            {
                on_hold: !appointment.onHold,
            },
        );

        pushToast(toast, data.toast);
        emit('saved', appointment.id);
    } catch {
        pushToast(toast, {
            type: 'error',
            message: 'Could not update hold status. Please try again.',
        });
    }
};

function formatToken(sequence: number): string {
    return sequence.toString().padStart(2, '0');
}

function queueStatusLabel(status: AppointmentCard['queueStatus']): string {
    return {
        complete: 'Completed',
        on_hold: 'On Hold',
        pending: 'Waiting',
        running: 'Running Now',
    }[status];
}

function queueStatusSeverity(
    status: AppointmentCard['queueStatus'],
): 'success' | 'warn' | 'danger' | 'contrast' {
    switch (status) {
        case 'complete':
            return 'success';
        case 'on_hold':
            return 'danger';
        case 'pending':
            return 'warn';
        case 'running':
            return 'contrast';
    }
}
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        dismissable-mask
        header="Appointment Details"
        class="booking-detail-dialog"
        :style="{ width: '68rem', height: '48rem', maxHeight: '90vh' }"
        :breakpoints="{ '1024px': '92vw' }"
    >
        <template v-if="props.appointment">
            <div class="booking-detail-identity">
                <div class="booking-detail-identity__profile">
                    <Avatar
                        :label="props.appointment.patientName.charAt(0)"
                        shape="circle"
                        size="large"
                        class="booking-detail-avatar"
                    />
                    <div>
                        <div class="booking-token-line">
                            Token
                            {{
                                formatToken(
                                    props.appointment.appointmentSequence,
                                )
                            }}
                        </div>
                        <h3>{{ props.appointment.patientName }}</h3>
                        <p>
                            {{ props.appointment.appointmentType }}
                            · {{ props.appointment.session }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="booking-patient-summary">
                <div
                    v-for="item in patientSummary"
                    :key="item.label"
                    class="booking-patient-summary__item"
                >
                    <div class="booking-patient-summary__icon-container">
                        <i :class="item.icon"></i>
                    </div>
                    <div class="booking-patient-summary__content">
                        <span>{{ item.label }}</span>
                        <strong>{{ formatValue(item.value) }}</strong>
                    </div>
                </div>
            </div>

            <div v-if="props.appointment.history && props.appointment.history.length" class="booking-patient-history">
                <span class="history-section-title">Patient Appointments History</span>
                <div class="history-chips-container">
                    <button
                        v-for="apt in props.appointment.history"
                        :key="apt.id"
                        type="button"
                        class="history-chip"
                        :class="{
                            'is-active': activeHistoryAppointmentId === apt.id,
                        }"
                        @click="activeHistoryAppointmentId = apt.id"
                    >
                        <span class="history-chip__date">{{ apt.appointmentDateLabel }}</span>
                        <span class="history-chip__meta">
                            {{ apt.session }} · {{ apt.appointmentType }}
                            <i v-if="apt.status?.toLowerCase() === 'complete'" class="pi pi-check-circle history-chip__completed-icon ml-1"></i>
                        </span>
                    </button>
                </div>
            </div>

            <Divider />

            <div class="booking-modal-workspace">
                <aside class="booking-modal-sidebar">
                    <button
                        v-for="(section, index) in FORM_SECTIONS"
                        :key="section.title"
                        type="button"
                        class="booking-modal-tab"
                        :class="{ 'is-active': activeFormSectionIndex === index }"
                        @click="activeFormSectionIndex = index"
                    >
                        <i :class="section.icon"></i>
                        <span>{{ section.title }}</span>
                        <span v-if="sectionHasData(section)" class="booking-modal-tab__indicator"></span>
                    </button>
                </aside>

                <div class="booking-modal-form-pane">
                    <form class="booking-modal-form" @submit.prevent="submitForm(true)">
                        <div
                            v-for="(section, index) in FORM_SECTIONS"
                            :key="section.title"
                            v-show="activeFormSectionIndex === index"
                            class="booking-form-card"
                        >
                            <header class="booking-form-card__header">
                                <h4>{{ section.title }}</h4>
                            </header>

                            <div class="booking-form-grid">
                                <div
                                    v-for="field in section.fields"
                                    :key="field.key"
                                    class="booking-field"
                                    :class="{
                                        'booking-field--wide':
                                            field.wide || field.type === 'textarea',
                                    }"
                                >
                                    <label :for="`field-${field.key}`">
                                        {{ field.label }}
                                    </label>

                                    <Textarea
                                        v-if="field.type === 'textarea'"
                                        :id="`field-${field.key}`"
                                        v-model="form[field.key] as string"
                                        rows="4"
                                        auto-resize
                                        :placeholder="field.label"
                                    />
                                    <AutoComplete
                                        v-else-if="field.type === 'chips'"
                                        :id="`field-${field.key}`"
                                        v-model="medicinesChips"
                                        :suggestions="filteredMedicines"
                                        multiple
                                        fluid
                                        forceSelection
                                        option-label="label"
                                        dataKey="id"
                                        placeholder="Search and select medicines..."
                                        class="w-full"
                                        @complete="searchMedicines"
                                    />
                                    <DatePicker
                                        v-else-if="field.type === 'date'"
                                        :input-id="`field-${field.key}`"
                                        v-model="followUpModel"
                                        date-format="dd M yy"
                                        show-icon
                                        icon-display="input"
                                    />
                                    <InputNumber
                                        v-else-if="field.type === 'number'"
                                        :input-id="`field-${field.key}`"
                                        v-model="form.days_prescription"
                                        :min="0"
                                        :max="3650"
                                        show-buttons
                                    />
                                    <InputText
                                        v-else
                                        :id="`field-${field.key}`"
                                        v-model="form[field.key] as string"
                                        :placeholder="field.label"
                                    />

                                    <small
                                        v-if="form.errors[field.key]"
                                        class="booking-field__error"
                                    >
                                        {{ form.errors[field.key] }}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        <div v-else class="booking-empty-state booking-empty-state--soft">
            <i class="pi pi-eye"></i>
            <h3>Pick an appointment to inspect</h3>
            <p>
                Use an action button to open the patient’s full appointment information here.
            </p>
        </div>

        <template v-if="props.appointment" #footer>
            <div class="booking-form-footer">
                <span v-if="isDirty" class="booking-form-footer__hint">
                    You have unsaved changes
                </span>

                <div class="booking-form-footer__actions">
                    <Button
                        outlined
                        icon="pi pi-print"
                        label="Print Receipt"
                        severity="secondary"
                        @click="printReceipt"
                    />
                    <Button
                        outlined
                        icon="pi pi-save"
                        label="Save changes"
                        :loading="form.processing"
                        @click="submitForm(false)"
                    />
                    <Button
                        icon="pi pi-check"
                        label="Mark Complete"
                        severity="success"
                        :loading="form.processing"
                        @click="submitForm(true)"
                    />
                </div>
            </div>
        </template>
    </Dialog>
</template>

<style scoped>

.booking-detail-dialog :deep(.p-dialog-content) {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    overflow: hidden !important;
    padding-bottom: 0.5rem;
}

.booking-detail-identity {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.booking-token-line {
    color: var(--text-color-secondary);
    font-size: 0.83rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.booking-detail-identity__profile {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.booking-detail-identity__profile h3 {
    margin: 0.15rem 0 0.1rem;
    font-size: 1.55rem;
    font-weight: 700;
    line-height: 1.2;
}

.booking-detail-identity__profile p {
    margin: 0;
    color: var(--text-color-secondary);
    font-size: 0.9rem;
}

.booking-detail-avatar {
    background: var(--p-primary-500) !important;
    color: white !important;
    font-weight: 700;
    width: 3rem !important;
    height: 3rem !important;
    flex-shrink: 0 !important;
}

.booking-detail-identity__meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.booking-detail-identity__meta :deep(.p-button) {
    width: auto !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}

.booking-patient-summary {
    display: grid;
    gap: 1rem 1.25rem;
    grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr));
    margin-top: 1rem;
    padding: 1.25rem;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
}

.booking-patient-summary__item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.booking-patient-summary__icon-container {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    background: var(--surface-100);
    border-radius: 6px;
    color: var(--p-primary-500);
    flex-shrink: 0;
}

.booking-patient-summary__icon-container i {
    font-size: 1rem;
}

.booking-patient-summary__content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.booking-patient-summary__content span {
    color: var(--text-color-secondary);
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}

.booking-patient-summary__content strong {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-color);
    word-break: break-word;
}

.booking-patient-history {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 1rem;
}

.history-section-title {
    color: var(--text-color-secondary);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-weight: 600;
}

.history-chips-container {
    display: flex;
    overflow-x: auto;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
}

.history-chip {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.15rem;
    padding: 0.5rem 0.85rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-50);
    color: var(--text-color);
    cursor: pointer;
    font-family: inherit;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.history-chip:hover {
    border-color: var(--p-primary-400);
    background: var(--surface-100);
}

.history-chip.is-active {
    border-color: var(--p-primary-500);
    background: color-mix(
        in srgb,
        var(--p-primary-500) 12%,
        var(--surface-card)
    );
    color: var(--p-primary-700);
    font-weight: 600;
}

.history-chip__date {
    font-size: 0.85rem;
    font-weight: 700;
}

.history-chip__meta {
    font-size: 0.72rem;
    color: var(--text-color-secondary);
    display: inline-flex;
    align-items: center;
}

.history-chip.is-active .history-chip__meta {
    color: var(--p-primary-600);
}

.history-chip__completed-icon {
    color: var(--p-green-500);
    margin-left: 0.25rem;
}

/* Modal Split Navigation Workspace */
.booking-modal-workspace {
    display: grid;
    grid-template-columns: 16rem minmax(0, 1fr);
    gap: 1.5rem;
    margin-top: 1.25rem;
    align-items: stretch;
}

.booking-modal-sidebar {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    border-right: 1px solid var(--surface-border);
    padding-right: 1.25rem;
}

.booking-modal-tab {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    padding: 0.8rem 1rem;
    border: 1px solid transparent;
    border-radius: 6px;
    background: transparent;
    color: var(--text-color-secondary);
    font-family: inherit;
    font-size: 0.92rem;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
    position: relative;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.booking-modal-tab:hover {
    background: var(--surface-hover);
    color: var(--p-primary-500);
    padding-left: 1.15rem;
}

.booking-modal-tab.is-active {
    background: color-mix(
        in srgb,
        var(--p-primary-500) 8%,
        var(--surface-card)
    );
    border-color: var(--surface-border);
    border-left: 4px solid var(--p-primary-500);
    color: var(--p-primary-700);
    padding-left: 1.25rem;
}

.booking-modal-tab i {
    font-size: 1.1rem;
    color: var(--text-color-secondary);
}

.booking-modal-tab.is-active i {
    color: var(--p-primary-600);
}

.booking-modal-tab__indicator {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--p-green-500);
}

.booking-modal-form-pane {
    flex: 1;
    min-width: 0;
    padding-left: 0.5rem;
}

.booking-modal-form-pane .booking-form-card {
    height: 100%;
    border: none;
    padding: 0;
    background: transparent;
}

.booking-modal-form-pane .booking-form-card__header {
    border-bottom: 1px solid var(--surface-border);
    padding-bottom: 0.75rem;
    margin-bottom: 1.25rem;
}

.booking-modal-form-pane .booking-form-card__header h4 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
}

/* Form inputs inside the dialog */
.booking-field {
    display: grid;
    gap: 0.35rem;
    align-content: start;
}

.booking-field--wide {
    grid-column: 1 / -1;
}

.booking-field label {
    color: var(--text-color);
    font-size: 0.85rem;
    font-weight: 600;
}

.booking-form-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.booking-modal-workspace :deep(.p-inputtext),
.booking-modal-workspace :deep(.p-textarea),
.booking-modal-workspace :deep(.p-datepicker),
.booking-modal-workspace :deep(.p-inputnumber) {
    width: 100%;
}

.booking-modal-workspace :deep(.p-textarea) {
    resize: vertical;
}

.booking-field__error {
    color: var(--p-red-500);
    font-size: 0.78rem;
}

.booking-form-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
    flex-wrap: wrap;
}

.booking-form-footer__hint {
    color: var(--p-orange-600);
    font-size: 0.85rem;
    font-weight: 600;
}

.booking-form-footer__actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-left: auto;
}

.booking-form-footer__actions :deep(.p-button) {
    width: auto !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}

.booking-empty-state {
    display: grid;
    place-items: center;
    gap: 0.75rem;
    padding: 2.2rem 1.2rem;
    text-align: center;
}

.booking-empty-state--soft {
    min-height: 15rem;
}

.booking-empty-state i {
    font-size: 1.5rem;
    color: var(--text-color-secondary);
}

.booking-empty-state h3 {
    margin: 0;
}

@media (max-width: 1024px) {
    .booking-modal-workspace {
        grid-template-columns: 1fr;
        height: auto;
        max-height: none;
        min-height: auto;
    }
    
    .booking-modal-sidebar {
        flex-direction: row;
        overflow-x: auto;
        border-right: none;
        border-bottom: 1px solid var(--surface-border);
        padding-right: 0;
        padding-bottom: 0.75rem;
        gap: 0.5rem;
        white-space: nowrap;
    }
    
    .booking-modal-tab {
        width: auto;
        padding: 0.6rem 0.9rem;
    }
    
    .booking-modal-tab.is-active {
        border-right: none;
        border-bottom: 3px solid var(--p-primary-500);
    }
}

@media (max-width: 640px) {
    .booking-form-grid {
        grid-template-columns: 1fr;
    }

    .booking-detail-identity,
    .booking-form-footer__actions {
        flex-direction: column;
        align-items: stretch;
    }

    .booking-form-footer__actions {
        gap: 0.5rem;
    }
    
    .booking-form-footer__actions :deep(.p-button) {
        width: 100% !important;
    }
    
    .booking-detail-identity__profile {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Modal dialog button hover animations */
.booking-detail-dialog :deep(.p-button) {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.booking-detail-dialog :deep(.p-button:not(:disabled)):hover {
    transform: translateY(-1px);
}

.booking-form-footer__actions :deep(.p-button-success:not(:disabled)):hover {
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.24);
}

.booking-form-footer__actions :deep(.p-button-outlined:not(:disabled)):hover {
    box-shadow: 0 4px 12px rgba(15, 118, 110, 0.08);
}

/* Scroll confinement inside Details Modal on Desktop */
@media (min-width: 1025px) {
    .booking-modal-workspace {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        overflow: hidden !important;
    }

    .booking-modal-sidebar {
        overflow-y: auto !important;
        height: 100% !important;
    }

    .booking-modal-form-pane {
        overflow-y: auto !important;
        height: 100% !important;
        padding-right: 0.75rem !important;
    }
}

/* Rounded corners for AutoComplete & Chips multiple selections */
:deep(.p-autocomplete),
:deep(.p-autocomplete-multiple-container),
:deep(.p-autocomplete-token),
:deep(.p-chips),
:deep(.p-chips-multiple-container),
:deep(.p-chips-token) {
    border-radius: 6px !important;
}
</style>
