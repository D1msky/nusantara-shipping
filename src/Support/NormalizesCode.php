<?php

declare(strict_types=1);

namespace Nusantara\Support;

trait NormalizesCode
{
    protected function normalizeCode(string $code, int $maxParts): string
    {
        $code = trim(str_replace(' ', '', $code));

        if (str_contains($code, '.')) {
            $parts = explode('.', $code);

            return implode('.', array_slice($parts, 0, $maxParts));
        }

        if (strlen($code) === 2 && $maxParts >= 1) {
            return $code;
        }

        if (strlen($code) === 4 && $maxParts >= 2) {
            return substr($code, 0, 2) . '.' . substr($code, 2, 2);
        }

        if (strlen($code) === 6 && $maxParts >= 3) {
            return substr($code, 0, 2) . '.' . substr($code, 2, 2) . '.' . substr($code, 4, 2);
        }

        if (strlen($code) === 10 && $maxParts >= 4) {
            return substr($code, 0, 2) . '.' . substr($code, 2, 2) . '.' . substr($code, 4, 2) . '.' . substr($code, 6, 4);
        }

        return $code;
    }
}
