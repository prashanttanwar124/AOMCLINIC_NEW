# AOM Clinic Project Handoff

## Stack

- Laravel 13
- Inertia.js v3
- Vue 3
- PrimeVue
- Sakai layout shell via `resources/css/app.scss`

## UI Rules

- Keep patient portal aligned with Sakai-style shell already in project.
- Do not add rounded corners for patient portal UI.
- `resources/css/app.scss` already forces square corners for many shared shell elements under `.layout-wrapper` and `.patient-layout`.
- Avoid page-level CSS that fights shell style with extra gradients, pill shapes, or custom rounded cards unless user asks.

## Booking Page Rules

File: `resources/js/pages/patient/Booking.vue`

- Patient booking page must stay square/flat, not soft-rounded.
- Top booking progress bar should exist.
- Token selection is by groups of 5, not exact token click.
- Example groups: `1-5`, `6-10`, `11-15`.
- When patient picks group, frontend sends one actual token from that group using `appointment_number`.
- Current behavior picks first free token inside chosen group.
- Summary should show assigned token and chosen group.

## Backend Booking Behavior

Files:

- `app/Http/Controllers/Patient/PatientAppointmentController.php`
- `app/Http/Requests/Patient/Appointments/StorePatientAppointmentRequest.php`

Rules:

- Backend validates exact `appointment_number`.
- Available tokens come from backend as `availableTokens` per session.
- Frontend groups tokens visually, but backend still stores exact token number.
- Concurrency protection exists in transaction with recheck before create.

## Before Changing Booking Flow

1. Use Laravel Boost `search_docs`.
2. Check `Booking.vue` and backend request/controller together.
3. Run targeted test:
   `php artisan test --compact tests/Feature/PatientAppointmentBookingTest.php`
4. If PHP changed, run:
   `vendor/bin/pint --dirty --format agent`

## Known Preference From User

- User does not want rounded-corner styling on this patient booking UI.
- User wants Sakai-kind theme feel.
- User wants token groups of 5.
