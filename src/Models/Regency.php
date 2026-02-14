<?php

declare(strict_types=1);

namespace Nusantara\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regency extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = ['code', 'province_code', 'name', 'latitude', 'longitude'];

    public function getTable(): string
    {
        return config('nusantara.table_prefix', 'nusantara_') . 'regencies';
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_code', 'code');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class, 'regency_code', 'code');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('name', 'like', '%' . addcslashes($term, '%_\\') . '%');
    }

    public function getTitleNameAttribute(): string
    {
        return \Nusantara\Support\RegionCollection::toTitleCase($this->name);
    }
}
