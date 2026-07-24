<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationCategory extends Model
{
    /** @use HasFactory<\Database\Factories\LocationCategoryFactory> */
    use HasFactory;

    /**
     * The guide's table name — Eloquent would otherwise guess
     * "location_categories" from the class name.
     */
    protected $table = 'locations_category';

    /** The guide's spec has no created_at/updated_at on this table. */
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
        return $this->hasMany(Location::class, 'category_id');
    }

    /**
     * Convenience for future admin queries. The API payload does NOT rely on
     * this — serving filters live in MapBootstrapService, by design.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
