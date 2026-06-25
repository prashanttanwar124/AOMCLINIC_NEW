<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { store } from '@/actions/App/Http/Controllers/Patient/Auth/RegisteredPatientController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { login, liveStatus } from '@/routes/patient';

defineOptions({
    layout: {
        title: 'Patient registration',
        description: 'Create your patient portal account',
    },
});

const genderOptions = [
    { label: 'Male', value: 'male' },
    { label: 'Female', value: 'female' },
    { label: 'Other', value: 'other' },
    { label: 'Prefer not to say', value: 'prefer_not_to_say' },
];
</script>

<template>
    <Head title="Patient Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="auth-form"
    >
        <div class="form-grid">
            <section class="page-card" style="padding: 1rem">
                <div class="section-heading">
                    <h2 style="font-size: 1.1rem">Basic details</h2>
                    <p>Tell us who you are so we can create your patient portal.</p>
                </div>

                <div
                    style="
                        display: grid;
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                        gap: 1rem;
                        margin-top: 1rem;
                    "
                    class="patient-register-split"
                >
                    <div class="field">
                        <label for="name">Full name</label>
                        <InputText
                            id="name"
                            type="text"
                            name="name"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="Full name"
                            fluid
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="field">
                        <label for="email">Email address</label>
                        <InputText
                            id="email"
                            type="email"
                            name="email"
                            required
                            autocomplete="email"
                            placeholder="patient@example.com"
                            fluid
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="field">
                        <label for="phone">Phone number</label>
                        <InputText
                            id="phone"
                            type="text"
                            name="phone"
                            required
                            autocomplete="tel"
                            placeholder="9876543210"
                            fluid
                        />
                        <InputError :message="errors.phone" />
                    </div>

                    <div class="field">
                        <label for="patient_number">Patient number</label>
                        <InputText
                            id="patient_number"
                            type="text"
                            name="patient_number"
                            placeholder="Optional reference"
                            fluid
                        />
                        <InputError :message="errors.patient_number" />
                    </div>
                </div>
            </section>

            <section class="page-card" style="padding: 1rem">
                <div class="section-heading">
                    <h2 style="font-size: 1.1rem">Personal profile</h2>
                    <p>These details help your care team keep your record complete.</p>
                </div>

                <div
                    style="
                        display: grid;
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                        gap: 1rem;
                        margin-top: 1rem;
                    "
                    class="patient-register-split"
                >
                    <div class="field">
                        <label for="date_of_birth">Date of birth</label>
                        <InputText
                            id="date_of_birth"
                            type="date"
                            name="date_of_birth"
                            required
                            fluid
                        />
                        <InputError :message="errors.date_of_birth" />
                    </div>

                    <div class="field">
                        <label for="gender">Gender</label>
                        <Select
                            id="gender"
                            name="gender"
                            :options="genderOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Select gender"
                            checkmark
                            fluid
                        />
                        <InputError :message="errors.gender" />
                    </div>

                    <div class="field">
                        <label for="city">City</label>
                        <InputText
                            id="city"
                            type="text"
                            name="city"
                            placeholder="City"
                            fluid
                        />
                        <InputError :message="errors.city" />
                    </div>

                    <div class="field" style="grid-column: 1 / -1">
                        <label for="address">Address</label>
                        <Textarea
                            id="address"
                            name="address"
                            rows="3"
                            placeholder="Street address"
                            auto-resize
                            fluid
                        />
                        <InputError :message="errors.address" />
                    </div>
                </div>
            </section>

            <div class="field">
                <label for="password">Password</label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm password"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button type="submit" label="Create patient account" :loading="processing" fluid />
        </div>

        <div class="form-footer" style="display: flex; flex-direction: column; gap: 0.5rem; align-items: center;">
            <div>
                Already registered?
                <TextLink :href="login()">Log in</TextLink>
            </div>
            <div style="font-size: 0.85rem; color: #64748b;">
                Or check the <TextLink :href="liveStatus()">Live Queue Status</TextLink> without logging in.
            </div>
        </div>
    </Form>
</template>
