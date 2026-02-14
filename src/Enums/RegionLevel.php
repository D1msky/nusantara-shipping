<?php

declare(strict_types=1);

namespace Nusantara\Enums;

enum RegionLevel: string
{
    case Province = 'province';
    case Regency = 'regency';
    case District = 'district';
    case Village = 'village';

    public function label(): string
    {
        return match ($this) {
            self::Province => 'Province',
            self::Regency => 'Regency',
            self::District => 'District',
            self::Village => 'Village',
        };
    }

    /**
     * Infer level from dot-separated code (e.g. "32" -> province, "32.73.01.1001" -> village).
     */
    public static function fromCode(string $code): self
    {
        $parts = substr_count(trim($code, '.'), '.') + 1;
        return match (true) {
            $parts <= 1 => self::Province,
            $parts === 2 => self::Regency,
            $parts === 3 => self::District,
            default => self::Village,
        };
    }
}
