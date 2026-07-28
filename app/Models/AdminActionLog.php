<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActionLog extends Model
{
    /** Append-only audit rows: created_at only, never updated. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'action',
        'target_type',
        'target_id',
        'ip_address',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'target_id' => 'integer',
        ];
    }
}
