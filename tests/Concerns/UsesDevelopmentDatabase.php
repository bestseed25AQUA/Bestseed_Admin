<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Run a test against the development MySQL database, inside a transaction that
 * is rolled back afterwards.
 *
 * Rules that turn on real date columns, real unique indexes or MySQL-only SQL
 * cannot be proved against an in-memory SQLite schema built from scratch. These
 * tests therefore use the real one and leave it exactly as they found it.
 *
 * Call [beginDevelopmentDatabase] from setUp and [rollBackDevelopmentDatabase]
 * from tearDown.
 */
trait UsesDevelopmentDatabase
{
    /** True once a transaction has actually been opened, so tearDown is safe. */
    private bool $developmentTransactionOpen = false;

    protected function beginDevelopmentDatabase(): void
    {
        $this->useDevelopmentDatabase();

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Needs the development MySQL database.');
        }

        DB::beginTransaction();
        $this->developmentTransactionOpen = true;
    }

    protected function rollBackDevelopmentDatabase(): void
    {
        // Guarded: a test skipped in setUp never opened one, and rolling back
        // a transaction that does not exist throws over the top of the real
        // reason the test was skipped.
        if (!$this->developmentTransactionOpen) {
            return;
        }

        DB::rollBack();
        $this->developmentTransactionOpen = false;
    }

    /**
     * Point the default connection at the developer's MySQL database, reading
     * the credentials straight out of .env.
     *
     * Deliberately file-based: phpunit.xml's `<env>` entries set
     * DB_CONNECTION=sqlite AND DB_DATABASE=:memory:, and that second one lands
     * on every connection. Switching the default to mysql through config alone
     * would hand it ":memory:" as a schema name, so env() and config() are both
     * useless here.
     */
    private function useDevelopmentDatabase(): void
    {
        $path = base_path('.env');

        if (!is_file($path)) {
            $this->markTestSkipped('No .env to read development database credentials from.');
        }

        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, " \t\"'");
        }

        if (($values['DB_CONNECTION'] ?? 'mysql') !== 'mysql' || empty($values['DB_DATABASE'])) {
            $this->markTestSkipped('Development environment is not on MySQL.');
        }

        config([
            'database.default'                    => 'mysql',
            'database.connections.mysql.host'     => $values['DB_HOST'] ?? '127.0.0.1',
            'database.connections.mysql.port'     => $values['DB_PORT'] ?? '3306',
            'database.connections.mysql.database' => $values['DB_DATABASE'],
            'database.connections.mysql.username' => $values['DB_USERNAME'] ?? 'root',
            'database.connections.mysql.password' => $values['DB_PASSWORD'] ?? '',
        ]);

        // The connection may already have been resolved with the SQLite
        // values; purge it so the next use picks these up.
        DB::purge('mysql');
    }
}
