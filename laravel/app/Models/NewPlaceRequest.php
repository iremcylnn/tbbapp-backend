<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewPlaceRequest extends Model
{
    /** @use HasFactory<\Database\Factories\NewPlaceRequestFactory> */
    use HasFactory;

    /** One-way pipeline: pending → approved | rejected. */
    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'user_id',
        'title',
        'category_id',
        'description',
        'lat',
        'long',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'long' => 'float',
            'category_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LocationCategory::class, 'category_id');
    }
}
