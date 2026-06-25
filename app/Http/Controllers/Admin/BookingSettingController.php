<?php

namespace App\Http\Controllers\Admin;

use App\Events\QueueUpdated;
use App\Http\Controllers\Controller;
use App\Models\BookingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingSettingController extends Controller
{
    /**
     * Display the booking settings page.
     */
    public function index(): Response
    {
        $settings = BookingSetting::current();

        return Inertia::render('admin/BookingSettings', [
            'settings' => [
                'morning_slot_capacity' => $settings->morning_slot_capacity,
                'evening_slot_capacity' => $settings->evening_slot_capacity,
                'booking_enabled' => $settings->booking_enabled,
                'booking_open_days' => $settings->booking_open_days,
                'morning_opening_time' => $settings->morning_opening_time ? substr($settings->morning_opening_time, 0, 5) : null,
                'morning_closing_time' => $settings->morning_closing_time ? substr($settings->morning_closing_time, 0, 5) : null,
                'evening_opening_time' => $settings->evening_opening_time ? substr($settings->evening_opening_time, 0, 5) : null,
                'evening_closing_time' => $settings->evening_closing_time ? substr($settings->evening_closing_time, 0, 5) : null,
                'clinic_closures' => $settings->clinic_closures ?? [],
                'closed_days' => $settings->closed_days ?? [],
                'notice_enabled' => $settings->notice_enabled,
                'notice_text' => $settings->notice_text,
            ],
        ]);
    }

    /**
     * Update the booking settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->all();
        foreach (['morning_opening_time', 'morning_closing_time', 'evening_opening_time', 'evening_closing_time'] as $field) {
            if (isset($data[$field]) && (trim((string) $data[$field]) === '' || $data[$field] === '')) {
                $data[$field] = null;
            }
        }

        $validated = validator($data, [
            'morning_slot_capacity' => ['required', 'integer', 'min:0'],
            'evening_slot_capacity' => ['required', 'integer', 'min:0'],
            'booking_enabled' => ['required', 'boolean'],
            'booking_open_days' => ['required', 'integer', 'min:1'],
            'morning_opening_time' => ['nullable', 'date_format:H:i'],
            'morning_closing_time' => ['nullable', 'date_format:H:i'],
            'evening_opening_time' => ['nullable', 'date_format:H:i'],
            'evening_closing_time' => ['nullable', 'date_format:H:i'],
            'clinic_closures' => ['nullable', 'array'],
            'clinic_closures.*.date' => ['required', 'date_format:Y-m-d'],
            'clinic_closures.*.slot' => ['required', 'array'],
            'clinic_closures.*.slot.*' => ['required', 'string', 'in:Morning,Evening,morning,evening'],
            'closed_days' => ['nullable', 'array'],
            'closed_days.*' => ['required', 'integer', 'min:0', 'max:6'],
            'notice_enabled' => ['required', 'boolean'],
            'notice_text' => ['nullable', 'string'],
        ])->validate();

        $settings = BookingSetting::current();
        $settings->fill($validated);
        $settings->save();

        try {
            broadcast(new QueueUpdated('All'));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => 'Booking settings updated successfully.',
        ]);
    }
}
