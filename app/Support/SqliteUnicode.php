<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Connection;
use PDO;

/**
 * Makes SQLite's LOWER() Unicode-aware.
 *
 * SQLite's built-in LOWER()/UPPER() and its LIKE fold ASCII only, unless the
 * build carries the ICU extension — the stock PHP build does not. So on
 * SQLite, `title LIKE '%пекарн%'` does not match "Пекарна Слънце", while on
 * MySQL's default utf8mb4 collation it does. The catalog search was written
 * against MySQL, CI only ever ran MySQL, and the difference therefore sat
 * unnoticed until the suite was run on both drivers.
 *
 * The fix registers a PHP implementation of lower() on each SQLite
 * connection. Registering it under the name the built-in already uses is
 * deliberate: a user-defined function takes precedence in SQLite, so every
 * query can say LOWER(col) and mean the same thing on both drivers. The
 * alternative — a differently-named function guarded by a driver check at
 * each call site — is the arrangement that produced this bug.
 *
 * This affects the test and local-development driver. Production runs MySQL,
 * where LOWER() is already Unicode-aware and nothing here applies.
 */
final class SqliteUnicode
{
    /**
     * Called for every established connection; a no-op unless it is SQLite.
     *
     * Registration is per-connection and does not survive a reconnect, which
     * is why this hangs off ConnectionEstablished rather than running once at
     * boot: Laravel re-fires that event when a dropped connection is remade.
     */
    public static function register(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();

        if (! $pdo instanceof PDO) {
            return; // a stand-in (e.g. a test double); leave it alone
        }

        // sqliteCreateFunction() is defined by pdo_sqlite, which is necessarily
        // loaded for the driver check above to have passed.

        $pdo->sqliteCreateFunction(
            'lower',
            static fn (?string $value): ?string => $value === null ? null : mb_strtolower($value, 'UTF-8'),
            1,
        );
    }
}
