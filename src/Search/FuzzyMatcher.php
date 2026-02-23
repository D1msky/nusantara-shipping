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

    public function __construct(?array $customAliases = null, ?int $threshold = null, ?int $maxResults = null)
    {
        $this->aliases = array_merge($this->defaultAliases(), (array) ($customAliases ?? config('nusantara.aliases', [])));
        $this->threshold = $threshold ?? (int) (config('nusantara.search.fuzzy_threshold') ?? 70);
        $this->maxResults = $maxResults ?? (int) (config('nusantara.search.max_results') ?? 20);
    }

    public function search(RepositoryInterface $repository, string $term, ?string $level = null): RegionCollection
    {
        $term = trim($term);
        if ($term === '') {
            return new RegionCollection([]);
        }

        // Check search cache
        $cache = new Cache();
        $cacheKey = 'search:' . md5($term . '|' . ($level ?? '*'));

        return $cache->get($cacheKey, function () use ($repository, $term, $level) {
            return $this->performSearch($repository, $term, $level);
        });
    }

    private function performSearch(RepositoryInterface $repository, string $term, ?string $level): RegionCollection
    {
        $termLower = mb_strtolower($term);
        $normalized = $this->normalizeInput($termLower);

        // 1) Alias exact match (highest priority)
        foreach ($this->aliases as $alias => $targetName) {
            if ($termLower === mb_strtolower($alias) || $normalized === mb_strtolower($alias)) {
                $candidates = $repository->search($targetName, $level);
                if ($candidates->isNotEmpty()) {
                    return $this->limitAndSort($candidates->all(), $targetName, 100);
                }
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
        $refLower = mb_strtolower($reference);
        usort($items, function ($a, $b) use ($refLower) {
            $na = $a['name'] ?? '';
            $nb = $b['name'] ?? '';
            $sa = $this->scoreSimilarity($refLower, mb_strtolower($na), $na);
            $sb = $this->scoreSimilarity($refLower, mb_strtolower($nb), $nb);

            return $sb <=> $sa;
        });

        return new RegionCollection(array_slice($items, 0, $max));
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
            foreach ($repository->provinces()->all() as $p) {
                foreach ($repository->regencies($p['code'])->all() as $r) {
                    $out[] = array_merge($r, ['level' => 'regency']);
                }
            }
        }

        if ($level === null || $level === 'district') {
            foreach ($repository->provinces()->all() as $p) {
                foreach ($repository->regencies($p['code'])->all() as $r) {
                    foreach ($repository->districts($r['code'])->all() as $d) {
                        $out[] = array_merge($d, ['level' => 'district']);
                    }
                }
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
        return [
            'jkt' => 'DKI JAKARTA',
            'jakarta' => 'DKI JAKARTA',
            'jaksel' => 'KOTA JAKARTA SELATAN',
            'jakut' => 'KOTA JAKARTA UTARA',
            'jaktim' => 'KOTA JAKARTA TIMUR',
            'jakbar' => 'KOTA JAKARTA BARAT',
            'jakpus' => 'KOTA JAKARTA PUSAT',
            'sby' => 'KOTA SURABAYA',
            'surabaya' => 'KOTA SURABAYA',
            'bdg' => 'KOTA BANDUNG',
            'bandung' => 'KOTA BANDUNG',
            'smg' => 'KOTA SEMARANG',
            'semarang' => 'KOTA SEMARANG',
            'jogja' => 'DI YOGYAKARTA',
            'yogya' => 'DI YOGYAKARTA',
            'yogyakarta' => 'DI YOGYAKARTA',
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
            'jkt selatan' => 'KOTA JAKARTA SELATAN',
            'jkt utara' => 'KOTA JAKARTA UTARA',
            'jkt timur' => 'KOTA JAKARTA TIMUR',
            'jkt barat' => 'KOTA JAKARTA BARAT',
            'jkt pusat' => 'KOTA JAKARTA PUSAT',
        ];
    }
}
