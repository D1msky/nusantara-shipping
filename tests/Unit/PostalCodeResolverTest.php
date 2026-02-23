<?php

namespace Nusantara\Tests\Unit;

use Nusantara\Data\FileRepository;
use Nusantara\Shipping\PostalCodeResolver;
use Nusantara\Tests\TestCase;

class PostalCodeResolverTest extends TestCase
{
    private PostalCodeResolver $resolver;

    private FileRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FileRepository();
        $this->resolver = new PostalCodeResolver();
    }

    public function test_find_villages_by_postal_code(): void
    {
        $r = $this->resolver->findByPostalCode($this->repo, '40132');
        $this->assertGreaterThan(0, $r->count());
        $this->assertEquals('40132', $r->first()['postal_code'] ?? null);
    }

    public function test_validate_existing_postal_code(): void
    {
        $this->assertTrue($this->resolver->validPostalCode($this->repo, '40132'));
        $this->assertTrue($this->resolver->validPostalCode($this->repo, '40114'));
    }

    public function test_validate_nonexistent_postal_code(): void
    {
        $this->assertFalse($this->resolver->validPostalCode($this->repo, '99999'));
    }

    public function test_get_postal_codes_by_regency(): void
    {
        $codes = $this->resolver->postalCodesForRegion($this->repo, '32.73');
        $this->assertIsArray($codes);
        if (count($codes) > 0) {
            $this->assertTrue(
                in_array('40114', $codes, true) || in_array(40114, $codes, true)
                || in_array('40132', $codes, true) || in_array(40132, $codes, true),
                'Sample data should contain 40114 or 40132 for Kota Bandung. Got: ' . implode(', ', array_map(strval(...), $codes))
            );
        }
    }
}
