<?php

namespace App\Database;

use Closure;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * MySQL connection that treats the host's transient connection-limit error
 * ("SQLSTATE[HY000] [2002] Operation not permitted") 
 * //as a *lost connection*.
 *
 * Laravel's Connection::run() already reconnects and retries a query ONCE when
 * causedByLostConnection() returns true (and we're not inside a transaction).
 * The shared-hosting MySQL on staging intermittently refuses new sockets with
 * [2002], which by default is NOT recognised as a lost connection — so the very
 * first query of a request (including the Sanctum personal_access_tokens lookup
 * in auth middleware) throws a 500 and the whole request fails.
 *
 * By widening the lost-connection detection to include [2002]/"Operation not
 * permitted", a transient refusal now triggers Laravel's built-in reconnect +
 * single retry, which usually lands on a healthy connection. If the DB is truly
 * down the retry still fails and the original error surfaces — no behaviour
 * change in that case.
 */
class ResilientMySqlConnection extends MySqlConnection
{
    /**
     * Additional driver/host messages that indicate a transient connection
     * failure on this host, on top of Laravel's built-in list.
     */
    protected array $extraLostConnectionMessages = [
        'SQLSTATE[HY000] [2002]',
        '[2002] Operation not permitted',
        'Operation not permitted',
        'No connection could be made',
        'Connection refused',
        'Connection timed out',
    ];

    protected function causedByLostConnection(Throwable $e)
    {
        if (parent::causedByLostConnection($e)) {
            return true;
        }

        return Str::contains($e->getMessage(), $this->extraLostConnectionMessages);
    }

    /**
     * Extra reconnect attempts on top of Laravel's single built-in retry, with
     * the pause before each (microseconds).
     *
     * One retry is not enough when the app cold-starts from a notification tap:
     * splash init, profile, banners, bookings and the tapped screen all open
     * their own MySQL connection within the same second, and shared hosting
     * refuses the ones over its cap. Both of Laravel's attempts land inside
     * that same burst. Waiting a little lets the burst drain.
     */
    protected array $retryBackoffMicroseconds = [150_000, 400_000, 900_000];

    /**
     * Run a query, retrying a few more times when the host refuses the socket.
     *
     * Only connection-level failures are retried — [2002] and friends mean the
     * query never reached the server, so re-running it cannot duplicate work.
     * Never retries inside a transaction: reconnecting would silently drop it.
     * When the database is genuinely down every attempt fails and the original
     * error surfaces unchanged.
     */
    protected function run($query, $bindings, Closure $callback)
    {
        $attempt = 0;

        while (true) {
            try {
                return parent::run($query, $bindings, $callback);
            } catch (QueryException $e) {
                $isLastAttempt = $attempt >= count($this->retryBackoffMicroseconds);

                if (
                    $isLastAttempt
                    || $this->transactions > 0
                    || !$this->causedByLostConnection($e->getPrevious() ?? $e)
                ) {
                    throw $e;
                }

                usleep($this->retryBackoffMicroseconds[$attempt]);
                $attempt++;

                $this->reconnect();
            }
        }
    }
}
