<?php

declare(strict_types=1);

namespace Nusantara\Support;

use Illuminate\Support\Collection;

class RegionCollection extends Collection
{
    /**
     * Filter by name (case-insensitive partial match).
     */
    public function whereName(string $name): static
    {
        $lower = mb_strtolower($name);
        return $this->filter(function (array $item) use ($lower) {
            return str_contains(mb_strtolower($item['name'] ?? ''), $lower);
        })->values();
    }

    /**
     * Get only codes.
     *
     * @return array<int, string>
     */
    public function codes(): array
    {
        return $this->pluck('code')->filter()->values()->all();
    }

    /**
     * Get only names.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return $this->pluck('name')->filter()->values()->all();
    }

    /**
     * Convert to dropdown format (for &lt;select&gt; elements).
     *
     * @return array<string, string>
     */
    public function toDropdown(string $valueKey = 'code', string $labelKey = 'name'): array
    {
        return $this->pluck($labelKey, $valueKey)->filter()->all();
    }

    /**
     * Convert names to title case (e.g. 'JAWA BARAT' → 'Jawa Barat').
     */
    public function titleCase(): static
    {
        return $this->map(function (array $item) {
            $item['name'] = self::toTitleCase($item['name'] ?? '');
            return $item;
        });
    }

    public static function toTitleCase(string $value): string
    {
        return collect(explode(' ', $value))
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)) . mb_strtolower(mb_substr($word, 1)))
            ->implode(' ');
    }
}
