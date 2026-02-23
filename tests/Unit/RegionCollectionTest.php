<?php

namespace Nusantara\Tests\Unit;

use Nusantara\Support\RegionCollection;
use Nusantara\Tests\TestCase;

class RegionCollectionTest extends TestCase
{
    private function sampleCollection(): RegionCollection
    {
        return new RegionCollection([
            ['code' => '32', 'name' => 'JAWA BARAT'],
            ['code' => '32.73', 'name' => 'KOTA BANDUNG'],
            ['code' => '33', 'name' => 'JAWA TENGAH'],
        ]);
    }

    public function test_where_name_filters_case_insensitive(): void
    {
        $coll = $this->sampleCollection();
        $filtered = $coll->whereName('jawa');
        $this->assertCount(2, $filtered);
        $names = $filtered->pluck('name')->all();
        $this->assertContains('JAWA BARAT', $names);
        $this->assertContains('JAWA TENGAH', $names);
    }

    public function test_where_name_partial_match(): void
    {
        $coll = $this->sampleCollection();
        $filtered = $coll->whereName('BANDUNG');
        $this->assertCount(1, $filtered);
        $this->assertSame('KOTA BANDUNG', $filtered->first()['name']);
    }

    public function test_where_name_no_match_returns_empty(): void
    {
        $coll = $this->sampleCollection();
        $filtered = $coll->whereName('nonexistent');
        $this->assertCount(0, $filtered);
    }

    public function test_codes_returns_array_of_codes(): void
    {
        $coll = $this->sampleCollection();
        $codes = $coll->codes();
        $this->assertSame(['32', '32.73', '33'], $codes);
    }

    public function test_names_returns_array_of_names(): void
    {
        $coll = $this->sampleCollection();
        $names = $coll->names();
        $this->assertSame(['JAWA BARAT', 'KOTA BANDUNG', 'JAWA TENGAH'], $names);
    }

    public function test_to_dropdown_default_code_as_value_name_as_label(): void
    {
        $coll = $this->sampleCollection();
        $dropdown = $coll->toDropdown();
        $this->assertSame([
            '32' => 'JAWA BARAT',
            '32.73' => 'KOTA BANDUNG',
            '33' => 'JAWA TENGAH',
        ], $dropdown);
    }

    public function test_to_dropdown_custom_keys(): void
    {
        $coll = new RegionCollection([['code' => 'a', 'name' => 'Alpha', 'id' => 1]]);
        $dropdown = $coll->toDropdown('id', 'name');
        $this->assertSame([1 => 'Alpha'], $dropdown);
    }

    public function test_title_case_converts_names(): void
    {
        $coll = $this->sampleCollection();
        $titleCased = $coll->titleCase();
        $this->assertSame('Jawa Barat', $titleCased->first()['name']);
        $this->assertSame('Kota Bandung', $titleCased->get(1)['name']);
    }

    public function test_to_title_case_static(): void
    {
        $this->assertSame('Jawa Barat', RegionCollection::toTitleCase('JAWA BARAT'));
        $this->assertSame('Dki Jakarta', RegionCollection::toTitleCase('DKI JAKARTA'));
    }

    public function test_to_title_case_single_word(): void
    {
        $this->assertSame('Bandung', RegionCollection::toTitleCase('BANDUNG'));
    }
}
