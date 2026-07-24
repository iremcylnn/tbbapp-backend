<?php

namespace App\NewPlaces;

use App\Models\AdminActionLog;
use App\Models\Location;
use App\Models\NewPlaceRequest;
use Illuminate\Support\Facades\DB;

/**
 * Approve/reject pipeline for citizen-submitted new-place requests.
 *
 * Both decisions "claim" the request atomically: the status flip runs as
 * UPDATE ... WHERE status = 'pending', so when two admins race on the same
 * request only one UPDATE matches a row (TOCTOU defense ported from the old
 * server's routes/newPlaceRequests.js). The loser gets null and the
 * controller answers 409.
 *
 * Approval creates the real locations row and the audit log entry inside the
 * SAME transaction as the claim — all three happen or none do. The new
 * locations row fires the Postgres statement trigger, so map_version bumps
 * (and the bootstrap ETag rotates) without any application code.
 */
class NewPlaceApprovalService
{
    /**
     * @return array{submission: NewPlaceRequest, location: Location}|null
     *         null = another admin already decided this request
     */
    public function approve(NewPlaceRequest $submission, int $districtId, int $categoryId, string $ip): ?array
    {
        return DB::transaction(function () use ($submission, $districtId, $categoryId, $ip) {
            if (! $this->claim($submission, 'approved')) {
                return null;
            }

            $location = Location::create([
                'title' => $submission->title,
                'district_id' => $districtId,
                'category_id' => $categoryId,
                'lat' => $submission->lat,
                'long' => $submission->long,
                'description' => $submission->description,
            ]);

            AdminActionLog::create([
                'action' => 'new_place_request.approved',
                'target_type' => 'NewPlaceRequest',
                'target_id' => $submission->id,
                'ip_address' => $ip,
                'metadata' => [
                    'location_id' => $location->id,
                    'district_id' => $districtId,
                    'category_id' => $categoryId,
                ],
            ]);

            return ['submission' => $submission->refresh(), 'location' => $location];
        });
    }

    /** @return NewPlaceRequest|null null = another admin already decided this request */
    public function reject(NewPlaceRequest $submission, string $ip): ?NewPlaceRequest
    {
        return DB::transaction(function () use ($submission, $ip) {
            if (! $this->claim($submission, 'rejected')) {
                return null;
            }

            AdminActionLog::create([
                'action' => 'new_place_request.rejected',
                'target_type' => 'NewPlaceRequest',
                'target_id' => $submission->id,
                'ip_address' => $ip,
            ]);

            return $submission->refresh();
        });
    }

    private function claim(NewPlaceRequest $submission, string $status): bool
    {
        return NewPlaceRequest::query()
            ->whereKey($submission->id)
            ->where('status', 'pending')
            ->update(['status' => $status]) === 1;
    }
}
