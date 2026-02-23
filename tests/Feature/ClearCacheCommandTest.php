<?php

namespace Nusantara\Tests\Feature;

use Nusantara\Tests\TestCase;

class ClearCacheCommandTest extends TestCase
{
    public function test_clear_cache_command_runs_successfully(): void
    {
        $this->artisan('nusantara:clear-cache')
            ->assertSuccessful();
    }
}
