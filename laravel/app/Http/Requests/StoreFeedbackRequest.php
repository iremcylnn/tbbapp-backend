<?php

namespace App\Http\Requests;

use App\Models\FeedbackSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Spec contract: {kind: complaint|request, description, lat?, long?,
 * location_id?} — the guide's field naming (lat/long/location_id), not the
 * old server's latitude/longitude/placeId.
 */
class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(FeedbackSubmission::KINDS)],
            'description' => ['required', 'string', 'max:2000'],
            // Deliberately `exists:` rather than the LocationSource-derived
            // Rule::in the two lookup ids use (see DecideNewPlaceRequestRequest):
            // places is unbounded — thousands of rows after the OSM imports —
            // and building an in: list of them on every feedback POST is the
            // query-everything cost the whole ETag design avoids. One indexed
            // EXISTS is the right tool at that cardinality.
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'long' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'kind.required' => 'kind zorunludur.',
            'kind.in' => 'kind şunlardan biri olmalıdır: complaint, request.',
            'description.required' => 'Açıklama zorunludur.',
            'description.max' => 'Açıklama 2000 karakteri geçemez.',
            'location_id.exists' => 'Geçersiz location_id.',
            'lat.between' => 'lat -90 ile 90 arasında olmalıdır.',
            'long.between' => 'long -180 ile 180 arasında olmalıdır.',
        ];
    }
}
