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
        $code = trim($code);
        if ($code === '') {
            return self::Province;
        }

        $dotCount = substr_count(trim($code, '.'), '.');

        return match ($dotCount) {
            0 => self::Province,
            1 => self::Regency,
            2 => self::District,
            default => self::Village,
        };
    }
}
