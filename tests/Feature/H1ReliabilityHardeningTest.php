<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class H1ReliabilityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_connection_uses_a_nonzero_busy_timeout(): void
    {
        $this->assertSame(5000, config('database.connections.sqlite.busy_timeout'));
        $this->assertSame(5000, (int) DB::selectOne('PRAGMA busy_timeout')->timeout);
    }

}
