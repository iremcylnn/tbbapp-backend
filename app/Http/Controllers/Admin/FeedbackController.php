<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedbackSubmission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Read-only Blade counterpart to the JSON API's FeedbackController::index. */
class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(
            ['kind' => ['sometimes', Rule::in(FeedbackSubmission::KINDS)]],
        );

        $feedback = FeedbackSubmission::query()
            ->when(isset($filters['kind']), fn ($q) => $q->where('kind', $filters['kind']))
            ->latest()
            ->with('user')
            ->get();

        return view('admin.feedback.index', [
            'feedback' => $feedback,
            'currentKind' => $filters['kind'] ?? null,
        ]);
    }
}
