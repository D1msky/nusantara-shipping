<?php

declare(strict_types=1);

namespace Nusantara\Search;

use Nusantara\Data\RepositoryInterface;
use Nusantara\Support\Cache;
use Nusantara\Support\RegionCollection;

final class FuzzyMatcher
{
    /** @var array<string, string> */
    private array $aliases;

    private int $threshold;

    private int $maxResults;

    /** Distinguishes cached results of matchers built with different aliases/threshold/limits. */
    private string $fingerprint;

    public function __construct(?array $customAliases = null, ?int $threshold = null, ?int $maxResults = null)
    {
        // Lower-case keys so lookups are O(1) and a custom 'JKT' overrides the built-in 'jkt'.
        $this->aliases = array_change_key_case(
            array_merge($this->defaultAliases(), (array) ($customAliases ?? config('nusantara.aliases', []))),
            CASE_LOWER
        );
        $this->threshold = $threshold ?? (int) (config('nusantara.search.fuzzy_threshold') ?? 70);
        $this->maxResults = $maxResults ?? (int) (config('nusantara.search.max_results') ?? 20);
        $this->fingerprint = md5(serialize([$this->aliases, $this->threshold, $this->maxResults]));
    }

    public function search(RepositoryInterface $repository, string $term, ?string $level = null): RegionCollection
    {
        $term = trim($term);
        if ($term === '') {
            return new RegionCollection([]);
        }

        $level = $level !== null ? strtolower($level) : null;

        // Check search cache (case/whitespace variants of the same query share one entry)
        $cache = new Cache();
        $cacheKey = 'search:' . md5($this->normalizeInput(mb_strtolower($term)) . '|' . ($level ?? '*') . '|' . $this->fingerprint);

        return $cache->get($cacheKey, function () use ($repository, $term, $level) {
            return $this->performSearch($repository, $term, $level);
        });
    }

