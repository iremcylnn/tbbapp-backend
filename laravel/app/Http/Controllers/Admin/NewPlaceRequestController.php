<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DecideNewPlaceRequestRequest;
use App\Models\District;
use App\Models\LocationCategory;
use App\Models\NewPlaceRequest;
use App\NewPlaces\NewPlaceApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Blade counterpart to the JSON API's NewPlaceRequestController — same service, same rules. */
class NewPlaceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(
            ['status' => ['sometimes', Rule::in(NewPlaceRequest::STATUSES)]],
        );

        $requests = NewPlaceRequest::query()
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->latest()
            ->with('user')
            ->get();

        return view('admin.new-place-requests.index', [
            'requests' => $requests,
            'districts' => District::active()->get(),
            'categories' => LocationCategory::active()->get(),
            'currentStatus' => $filters['status'] ?? null,
        ]);
    }

    public function decide(
        DecideNewPlaceRequestRequest $request,
        NewPlaceApprovalService $service,
        int $id,
    ): RedirectResponse {
        $submission = NewPlaceRequest::find($id);
        if ($submission === null) {
            return back()->with('status', 'Öneri bulunamadı.');
        }

        $data = $request->validated();

        if ($data['status'] === 'rejected') {
            $updated = $service->reject($submission, (string) $request->ip());

            return back()->with('status', $updated !== null
                ? 'Öneri reddedildi.'
                : 'Bu öneri zaten karara bağlanmış.');
        }

        $categoryId = $data['category_id'] ?? $submission->category_id;
        if ($categoryId === null) {
            return back()->withErrors(['category_id' => 'Onay için kategori zorunludur.']);
        }

        $result = $service->approve($submission, (int) $data['district_id'], $categoryId, (string) $request->ip());

        return back()->with('status', $result !== null
            ? 'Öneri onaylandı, harita üzerinde yayınlandı.'
            : 'Bu öneri zaten karara bağlanmış.');
    }
}
