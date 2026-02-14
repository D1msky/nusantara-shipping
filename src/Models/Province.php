<?php

declare(strict_types=1);

namespace Nusantara\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = ['code', 'name', 'latitude', 'longitude'];

    public function getTable(): string
    {
        return config('nusantara.table_prefix', 'nusantara_') . 'provinces';
    }

    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class, 'province_code', 'code');
    }

    public function getTitleNameAttribute(): string
    {
        return \Nusantara\Support\RegionCollection::toTitleCase($this->name);
    }
}
