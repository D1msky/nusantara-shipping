<?php

declare(strict_types=1);

namespace Nusantara\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = ['code', 'regency_code', 'name', 'latitude', 'longitude'];

    public function getTable(): string
    {
        return config('nusantara.table_prefix', 'nusantara_') . 'districts';
    }

    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'regency_code', 'code');
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class, 'district_code', 'code');
    }

    public function getTitleNameAttribute(): string
    {
        return \Nusantara\Support\RegionCollection::toTitleCase($this->name);
    }
}
