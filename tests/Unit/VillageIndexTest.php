<?php

namespace Nusantara\Tests\Unit;

use Nusantara\Data\VillageIndex;
use Nusantara\Tests\TestCase;

class VillageIndexTest extends TestCase
{
    private function index(): VillageIndex
    {
        // Deliberately unsorted: fromRows() must sort for the binary search to work.
        return VillageIndex::fromRows([
            ['code' => '32.73.02.1002', 'district_code' => '32.73.02', 'name' => 'LEBAK GEDE', 'postal_code' => '40132'],
            ['code' => '11.01.01.2001', 'district_code' => '11.01.01', 'name' => 'KEUDE BAKONGAN', 'postal_code' => '23773'],
            ['code' => '32.73.01.1002', 'district_code' => '32.73.01', 'name' => 'GEGERKALONG', 'postal_code' => '40153'],
            ['code' => '32.73.01.1001', 'district_code' => '32.73.01', 'name' => 'SUKARASA', 'postal_code' => '40152'],
            ['code' => '32.73.02.1006', 'district_code' => '32.73.02', 'name' => 'LEBAK SILIWANGI', 'postal_code' => '40132'],
            ['code' => '73.71.01.1001', 'district_code' => '73.71.01', 'name' => "MA'RANG", 'postal_code' => null],
            ['code' => '96.71.10.1004', 'district_code' => '96.71.10', 'name' => 'TANJUNG KASUARI', 'postal_code' => '98412'],
        ]);
    }

    public function test_find_returns_row_in_the_original_shape(): void
    {
        $this->assertSame(
            ['code' => '32.73.01.1001', 'district_code' => '32.73.01', 'name' => 'SUKARASA', 'postal_code' => '40152'],
            $this->index()->find('32.73.01.1001')
        );
    }

    public function test_find_first_last_and_missing_codes(): void
    {
        $index = $this->index();
        $this->assertSame('KEUDE BAKONGAN', $index->find('11.01.01.2001')['name'] ?? null);
        $this->assertSame('TANJUNG KASUARI', $index->find('96.71.10.1004')['name'] ?? null);
        $this->assertNull($index->find('00.00.00.0000'));
        $this->assertNull($index->find('99.99.99.9999'));
        $this->assertNull($index->find('32.73.01.100')); // prefix of a real code is not a match
    }

    public function test_for_district_returns_contiguous_villages_in_code_order(): void
    {
        $index = $this->index();
        $this->assertSame(['32.73.01.1001', '32.73.01.1002'], array_column($index->forDistrict('32.73.01'), 'code'));
        $this->assertSame(['96.71.10.1004'], array_column($index->forDistrict('96.71.10'), 'code'));
        $this->assertSame([], $index->forDistrict('32.73.03'));
        $this->assertSame([], $index->forDistrict('32.73.0'));
    }

    public function test_by_postal_code(): void
    {
        $index = $this->index();
        $this->assertSame(['LEBAK GEDE', 'LEBAK SILIWANGI'], array_column($index->byPostalCode('40132'), 'name'));
        $this->assertSame([], $index->byPostalCode('99999'));
    }

    public function test_search_is_case_insensitive_and_only_matches_names(): void
    {
        $index = $this->index();
        $this->assertSame(['LEBAK GEDE', 'LEBAK SILIWANGI'], array_column($index->search('lebak', 10), 'name'));
        $this->assertSame(['LEBAK GEDE'], array_column($index->search('lebak', 1), 'name'));
        $this->assertSame(["MA'RANG"], array_column($index->search("ma'rang", 10), 'name'));
        $this->assertSame([], $index->search('40132', 10), 'postal column must not match');
        $this->assertSame([], $index->search('32.73', 10), 'code column must not match');
        $this->assertSame([], $index->search("lebak\tgede", 10));
    }

    public function test_null_postal_code_round_trips(): void
    {
        $this->assertNull($this->index()->find('73.71.01.1001')['postal_code']);
    }

    public function test_php_literal_round_trips_through_a_data_file(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'nusantara-villages');
        file_put_contents($file, '<?php return ' . $this->index()->toPhpLiteral() . ';');

        $loaded = VillageIndex::fromData(require $file);
        unlink($file);

        $this->assertSame(iterator_to_array($this->index()->rows()), iterator_to_array($loaded->rows()));
        $this->assertSame(7, $loaded->count());
    }

    public function test_crlf_line_endings_are_normalized(): void
    {
        // e.g. a git checkout on Windows with core.autocrlf=true converts the data file to CRLF
        $index = VillageIndex::fromData(str_replace("\n", "\r\n", "\n32.73.01.1001\tSUKARASA\t40152\n32.73.02.1002\tLEBAK GEDE\t40132\n73.71.01.1001\tMA'RANG\t\n"));

        $this->assertSame(['LEBAK GEDE'], array_column($index->byPostalCode('40132'), 'name'));
        $this->assertSame('40152', $index->find('32.73.01.1001')['postal_code'] ?? null);
        $this->assertNull($index->find('73.71.01.1001')['postal_code']);
        $this->assertSame(['32.73.01.1001'], array_column($index->forDistrict('32.73.01'), 'code'));
        $this->assertSame(3, $index->count());
    }

    public function test_legacy_array_data_is_accepted(): void
    {
        $index = VillageIndex::fromData([
            ['code' => '32.73.01.1001', 'district_code' => '32.73.01', 'name' => 'SUKARASA', 'postal_code' => 40152],
        ]);

        $this->assertSame('40152', $index->find('32.73.01.1001')['postal_code']);
    }

    public function test_empty_data(): void
    {
        $index = VillageIndex::fromData(null);
        $this->assertSame(0, $index->count());
        $this->assertNull($index->find('32.73.01.1001'));
        $this->assertSame([], $index->forDistrict('32.73.01'));
        $this->assertSame([], $index->search('a', 10));
        $this->assertSame([], iterator_to_array($index->rows()));
    }
}
