<?php

use App\Http\Controllers\Admin\ActionLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\FeedbackController;
use App\Http\Controllers\Admin\NewPlaceRequestController;
use Illuminate\Support\Facades\Route;

// All product routes live in routes/api.php (the mobile app's JSON contract).
// This file holds only the internal /admin Blade panel — see CLAUDE.md.

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:auth')->name('login.store');

    Route::middleware('admin.session')->group(function () {
        Route::redirect('/', '/admin/new-place-requests');
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

        Route::get('/new-place-requests', [NewPlaceRequestController::class, 'index'])->name('new-place-requests.index');
        Route::patch('/new-place-requests/{id}', [NewPlaceRequestController::class, 'decide'])->whereNumber('id')->name('new-place-requests.decide');

        Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
        Route::get('/action-logs', [ActionLogController::class, 'index'])->name('action-logs.index');
    });
});
