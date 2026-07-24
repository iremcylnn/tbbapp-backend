<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin decision body: {status: approved|rejected, district_id?, category_id?}.
 *
 * district_id is required only on approval — the citizen form doesn't collect
 * a district, but a real locations row can't exist without one, so the admin
 * supplies it at decision time. category_id here OVERRIDES the submission's
 * own category; the "one of the two must exist" rule lives in the controller
 * because it needs the submission row.
 */
class DecideNewPlaceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'district_id' => ['required_if:status,approved', 'integer', 'exists:districts,id'],
            'category_id' => ['nullable', 'integer', 'exists:locations_category,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'status zorunludur.',
            'status.in' => "status 'approved' veya 'rejected' olmalıdır.",
            'district_id.required_if' => 'Onay için district_id zorunludur.',
            'district_id.exists' => 'Geçersiz district_id.',
            'category_id.exists' => 'Geçersiz category_id.',
        ];
    }
}
