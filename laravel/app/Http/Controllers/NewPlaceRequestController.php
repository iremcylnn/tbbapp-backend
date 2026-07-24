<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideNewPlaceRequestRequest;
use App\Http\Requests\StoreNewPlaceRequestRequest;
use App\Models\NewPlaceRequest;
use App\NewPlaces\NewPlaceApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NewPlaceRequestController extends Controller
{
    /** POST /api/new-place-requests — citizen proposes a new place (starts pending). */
    public function store(StoreNewPlaceRequestRequest $request): JsonResponse
    {
        $data = $request->validated();

        $submission = $request->user()->newPlaceRequests()->create([
            'title' => trim($data['title']),
            'category_id' => $data['category_id'] ?? null,
            'description' => trim($data['description']),
            'lat' => $data['lat'],
            'long' => $data['long'],
        ]);

        // refresh(): the status column is filled by a DATABASE default
        // ('pending'), which the in-memory model can't know until re-read.
        return response()->json($submission->refresh(), 201);
    }

    /** GET /api/new-place-requests/mine — the citizen's own proposals. */
    public function mine(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->newPlaceRequests()->latest()->get()
        );
    }

    /** GET /api/new-place-requests?status=pending — admin listing, newest first. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(
            ['status' => ['sometimes', Rule::in(NewPlaceRequest::STATUSES)]],
            ['status.in' => 'status şunlardan biri olmalıdır: pending, approved, rejected.'],
        );

        $requests = NewPlaceRequest::query()
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->latest()
            ->with('user')
            ->get()
            ->map(fn (NewPlaceRequest $r) => [
                ...$r->withoutRelations()->toArray(),
                'user' => [
                    'firstName' => $r->user->first_name,
                    'lastName' => $r->user->last_name,
                    'email' => $r->user->email,
                ],
            ]);

        return response()->json($requests);
    }

    /** PATCH /api/new-place-requests/{id} — admin approves (creates a location) or rejects. */
    public function decide(
        DecideNewPlaceRequestRequest $request,
        NewPlaceApprovalService $service,
        int $id,
    ): JsonResponse {
        $submission = NewPlaceRequest::find($id);
        if ($submission === null) {
            return response()->json(['message' => 'Öneri bulunamadı'], 404);
        }

        $data = $request->validated();

        if ($data['status'] === 'rejected') {
            $updated = $service->reject($submission, (string) $request->ip());

            return $updated !== null
                ? response()->json($updated)
                : response()->json(['message' => 'Bu öneri zaten karara bağlanmış'], 409);
        }

        // Category comes from the submission unless the admin overrides it;
        // one of the two must exist to build a real locations row.
        $categoryId = $data['category_id'] ?? $submission->category_id;
        if ($categoryId === null) {
            throw ValidationException::withMessages([
                'category_id' => 'Onay için category_id zorunludur (öneride yoksa istekle gönderin).',
            ]);
        }

        $result = $service->approve($submission, (int) $data['district_id'], $categoryId, (string) $request->ip());

        return $result !== null
            ? response()->json($result)
            : response()->json(['message' => 'Bu öneri zaten karara bağlanmış'], 409);
    }
}
