<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    /** @use HasFactory<\Database\Factories\LocationFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'province_id',
        'district_id',
        'lat',
        'long',
        'status',
        'category_id',
        'description',
    ];

    /**
     * lat/long MUST cast to float: Postgres hands numeric columns to PHP as
     * strings, and without the cast the JSON contract would break by
     * serializing "lat": "40.9778" instead of "lat": 40.9778.
     *
     * No active/province query scopes here on purpose — filtering is the
     * application layer's job (MapBootstrapService), not the model's.
     */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'long' => 'float',
            'province_id' => 'integer',
            'district_id' => 'integer',
            'category_id' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LocationCategory::class, 'category_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }
}