    private function performSearch(RepositoryInterface $repository, string $term, ?string $level): RegionCollection
    {
        $termLower = mb_strtolower($term);
        $normalized = $this->normalizeInput($termLower);

        // 1) Alias exact match (highest priority)
        $targetName = $this->aliases[$normalized] ?? $this->aliases[$termLower] ?? null;
        if ($targetName !== null) {
            $candidates = $repository->search($targetName, $level);
            if ($candidates->isNotEmpty()) {
                return $this->limitAndSort($candidates->all(), $targetName, 100);
            }
        }

        // 2) Multi-token: try alias per token then combine
        $tokens = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) > 1) {
            $expanded = [];
            foreach ($tokens as $t) {
                $expanded[] = $this->aliases[$t] ?? $t;
            }
            $expandedQuery = implode(' ', $expanded);
            $candidates = $repository->search($expandedQuery, $level);
            if ($candidates->isNotEmpty()) {
                return $this->limitAndSort($candidates->all(), $expandedQuery, $this->maxResults);
            }
        }

        // 3) Direct search (substring)
        $candidates = $repository->search($term, $level);
        if ($candidates->isNotEmpty()) {
            return $this->limitAndSort($candidates->all(), $term, $this->maxResults);
        }

        // 4) Fuzzy similarity over all regions of that level
        $all = $this->collectCandidates($repository, $level);
        $scored = [];
        foreach ($all as $item) {
            $name = $item['name'] ?? '';
            $score = $this->scoreSimilarity($termLower, mb_strtolower($name), $name);
            if ($score >= $this->threshold) {
                $item['_score'] = $score;
                $scored[] = $item;
            }
        }

        usort($scored, fn ($a, $b) => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));
        $scored = array_slice($scored, 0, $this->maxResults);

        foreach ($scored as &$s) {
            unset($s['_score']);
        }

        return new RegionCollection($scored);
    }

    /**
     * @param array<int, array> $items
     */
    private function limitAndSort(array $items, string $reference, int $max): RegionCollection
    {
        // Score each item once instead of twice per comparison inside usort (levenshtein is the hot path).
        $refLower = mb_strtolower($reference);
        $scores = [];
        foreach ($items as $i => $item) {
            $name = $item['name'] ?? '';
            $scores[$i] = $this->scoreSimilarity($refLower, mb_strtolower($name), $name);
        }
        arsort($scores);

        $sorted = [];
        foreach (array_slice(array_keys($scores), 0, $max) as $i) {
            $sorted[] = $items[$i];
        }

        return new RegionCollection($sorted);
    }

    private function collectCandidates(RepositoryInterface $repository, ?string $level): array
    {
        $out = [];

        if ($level === null || $level === 'province') {
            foreach ($repository->provinces()->all() as $p) {
                $out[] = array_merge($p, ['level' => 'province']);
            }
        }

        if ($level === null || $level === 'regency') {
            foreach ($repository->allRegencies()->all() as $r) {
                $out[] = array_merge($r, ['level' => 'regency']);
            }
        }

        if ($level === null || $level === 'district') {
            foreach ($repository->allDistricts()->all() as $d) {
                $out[] = array_merge($d, ['level' => 'district']);
            }
        }

        // Village-level fuzzy is skipped for performance (83k+ records).
        // Village search is handled by alias match and substring search above.

        return $out;
    }

    private function scoreSimilarity(string $term, string $name, string $originalName): int
    {
        if ($name === '' || $term === '') {
            return 0;
        }

        if (str_contains($name, $term) || str_contains($originalName, $term)) {
            return 95;
        }

        similar_text($term, $name, $pct);
        $lev = levenshtein(mb_substr($term, 0, 255), mb_substr($name, 0, 255));
        $maxLen = max(mb_strlen($term), mb_strlen($name), 1);
        $levScore = (int) max(0, 100 - ($lev / $maxLen) * 100);

        return (int) max($pct, $levScore);
    }

    private function normalizeInput(string $input): string
    {
        return preg_replace('/\s+/', ' ', $input);
    }

    /** @return array<string, string> */
    private function defaultAliases(): array
    {
        // Targets use the official Kepmendagri names; the pre-2025 short names stay as aliases
        // so stored addresses like "DKI JAKARTA" still resolve.
        return [
            'jkt' => 'DAERAH KHUSUS IBUKOTA JAKARTA',
            'jakarta' => 'DAERAH KHUSUS IBUKOTA JAKARTA',
            'dki' => 'DAERAH KHUSUS IBUKOTA JAKARTA',
            'dki jakarta' => 'DAERAH KHUSUS IBUKOTA JAKARTA',
            'jaksel' => 'KOTA ADMINISTRASI JAKARTA SELATAN',
            'jakut' => 'KOTA ADMINISTRASI JAKARTA UTARA',
            'jaktim' => 'KOTA ADMINISTRASI JAKARTA TIMUR',
            'jakbar' => 'KOTA ADMINISTRASI JAKARTA BARAT',
            'jakpus' => 'KOTA ADMINISTRASI JAKARTA PUSAT',
            'kota jakarta selatan' => 'KOTA ADMINISTRASI JAKARTA SELATAN',
            'kota jakarta utara' => 'KOTA ADMINISTRASI JAKARTA UTARA',
            'kota jakarta timur' => 'KOTA ADMINISTRASI JAKARTA TIMUR',
            'kota jakarta barat' => 'KOTA ADMINISTRASI JAKARTA BARAT',
            'kota jakarta pusat' => 'KOTA ADMINISTRASI JAKARTA PUSAT',
            'sby' => 'KOTA SURABAYA',
            'surabaya' => 'KOTA SURABAYA',
            'bdg' => 'KOTA BANDUNG',
            'bandung' => 'KOTA BANDUNG',
            'smg' => 'KOTA SEMARANG',
            'semarang' => 'KOTA SEMARANG',
            'jogja' => 'DAERAH ISTIMEWA YOGYAKARTA',
            'yogya' => 'DAERAH ISTIMEWA YOGYAKARTA',
            'yogyakarta' => 'DAERAH ISTIMEWA YOGYAKARTA',
            'diy' => 'DAERAH ISTIMEWA YOGYAKARTA',
            'di yogyakarta' => 'DAERAH ISTIMEWA YOGYAKARTA',
            'solo' => 'KOTA SURAKARTA',
            'surakarta' => 'KOTA SURAKARTA',
            'medan' => 'KOTA MEDAN',
            'mks' => 'KOTA MAKASSAR',
            'mksr' => 'KOTA MAKASSAR',
            'makassar' => 'KOTA MAKASSAR',
            'mlg' => 'KOTA MALANG',
            'malang' => 'KOTA MALANG',
            'dpk' => 'KOTA DEPOK',
            'depok' => 'KOTA DEPOK',
            'tgr' => 'KOTA TANGERANG',
            'tangerang' => 'KOTA TANGERANG',
            'tangsel' => 'KOTA TANGERANG SELATAN',
            'bks' => 'KOTA BEKASI',
            'bekasi' => 'KOTA BEKASI',
            'bgr' => 'KOTA BOGOR',
            'bogor' => 'KOTA BOGOR',
            'jkt selatan' => 'KOTA ADMINISTRASI JAKARTA SELATAN',
            'jkt utara' => 'KOTA ADMINISTRASI JAKARTA UTARA',
            'jkt timur' => 'KOTA ADMINISTRASI JAKARTA TIMUR',
            'jkt barat' => 'KOTA ADMINISTRASI JAKARTA BARAT',
            'jkt pusat' => 'KOTA ADMINISTRASI JAKARTA PUSAT',
        ];
    }
}
