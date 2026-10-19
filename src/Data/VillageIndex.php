<?php

declare(strict_types=1);

namespace Nusantara\Data;

/**
 * Villages stored as one string, a line per village: "<code>\t<NAME>\t<postal_code>", sorted by code.
 *
 * The full dataset (~84k villages) as PHP arrays costs ~60 MB and ~120 ms to parse per process
 * without OPcache; as one string it is ~5 MB and parses in a few ms. Lookups by code or district
 * binary-search the sorted lines, and name/postal searches run strpos() over the string, so no
 * index is ever built.
 */
final class VillageIndex
{
    /** "\n" + lines joined by "\n" + "\n", so every line is "\n"-delimited on both sides */
    private string $blob;

    private ?string $upperBlob = null;

    private function __construct(string $blob)
    {
        $this->blob = $blob;
    }

    /**
     * @param mixed $data contents of villages.php: the line string, or the legacy array of rows
     */
    public static function fromData(mixed $data): self
    {
        if (is_string($data)) {
            // Files written by nusantara:update-data are already sorted and "\n"-wrapped; using the
            // string as-is keeps OPcache's immutable copy instead of allocating a new one.
            return new self(str_starts_with($data, "\n") && str_ends_with($data, "\n") ? $data : "\n" . trim($data, "\n") . "\n");
        }

        return self::fromRows(is_array($data) ? $data : []);
    }

    /** @param iterable<array{code: string, name?: string, postal_code?: string|int|null}> $rows */
    public static function fromRows(iterable $rows): self
    {
        $lines = [];
        foreach ($rows as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $lines[] = $code . "\t" . self::clean((string) ($row['name'] ?? '')) . "\t" . self::clean((string) ($row['postal_code'] ?? ''));
        }
        sort($lines, SORT_STRING);

        return new self($lines === [] ? "\n" : "\n" . implode("\n", $lines) . "\n");
    }

    /** PHP literal for a data file; one village per line, so diffs stay readable. */
    public function toPhpLiteral(): string
    {
        return var_export($this->blob, true);
    }

    public function count(): int
    {
        return max(0, substr_count($this->blob, "\n") - 1);
    }

    /** @return array{code: string, district_code: string, name: string, postal_code: string|null}|null */
    public function find(string $code): ?array
    {
        $start = $this->lowerBound($code . "\t");
        if (! $this->lineStartsWith($start, $code . "\t")) {
            return null;
        }

        return $this->row($this->lineAt($start));
    }

    /** @return array<int, array{code: string, district_code: string, name: string, postal_code: string|null}> */
    public function forDistrict(string $districtCode): array
    {
        $prefix = $districtCode . '.';
        $rows = [];
        for ($start = $this->lowerBound($prefix); $this->lineStartsWith($start, $prefix); $start += strlen($line) + 1) {
            $line = $this->lineAt($start);
            $rows[] = $this->row($line);
        }

        return $rows;
    }

    /** @return array<int, array{code: string, district_code: string, name: string, postal_code: string|null}> */
    public function byPostalCode(string $postalCode): array
    {
        $needle = "\t" . $postalCode . "\n";
        $rows = [];
        $offset = 0;
        while (($pos = strpos($this->blob, $needle, $offset)) !== false) {
            [$start, $end] = $this->lineBounds($pos);
            $rows[] = $this->row(substr($this->blob, $start, $end - $start));
            $offset = $end;
        }

        return $rows;
    }

    /**
     * Villages whose name contains $term (case-insensitive, ASCII), in code order.
     *
     * @return array<int, array{code: string, district_code: string, name: string, postal_code: string|null}>
     */
    public function search(string $term, int $limit): array
    {
        $term = strtoupper($term);
        if ($term === '' || $limit <= 0 || strpbrk($term, "\t\n") !== false) {
            return [];
        }

        // strtoupper() keeps byte offsets, so positions found in the upper-cased copy map 1:1 onto
        // the original; it is built once (and is the same string when names are already upper-case).
        $haystack = $this->upperBlob ??= strtoupper($this->blob);
        $rows = [];
        $offset = 0;
        while (count($rows) < $limit && ($pos = strpos($haystack, $term, $offset)) !== false) {
            [$start, $end] = $this->lineBounds($pos);
            $row = $this->row(substr($this->blob, $start, $end - $start));
            if (str_contains(strtoupper($row['name']), $term)) { // the hit may be in the code or postal column
                $rows[] = $row;
            }
            $offset = $end;
        }

        return $rows;
    }

    /** @return \Generator<int, array{code: string, district_code: string, name: string, postal_code: string|null}> */
    public function rows(): \Generator
    {
        $offset = 1;
        while (($end = strpos($this->blob, "\n", $offset)) !== false) {
            if ($end > $offset) {
                yield $this->row(substr($this->blob, $offset, $end - $offset));
            }
            $offset = $end + 1;
        }
    }

    /**
     * Offset of the first line that sorts at or after $key (strlen($blob) when there is none).
     * Binary search over byte offsets: each probe snaps forward to the next line start.
     */
    private function lowerBound(string $key): int
    {
        $length = strlen($this->blob);
        $lo = 0;
        $hi = $length - 1;
        while ($lo < $hi) {
            $mid = ($lo + $hi) >> 1;
            $start = strpos($this->blob, "\n", $mid) + 1;
            if ($start < $length && substr_compare($this->blob, $key, $start, strlen($key)) < 0) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }

        return strpos($this->blob, "\n", $lo) + 1;
    }

    private function lineStartsWith(int $start, string $prefix): bool
    {
        return $start < strlen($this->blob) && substr_compare($this->blob, $prefix, $start, strlen($prefix)) === 0;
    }

    private function lineAt(int $start): string
    {
        return substr($this->blob, $start, strpos($this->blob, "\n", $start) - $start);
    }

    /** @return array{0: int, 1: int} start and end (exclusive, at the "\n") of the line containing $pos */
    private function lineBounds(int $pos): array
    {
        // The blob starts with "\n", so a newline always exists at or before $pos.
        $start = strrpos($this->blob, "\n", $pos - strlen($this->blob)) + 1;

        return [$start, strpos($this->blob, "\n", $pos)];
    }

    /** @return array{code: string, district_code: string, name: string, postal_code: string|null} */
    private function row(string $line): array
    {
        [$code, $name, $postal] = explode("\t", $line, 3) + ['', '', ''];
        $dot = strrpos($code, '.');

        return [
            'code' => $code,
            'district_code' => $dot === false ? '' : substr($code, 0, $dot),
            'name' => $name,
            'postal_code' => $postal === '' ? null : $postal,
        ];
    }

    private static function clean(string $value): string
    {
        return trim(strtr($value, "\t\n\r", '   '));
    }
}
