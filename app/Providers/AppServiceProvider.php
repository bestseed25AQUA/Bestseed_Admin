<?php

namespace App\Providers;

use App\Database\ResilientMySqlConnection;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ── DB resilience guard ──
        // Resolve the 'mysql' driver to a connection that recognises the host's
        // transient "[2002] Operation not permitted" refusal as a lost
        // connection, so Laravel auto-reconnects and retries the query once
        // instead of 500-ing the whole request (including Sanctum auth).
        // Only affects the mysql driver — sqlite (tests) is untouched.


        
        Connection::resolverFor('mysql', function ($pdo, $database = '', $tablePrefix = '', array $config = []) {
            return new ResilientMySqlConnection($pdo, $database, $tablePrefix, $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix for older MySQL versions
        Schema::defaultStringLength(191);
    }
}
