<?php

declare(strict_types=1);

namespace Nusantara\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Village extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = ['code', 'district_code', 'name', 'postal_code'];

    public function getTable(): string
    {
        return config('nusantara.table_prefix', 'nusantara_') . 'villages';
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_code', 'code');
    }

    public function getTitleNameAttribute(): string
    {
        return \Nusantara\Support\RegionCollection::toTitleCase($this->name);
    }
}
