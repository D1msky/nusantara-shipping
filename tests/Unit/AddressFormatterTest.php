<?php

namespace Nusantara\Tests\Unit;

use Nusantara\Data\FileRepository;
use Nusantara\Shipping\AddressFormatter;
use Nusantara\Tests\TestCase;

class AddressFormatterTest extends TestCase
{
    private AddressFormatter $formatter;

    private FileRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FileRepository();
        $this->formatter = new AddressFormatter();
    }

    public function test_default_format(): void
    {
        $addr = $this->formatter->format($this->repo, '32.73.01.1001');
        $this->assertNotNull($addr);
        $this->assertStringContainsString('Cihapit', $addr);
        // District for 32.73.01 is Bandung Kulon (per data source)
        $this->assertStringContainsString('Bandung Kulon', $addr);
        $this->assertStringContainsString('40114', $addr);
    }

    public function test_jne_format(): void
    {
        $addr = $this->formatter->format($this->repo, '32.73.01.1001', null, 'jne');
        $this->assertNotNull($addr);
        $this->assertStringContainsString('CIHAPIT', $addr);
        $this->assertEquals(mb_strtoupper($addr), $addr);
    }

    public function test_jnt_format(): void
    {
        $addr = $this->formatter->format($this->repo, '32.73.01.1001', null, 'jnt');
        $this->assertNotNull($addr);
        $this->assertStringContainsString('CIHAPIT', $addr);
    }

    public function test_sicepat_format(): void
    {
        $addr = $this->formatter->format($this->repo, '32.73.01.1001', null, 'sicepat');
        $this->assertNotNull($addr);
        $this->assertStringContainsString('JAWA BARAT', $addr);
    }

    public function test_custom_format_template(): void
    {
        $addr = $this->formatter->format($this->repo, '32.73.01.1001', ':village, :district, :regency :postal');
        $this->assertNotNull($addr);
        $this->assertStringContainsString('40114', $addr);
    }

    public function test_title_case_conversion(): void
    {
        $addr = $this->formatter->format($this->repo, '32.73.01.1001');
        $this->assertNotNull($addr);
        $this->assertStringContainsString('Cihapit', $addr);
    }

    public function test_upper_case_conversion(): void
    {
        $addr = $this->formatter->format($this->repo, '32.73.01.1001', null, 'jne');
        $this->assertSame(mb_strtoupper($addr), $addr);
    }

    public function test_invalid_code_returns_null(): void
    {
        $this->assertNull($this->formatter->format($this->repo, '99.99.99.9999'));
    }
}
