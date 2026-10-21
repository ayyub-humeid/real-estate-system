<?php

namespace App\Database;

use DateTimeInterface;
use Illuminate\Database\PostgresConnection as BasePostgresConnection;

/**
 * Keeps boolean bindings portable for PostgreSQL connections, including the
 * direct Neon connection that uses emulated prepares during deploys.
 */
class PostgresConnection extends BasePostgresConnection
{
    public function prepareBindings(array $bindings): array
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                // PostgreSQL boolean columns do not accept the integer values
                // Laravel's base Connection normally binds for booleans.
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return $bindings;
    }
}
