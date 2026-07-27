<?php

namespace App\Http\Requests;

use App\Map\MapBootstrapService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Spec contract: {title, category_id?, description, lat, long} — unlike
 * feedback, coordinates are REQUIRED here (a place proposal without a
 * location is meaningless).
 *
 * category_id is checked against the configured LocationSource (see
 * DecideNewPlaceRequestRequest for the full reasoning): the citizen picks
 * from the categories the bootstrap payload gave them, so that same list is
 * what the server accepts back.
 */
class StoreNewPlaceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer', Rule::in(array_column(app(MapBootstrapService::class)->categories(), 'id'))],
            'description' => ['required', 'string', 'max:2000'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'long' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'title zorunludur.',
            'title.max' => 'title 200 karakteri geçemez.',
            'category_id.in' => 'Geçersiz category_id.',
            'description.required' => 'Açıklama zorunludur.',
            'description.max' => 'Açıklama 2000 karakteri geçemez.',
            'lat.required' => 'lat zorunludur.',
            'lat.between' => 'lat -90 ile 90 arasında olmalıdır.',
            'long.required' => 'long zorunludur.',
            'long.between' => 'long -180 ile 180 arasında olmalıdır.',
        ];
    }
}
