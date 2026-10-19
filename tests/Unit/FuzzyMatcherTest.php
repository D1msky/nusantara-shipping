<?php

namespace Nusantara\Tests\Unit;

use Nusantara\Data\FileRepository;
use Nusantara\Search\FuzzyMatcher;
use Nusantara\Tests\TestCase;

class FuzzyMatcherTest extends TestCase
{
    private FuzzyMatcher $matcher;

    private FileRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FileRepository();
        $this->matcher = new FuzzyMatcher([], 70, 20);
    }

    public function test_exact_match(): void
    {
        $r = $this->matcher->search($this->repo, 'BANDUNG', 'regency');
        $this->assertGreaterThan(0, $r->count());
        $this->assertStringContainsString('BANDUNG', $r->first()['name'] ?? '');
    }

    public function test_partial_match(): void
    {
        $r = $this->matcher->search($this->repo, 'bandung');
        $this->assertGreaterThan(0, $r->count());
    }

    public function test_alias_match_jkt(): void
    {
        $r = $this->matcher->search($this->repo, 'jkt');
        $this->assertSame('DAERAH KHUSUS IBUKOTA JAKARTA', $r->first()['name'] ?? null);
    }

    public function test_alias_match_jaksel(): void
    {
        $r = $this->matcher->search($this->repo, 'jaksel', 'regency');
        $this->assertSame('31.74', $r->first()['code'] ?? null);
        $this->assertStringContainsString('JAKARTA SELATAN', $r->first()['name'] ?? '');
    }

    public function test_pre_2025_names_still_resolve(): void
    {
        $this->assertSame('31', $this->matcher->search($this->repo, 'DKI Jakarta', 'province')->first()['code'] ?? null);
        $this->assertSame('34', $this->matcher->search($this->repo, 'DI Yogyakarta', 'province')->first()['code'] ?? null);
        $this->assertSame('31.74', $this->matcher->search($this->repo, 'Kota Jakarta Selatan', 'regency')->first()['code'] ?? null);
    }

    public function test_alias_match_jogja(): void
    {
        $r = $this->matcher->search($this->repo, 'jogja');
        $this->assertGreaterThan(0, $r->count());
    }

    public function test_alias_match_solo(): void
    {
        $r = $this->matcher->search($this->repo, 'solo', 'regency');
        $this->assertSame('KOTA SURAKARTA', $r->first()['name'] ?? null);
    }

    public function test_alias_match_sby(): void
    {
        $r = $this->matcher->search($this->repo, 'sby', 'regency');
        $this->assertGreaterThan(0, $r->count());
        $this->assertStringContainsString('SURABAYA', $r->first()['name'] ?? '');
    }

    public function test_case_insensitive_search(): void
    {
        $r = $this->matcher->search($this->repo, 'JaWa BaRat');
        $this->assertGreaterThan(0, $r->count());
    }

    public function test_no_match_returns_empty(): void
    {
        $r = $this->matcher->search($this->repo, 'xyznonexistent123');
        $this->assertEquals(0, $r->count());
    }

    public function test_custom_alias_from_config(): void
    {
        $this->app['config']->set('nusantara.aliases', ['mycity' => 'KOTA BANDUNG']);
        $matcher = new FuzzyMatcher(config('nusantara.aliases'), 70, 20);
        $r = $matcher->search($this->repo, 'mycity');
        $this->assertGreaterThan(0, $r->count());
    }

    public function test_custom_alias_overrides_builtin_alias_regardless_of_case(): void
    {
        $matcher = new FuzzyMatcher(['JKT' => 'KOTA BANDUNG'], 70, 20);
        $r = $matcher->search($this->repo, 'jkt');
        $this->assertSame('KOTA BANDUNG', $r->first()['name'] ?? null);
    }

    public function test_fuzzy_fallback_accepts_level_in_any_case(): void
    {
        $r = $this->matcher->search($this->repo, 'bandug kulon', 'District');
        $this->assertSame('BANDUNG KULON', $r->first()['name'] ?? null);
    }
}
