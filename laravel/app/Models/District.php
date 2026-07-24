<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    /** @use HasFactory<\Database\Factories\DistrictFactory> */
    use HasFactory;

    /** Mirror of locations_category: no timestamps in the schema. */
    public $timestamps = false;

    protected $fillable = ['title', 'status'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
        ];
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'district_id');
    }

    /** Convenience for admin queries; serving filters live in MapBootstrapService. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
