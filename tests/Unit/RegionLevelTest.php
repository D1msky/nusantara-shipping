<?php

namespace Nusantara\Tests\Unit;

use Nusantara\Enums\RegionLevel;
use Nusantara\Tests\TestCase;

class RegionLevelTest extends TestCase
{
    public function test_from_code_province(): void
    {
        $this->assertSame(RegionLevel::Province, RegionLevel::fromCode('32'));
        $this->assertSame(RegionLevel::Province, RegionLevel::fromCode('32.'));
    }

    public function test_from_code_regency(): void
    {
        $this->assertSame(RegionLevel::Regency, RegionLevel::fromCode('32.73'));
        $this->assertSame(RegionLevel::Regency, RegionLevel::fromCode('32.73.'));
    }

    public function test_from_code_district(): void
    {
        $this->assertSame(RegionLevel::District, RegionLevel::fromCode('32.73.01'));
    }

    public function test_from_code_village(): void
    {
        $this->assertSame(RegionLevel::Village, RegionLevel::fromCode('32.73.01.1001'));
        $this->assertSame(RegionLevel::Village, RegionLevel::fromCode('32.73.01.1001.'));
    }

    public function test_from_code_trimmed(): void
    {
        $this->assertSame(RegionLevel::Province, RegionLevel::fromCode(' 32 '));
        $this->assertSame(RegionLevel::Regency, RegionLevel::fromCode('.32.73.'));
    }

    public function test_from_code_empty_returns_province(): void
    {
        $this->assertSame(RegionLevel::Province, RegionLevel::fromCode(''));
        $this->assertSame(RegionLevel::Province, RegionLevel::fromCode('.'));
    }

    public function test_label_province(): void
    {
        $this->assertSame('Province', RegionLevel::Province->label());
    }

    public function test_label_regency(): void
    {
        $this->assertSame('Regency', RegionLevel::Regency->label());
    }

    public function test_label_district(): void
    {
        $this->assertSame('District', RegionLevel::District->label());
    }

    public function test_label_village(): void
    {
        $this->assertSame('Village', RegionLevel::Village->label());
    }
}
