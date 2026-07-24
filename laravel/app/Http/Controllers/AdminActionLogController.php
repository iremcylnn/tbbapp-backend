<?php

namespace App\Http\Controllers;

use App\Models\AdminActionLog;
use Illuminate\Http\JsonResponse;

class AdminActionLogController extends Controller
{
    /** GET /api/admin/action-logs — append-only audit trail, newest first. */
    public function __invoke(): JsonResponse
    {
        return response()->json(
            AdminActionLog::query()->latest()->orderByDesc('id')->get()
        );
    }
}
