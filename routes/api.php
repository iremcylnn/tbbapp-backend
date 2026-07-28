<?php

use App\Http\Controllers\AdminActionLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\MapBootstrapController;
use App\Http\Controllers\NewPlaceRequestController;
use Illuminate\Support\Facades\Route;

// Routes here are automatically prefixed with /api (see bootstrap/app.php).

// Map bootstrap — public read, version-key ETag. No cache.headers:etag
// middleware: it would recompute a content-hash ETag and overwrite ours.
Route::get('/map/bootstrap', MapBootstrapController::class);

// Citizen auth. throttle:auth = 20 req / 15 min per IP (see AppServiceProvider).
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Citizen submissions. throttle:public-write = 20 / 15 min per user —
    // a SEPARATE counter from throttle:auth, on purpose.
    Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:public-write');
    Route::get('/feedback/mine', [FeedbackController::class, 'mine']);
    Route::post('/new-place-requests', [NewPlaceRequestController::class, 'store'])->middleware('throttle:public-write');
    Route::get('/new-place-requests/mine', [NewPlaceRequestController::class, 'mine']);
});

// Admin endpoints — shared x-admin-key header (see RequireAdminKey).
Route::middleware('admin.key')->group(function () {
    Route::get('/feedback', [FeedbackController::class, 'index']);
    Route::get('/new-place-requests', [NewPlaceRequestController::class, 'index']);
    Route::patch('/new-place-requests/{id}', [NewPlaceRequestController::class, 'decide'])->whereNumber('id');
    Route::get('/admin/action-logs', AdminActionLogController::class);
});
