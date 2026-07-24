<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackSubmission extends Model
{
    /** @use HasFactory<\Database\Factories\FeedbackSubmissionFactory> */
    use HasFactory;

    /** kind: 'complaint' | 'request' (the app's MapFeedbackSubmission.kind). */
    public const KINDS = ['complaint', 'request'];

    protected $fillable = [
        'user_id',
        'kind',
        'description',
        'location_id',
        'lat',
        'long',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'long' => 'float',
            'location_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
