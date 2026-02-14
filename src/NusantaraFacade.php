<?php

declare(strict_types=1);

namespace Nusantara;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Nusantara\Support\RegionCollection provinces()
 * @method static \Nusantara\Support\RegionCollection regencies(string $provinceCodeOrName)
 * @method static \Nusantara\Support\RegionCollection districts(string $regencyCode)
 * @method static \Nusantara\Support\RegionCollection villages(string $districtCode)
 * @method static array|null find(string $code)
 * @method static \Nusantara\Support\RegionCollection search(string $term, ?string $level = null)
 * @method static \Nusantara\Support\RegionCollection postalCode(string $postalCode)
 * @method static bool validPostalCode(string $postalCode)
 * @method static array postalCodes(string $regionCode)
 * @method static array|null hierarchy(string $code)
 * @method static string|null shippingAddress(string $regionCode, ?string $format = null, ?string $style = null)
 * @method static array coordinates(string $code)
 * @method static array|null nearestRegency(float $latitude, float $longitude)
 *
 * @see \Nusantara\Nusantara
 */
class NusantaraFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Nusantara::class;
    }
}
