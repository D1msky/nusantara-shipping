<?php

namespace Nusantara\Tests\Feature;

use Nusantara\Tests\TestCase;

class StatsCommandTest extends TestCase
{
    public function test_reports_counts_of_the_bundled_data(): void
    {
        $this->artisan('nusantara:stats')
            ->expectsOutputToContain('Provinces:    38')
            ->expectsOutputToContain('Regencies:    514')
            ->expectsOutputToContain('Districts:    7,285')
            ->expectsOutputToContain('Villages:     83,762')
            ->assertSuccessful();
    }
}
