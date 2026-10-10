<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostMediaQueryCompatibilityTest extends TestCase
{
    public function test_media_scope_compiles_json_array_length_for_supported_database_drivers(): void
    {
        $drivers = [
            'sqlite' => 'json_array_length',
            'mysql' => 'json_length',
            'mariadb' => 'json_length',
            'pgsql' => 'jsonb_array_length',
            'sqlsrv' => 'openjson',
        ];

        foreach ($drivers as $driver => $expectedSql) {
            $connectionName = 'media_scope_'.$driver;
            $connectionConfig = config('database.connections.sqlite');
            $connectionConfig['driver'] = $driver;
            $connectionConfig['database'] = $driver === 'sqlite' ? ':memory:' : 'compile_only';
            $connectionConfig['host'] = '127.0.0.1';
            $connectionConfig['username'] = '';
            $connectionConfig['password'] = '';
            $connectionConfig['port'] = match ($driver) {
                'mysql', 'mariadb' => 3306,
                'pgsql' => 5432,
                'sqlsrv' => 1433,
                default => null,
            };
            $connectionConfig['charset'] = match ($driver) {
                'mysql', 'mariadb' => 'utf8mb4',
                'pgsql', 'sqlsrv' => 'utf8',
                default => null,
            };
            $connectionConfig['collation'] = $driver === 'mysql' || $driver === 'mariadb'
                ? 'utf8mb4_unicode_ci'
                : null;
            config(['database.connections.'.$connectionName => $connectionConfig]);
            DB::purge($connectionName);

            try {
                $sql = strtolower(Post::on($connectionName)->whereHasMedia()->toSql());

                $this->assertStringContainsString($expectedSql, $sql, "Unexpected JSON length grammar for {$driver}.");
            } finally {
                DB::purge($connectionName);
            }
        }
    }

    public function test_poll_and_pin_migration_compiles_with_sql_server_delete_actions(): void
    {
        $connectionName = 'migration_sqlsrv_compatibility';
        $connectionConfig = config('database.connections.sqlite');
        $connectionConfig['driver'] = 'sqlsrv';
        $connectionConfig['database'] = 'compile_only';
        $connectionConfig['host'] = '127.0.0.1';
        $connectionConfig['username'] = '';
        $connectionConfig['password'] = '';
        $connectionConfig['port'] = 1433;
        $connectionConfig['charset'] = 'utf8';
        config(['database.connections.'.$connectionName => $connectionConfig]);
        DB::purge($connectionName);

        $previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection($connectionName);

        try {
            $migration = require database_path('migrations/2026_10_08_000001_add_polls_and_pins_to_posts.php');
            $statements = DB::connection($connectionName)->pretend(static function () use ($migration): void {
                $migration->up();
            });
            $sql = strtolower(implode("\n", array_column($statements, 'query')));
            $voteUserForeignKey = collect($statements)
                ->first(static fn (array $statement): bool => str_contains($statement['query'], 'post_poll_votes_user_id_foreign'));

            $this->assertStringContainsString('user_pinned_posts', $sql);
            $this->assertStringNotContainsString('on delete restrict', $sql);
            $this->assertStringNotContainsString('post_poll_votes_poll_id_foreign', $sql);
            $this->assertIsArray($voteUserForeignKey);
            $this->assertStringNotContainsString('on delete cascade', strtolower($voteUserForeignKey['query']));
        } finally {
            DB::setDefaultConnection($previousConnection);
            DB::purge($connectionName);
        }
    }
}
