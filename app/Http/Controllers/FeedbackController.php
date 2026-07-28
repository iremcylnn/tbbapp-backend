<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Models\FeedbackSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    /** POST /api/feedback — logged-in citizen files a complaint/request. */
    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        $data = $request->validated();

        $feedback = $request->user()->feedbackSubmissions()->create([
            'kind' => $data['kind'],
            'description' => trim($data['description']),
            'location_id' => $data['location_id'] ?? null,
            'lat' => $data['lat'] ?? null,
            'long' => $data['long'] ?? null,
        ]);

        return response()->json($feedback, 201);
    }

    /** GET /api/feedback/mine — the citizen's own submission history. */
    public function mine(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->feedbackSubmissions()->latest()->get()
        );
    }

    /** GET /api/feedback?kind=complaint — admin listing, newest first. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(
            ['kind' => ['sometimes', Rule::in(FeedbackSubmission::KINDS)]],
            ['kind.in' => 'kind şunlardan biri olmalıdır: complaint, request.'],
        );

        $feedback = FeedbackSubmission::query()
            ->when(isset($filters['kind']), fn ($q) => $q->where('kind', $filters['kind']))
            ->latest()
            ->with('user')
            ->get()
            ->map(fn (FeedbackSubmission $f) => [
                ...$f->withoutRelations()->toArray(),
                'user' => [
                    'firstName' => $f->user->first_name,
                    'lastName' => $f->user->last_name,
                    'email' => $f->user->email,
                ],
            ]);

        return response()->json($feedback);
    }
}
