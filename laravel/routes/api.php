<?php

use App\Http\Controllers\MapBootstrapController;
use Illuminate\Support\Facades\Route;

// Routes here are automatically prefixed with /api (see bootstrap/app.php).
// No cache.headers:etag middleware on this route — it would recompute a
// content-hash ETag over the response body and overwrite our version key.
Route::get('/map/bootstrap', MapBootstrapController::class);
