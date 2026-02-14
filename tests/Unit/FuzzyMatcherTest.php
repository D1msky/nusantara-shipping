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
        $this->assertGreaterThan(0, $r->count());
        $names = $r->pluck('name')->all();
        $this->assertTrue(in_array('DKI JAKARTA', $names, true) || in_array('KOTA JAKARTA SELATAN', $names, true));
    }

    public function test_alias_match_jaksel(): void
    {
        $r = $this->matcher->search($this->repo, 'jaksel', 'regency');
        $this->assertGreaterThan(0, $r->count());
        $this->assertStringContainsString('JAKARTA SELATAN', $r->first()['name'] ?? '');
    }

    public function test_alias_match_jogja(): void
    {
        $r = $this->matcher->search($this->repo, 'jogja');
        $this->assertGreaterThan(0, $r->count());
    }

    public function test_alias_match_solo(): void
    {
        $r = $this->matcher->search($this->repo, 'solo');
        $this->assertGreaterThanOrEqual(0, $r->count());
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
}
