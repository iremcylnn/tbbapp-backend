<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sanctum tokens are issued with a 30-day expiry (AuthController). Expiry is
// enforced at authentication time, so this changes no behaviour — it just
// stops dead rows accumulating in personal_access_tokens forever. --hours=24
// keeps a day's grace after expiry for debugging "why was I logged out".
// Runs only where a scheduler is actually running (`php artisan schedule:work`
// in dev, a cron entry calling `schedule:run` on a server).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
