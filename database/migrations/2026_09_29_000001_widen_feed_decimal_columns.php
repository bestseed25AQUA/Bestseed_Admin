<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feed figures were capped at 99,999,999.99 by decimal(10,2).
 *
 * A farmer entering nine digits of "feed already used" overflowed
 * `tanks.total_feed_used`, which threw inside the backfill's transaction and
 * rolled every generated row back — so the tank ended up with no history and a
 * total of zero, silently. Widened to decimal(14,2).
 */
return new class extends Migration
{
    /** table => [columns], all currently decimal(10,2) or decimal(12,2). */
    private const TARGETS = [
        'tanks'               => ['total_feed_used'],
        'feeds'               => ['feed_quantity'],
        'tank_feed_histories' => ['feed_quantity'],
        'farms'               => ['total_feed_used', 'feed_used_before'],
        'tank_batches'        => ['feed_used_before', 'harvest_quantity'],
    ];

    public function up(): void
    {
        $this->setPrecision('DECIMAL(14,2)');
    }

    public function down(): void
    {
        $this->setPrecision('DECIMAL(10,2)');
    }

    /**
     * Raw ALTER rather than Blueprint->change(): changing a decimal through
     * the schema builder drops the column's nullability and default unless
     * every attribute is restated, and these differ column to column.
     */
    private function setPrecision(string $type): void
    {
        foreach (self::TARGETS as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                $nullable = $this->isNullable($table, $column) ? 'NULL' : 'NOT NULL';
                $default  = $this->defaultClause($table, $column);

                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} {$nullable}{$default}");
            }
        }
    }

    private function column(string $table, string $column): ?object
    {
        return DB::selectOne(
            'SELECT IS_NULLABLE, COLUMN_DEFAULT
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    private function isNullable(string $table, string $column): bool
    {
        return ($this->column($table, $column)->IS_NULLABLE ?? 'YES') === 'YES';
    }

    private function defaultClause(string $table, string $column): string
    {
        $default = $this->column($table, $column)->COLUMN_DEFAULT ?? null;

        // MariaDB reports a nullable column with no default as the literal
        // string "NULL", which fed back into the ALTER is invalid for a
        // decimal.
        if ($default === null || strcasecmp((string) $default, 'NULL') === 0) {
            return '';
        }

        return " DEFAULT '{$default}'";
    }
};
