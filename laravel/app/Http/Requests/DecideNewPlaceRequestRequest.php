<?php

namespace App\Http\Requests;

use App\Map\MapBootstrapService;
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
 *
 * The two ids are validated against the configured LocationSource, not against
 * the tables directly. Two reasons: the admin panel builds its dropdowns from
 * that same source, so a form can never offer an option its own validator
 * rejects; and it holds under MAP_SOURCE=mock, where `exists:districts,id`
 * would fail on ids the map API is actively serving. It is also stricter in a
 * useful way — the source yields only ACTIVE rows, so a disabled district or
 * category can no longer be assigned to a freshly published location.
 */
class DecideNewPlaceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $map = app(MapBootstrapService::class);

        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'district_id' => ['required_if:status,approved', 'integer', Rule::in(array_column($map->districts(), 'id'))],
            'category_id' => ['nullable', 'integer', Rule::in(array_column($map->categories(), 'id'))],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'status zorunludur.',
            'status.in' => "status 'approved' veya 'rejected' olmalıdır.",
            'district_id.required_if' => 'Onay için district_id zorunludur.',
            'district_id.in' => 'Geçersiz district_id.',
            'category_id.in' => 'Geçersiz category_id.',
        ];
    }
}
