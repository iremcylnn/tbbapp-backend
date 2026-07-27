<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use Illuminate\View\View;

/** Read-only Blade counterpart to the JSON API's AdminActionLogController. */
class ActionLogController extends Controller
{
    public function index(): View
    {
        return view('admin.action-logs.index', [
            'logs' => AdminActionLog::query()->latest()->orderByDesc('id')->get(),
        ]);
    }
}
