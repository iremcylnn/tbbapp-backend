<?php

namespace App\Http\Controllers;

use App\Map\MapBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/map/bootstrap — the one round-trip the app's map module needs.
 *
 * The ETag check runs BEFORE the payload is built: when the client's
 * If-None-Match matches, the whole request costs one single-row SELECT
 * (the version read) — no locations query, no serialization.
 */
class MapBootstrapController extends Controller
{
    public function __invoke(Request $request, MapBootstrapService $map): JsonResponse
    {
        $etag = $map->etag();

        // getETags() is the parsed If-None-Match header; '*' matches any
        // representation per RFC 9110.
        $clientETags = $request->getETags();
        if (in_array($etag, $clientETags, true) || in_array('*', $clientETags, true)) {
            return response()->json(null, 304)->withHeaders(['ETag' => $etag]);
        }

        return response()->json($map->payload())->withHeaders(['ETag' => $etag]);
    }
}
