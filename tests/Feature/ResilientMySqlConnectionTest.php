<?php

namespace Tests\Feature;

use App\Database\ResilientMySqlConnection;
use Illuminate\Database\QueryException;
use PDOException;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * The host refuses new MySQL sockets with
 * "SQLSTATE[HY000] [2002] Operation not permitted" when too many connections
 * are opened at once. That happens on a notification cold-start: splash init,
 * profile, banners, bookings and the tapped screen all connect within the same
 * second, and the request that loses the race 500s — including the Sanctum
 * personal_access_tokens lookup, which runs before any controller.
 *
 * These tests pin the two properties that make the retry safe:
 *   • a connection refusal IS recognised as retryable
 *   • a real SQL error is NOT, so genuine bugs still surface immediately
 */
class ResilientMySqlConnectionTest extends TestCase
{
    private function connection(): ResilientMySqlConnection
    {
        return new ResilientMySqlConnection(new \PDO('sqlite::memory:'), 'test');
    }

    private function causedByLostConnection(\Throwable $e): bool
    {
        $method = new ReflectionMethod(ResilientMySqlConnection::class, 'causedByLostConnection');
        $method->setAccessible(true);

        return $method->invoke($this->connection(), $e);
    }

    public static function refusalMessages(): array
    {
        return [
            'the exact error from production' => [
                'SQLSTATE[HY000] [2002] Operation not permitted (Connection: mysql, '
                . 'Host: 127.0.0.1, Port: 3306, Database: u997602530_bestseed_db, '
                . 'SQL: select * from `personal_access_tokens` where `id` = 1867 limit 1)',
            ],
            'bare 2002' => ['SQLSTATE[HY000] [2002] Operation not permitted'],
            'connection refused' => ['SQLSTATE[HY000] [2002] Connection refused'],
            'connection timed out' => ['SQLSTATE[HY000] [2002] Connection timed out'],
            'windows wording' => ['No connection could be made because the target machine actively refused it'],
        ];
    }

    /** @dataProvider refusalMessages */
    public function test_a_connection_refusal_is_treated_as_retryable(string $message): void
    {
        $this->assertTrue(
            $this->causedByLostConnection(new PDOException($message)),
            "Should retry after: {$message}"
        );
    }

    public static function realSqlErrors(): array
    {
        return [
            'unknown column' => ["SQLSTATE[42S22]: Column not found: 1054 Unknown column 'foo'"],
            'table missing' => ["SQLSTATE[42S02]: Base table or view not found: 1146 Table 'x' doesn't exist"],
            'duplicate key' => ["SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry"],
            'syntax error' => ["SQLSTATE[42000]: Syntax error or access violation: 1064"],
        ];
    }

    /**
     * A real SQL bug must fail fast. Retrying it would turn one clear error
     * into several seconds of silence and still fail.
     *
     * @dataProvider realSqlErrors
     */
    public function test_a_genuine_sql_error_is_not_retried(string $message): void
    {
        $this->assertFalse(
            $this->causedByLostConnection(new PDOException($message)),
            "Should NOT retry after: {$message}"
        );
    }

    public function test_it_retries_more_than_laravels_single_attempt(): void
    {
        $property = new ReflectionProperty(ResilientMySqlConnection::class, 'retryBackoffMicroseconds');
        $property->setAccessible(true);
        $backoff = $property->getValue($this->connection());

        $this->assertGreaterThanOrEqual(
            2,
            count($backoff),
            'One retry lands inside the same connection burst — several are needed.'
        );

        // Each wait must be longer than the last, so later attempts land after
        // the burst has drained rather than hammering the host.
        $previous = 0;
        foreach ($backoff as $wait) {
            $this->assertGreaterThan($previous, $wait, 'Backoff must increase.');
            $previous = $wait;
        }

        // A refused request should not hang the phone for seconds on end.
        $this->assertLessThan(
            3_000_000,
            array_sum($backoff),
            'Total added latency must stay under 3 seconds.'
        );
    }

    public function test_the_override_matches_laravels_signature(): void
    {
        // A mismatched signature would fatal at runtime rather than in tests.
        $ours = new ReflectionMethod(ResilientMySqlConnection::class, 'run');
        $theirs = new ReflectionMethod(\Illuminate\Database\Connection::class, 'run');

        $this->assertSame(
            $theirs->getNumberOfParameters(),
            $ours->getNumberOfParameters(),
            'run() must keep the parent signature.'
        );
        $this->assertTrue($ours->isProtected());
    }
}
