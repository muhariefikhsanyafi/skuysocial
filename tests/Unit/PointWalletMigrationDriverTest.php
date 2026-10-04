<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PointWalletMigrationDriverTest extends TestCase
{
    public function test_wallet_migration_rejects_drivers_without_point_invariant_constraints(): void
    {
        $database = \Mockery::mock();
        $database->shouldReceive('getDriverName')
            ->once()
            ->andReturn('pgsql');
        DB::swap($database);

        $schema = \Mockery::mock();
        $schema->shouldNotReceive('create');
        Schema::swap($schema);
        $migration = require database_path('migrations/2026_10_04_000001_create_point_wallets_and_transactions.php');

        $this->expectException(RuntimeException::class);
        $migration->up();
    }
}
