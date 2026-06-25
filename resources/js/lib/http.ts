import axios from 'axios';
import type { AxiosError } from 'axios';
import type { ToastServiceMethods } from 'primevue/toastservice';
import type { FlashToast } from '@/types/ui';

/**
 * Shared axios client for authenticated admin/patient panels.
 *
 * The app is served through Laravel's `web` middleware, so the `XSRF-TOKEN`
 * cookie is always present — axios mirrors it back as the `X-XSRF-TOKEN`
 * header. `Accept: application/json` makes Laravel answer validation
 * failures with a 422 JSON body instead of an Inertia redirect.
 */
const http = axios.create({
    withCredentials: true,
    withXSRFToken: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export type ValidationErrors = Record<string, string>;

type ErrorResponse = {
    errors?: Record<string, string[] | string>;
    message?: string;
};

/**
 * Flatten a Laravel 422 error bag down to the first message per field.
 */
export function extractErrors(error: unknown): ValidationErrors {
    const bag = (error as AxiosError<ErrorResponse>).response?.data?.errors;

    if (!bag) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(bag).map(([field, messages]) => [
            field,
            Array.isArray(messages) ? messages[0] : String(messages),
        ]),
    );
}

const SEVERITY: Record<FlashToast['type'], string> = {
    success: 'success',
    info: 'info',
    warning: 'warn',
    error: 'error',
};

/**
 * Surface a toast payload returned by an axios JSON response.
 */
export function pushToast(
    toast: ToastServiceMethods,
    payload: FlashToast | undefined | null,
): void {
    if (!payload) {
        return;
    }

    toast.add({
        severity: SEVERITY[payload.type] ?? 'info',
        summary: payload.type.charAt(0).toUpperCase() + payload.type.slice(1),
        detail: payload.message,
        life: 3500,
    });
}

export default http;
