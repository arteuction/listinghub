<?php

declare(strict_types=1);

namespace App\Services\Install;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDOException;
use Throwable;

/**
 * Tests a candidate DB connection and enforces the "empty database" rule.
 * On any failure it returns a safe, credential-free reason — the password is
 * never echoed into messages, logs, or exceptions surfaced to the user.
 */
class DatabaseTester
{
    /**
     * @param  array{connection:string,host:string,port:string,database:string,username:string,password:string}  $cfg
     * @return array{ok:bool,reason:?string}
     */
    public function test(array $cfg): array
    {
        $name = 'install_test';
        Config::set("database.connections.{$name}", [
            'driver' => $cfg['connection'],
            'host' => $cfg['host'],
            'port' => $cfg['port'],
            'database' => $cfg['database'],
            'username' => $cfg['username'],
            'password' => $cfg['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);

        try {
            DB::purge($name);
            $conn = DB::connection($name);
            $conn->getPdo();

            $tables = $this->listTables($conn, $cfg['connection']);
            // Ignore Laravel's own migration bookkeeping if a prior attempt left it.
            $foreign = array_filter($tables, fn ($t) => $t !== 'migrations');

            if (! empty($foreign)) {
                return ['ok' => false, 'reason' => 'The target database is not empty. Use a fresh, empty database.'];
            }

            return ['ok' => true, 'reason' => null];
        } catch (PDOException) {
            // Deliberately generic — never include credentials or the raw DSN.
            return ['ok' => false, 'reason' => 'Could not connect with the provided settings.'];
        } catch (Throwable) {
            return ['ok' => false, 'reason' => 'Database check failed.'];
        } finally {
            DB::purge($name);
        }
    }

    /**
     * List table names reliably. Laravel's schema introspection under-reports
     * some MySQL/MariaDB system schemas, so use SHOW TABLES there; other
     * drivers use the standard listing.
     *
     * @return list<string>
     */
    private function listTables(\Illuminate\Database\Connection $conn, string $driver): array
    {
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return array_map(
                fn ($row) => (string) array_values((array) $row)[0],
                $conn->select('SHOW TABLES'),
            );
        }

        return $conn->getSchemaBuilder()->getTableListing();
    }
}
