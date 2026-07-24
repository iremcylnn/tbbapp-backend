<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Map data source
    |--------------------------------------------------------------------------
    |
    | Which LocationSource implementation serves the map data (the ratified
    | mock-first, source-swappable seam). Selected in AppServiceProvider.
    |
    | Supported: "database" (seeded PostgreSQL), "mock" (in-code MapSeedData —
    | needs no database at all).
    |
    */

    'source' => env('MAP_SOURCE', 'database'),

];
