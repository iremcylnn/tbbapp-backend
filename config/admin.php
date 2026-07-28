<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin API key
    |--------------------------------------------------------------------------
    |
    | Shared secret protecting admin endpoints (x-admin-key header) — the old
    | server's interim scheme, kept until real admin accounts/roles arrive.
    | env() is read here and ONLY here: config can be cached; routes and
    | middleware must read config('admin.api_key'), never env() directly.
    |
    */

    'api_key' => env('ADMIN_API_KEY', ''),

];
