<?php

namespace App\Services\Backup;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Writes a complete, gzip-compressed SQL dump (structure + indexes + data of
 * every base table) using only PHP and the existing database connection, so
 * no `mysqldump` binary is needed on the host.
 *
 * Everything runs inside one read transaction: InnoDB serves plain SELECTs
 * from a consistent snapshot without taking row locks, so operational writes
 * keep flowing while the dump runs. Rows are streamed in batches and never
 * held in memory all at once.
 */
class DatabaseDumper
{
    private const ROWS_PER_INSERT = 500;

    private const DRIVERS = ['mysql', 'sqlite'];

    public function dumpTo(string $path): void
    {
        if (! function_exists('gzopen')) {
            throw new BackupStepException('The zlib extension is not available on the server.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, self::DRIVERS, true)) {
            throw new BackupStepException("Driver database [{$driver}] belum didukung untuk backup.");
        }

        $handle = gzopen($path, 'wb9');

        if ($handle === false) {
            throw new BackupStepException('Could not create a temporary backup file.');
        }

        try {
            $connection->transaction(function () use ($connection, $driver, $handle) {
                $this->write($handle, $this->header($driver));

                foreach ($this->tables($connection, $driver) as $table) {
                    $this->writeTable($handle, $connection, $driver, $table);
                }

                $this->write($handle, $driver === 'mysql'
                    ? "SET FOREIGN_KEY_CHECKS=1;\n"
                    : "PRAGMA foreign_keys=ON;\n");
            });
        } finally {
            gzclose($handle);
        }
    }

    private function header(string $driver): string
    {
        $lines = [
            '-- FreeDOMS database backup',
            '-- Created: '.now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            '',
        ];

        // Foreign-key checks are off only for the duration of this file, so
        // table order does not matter when it is restored.
        $lines[] = $driver === 'mysql'
            ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n"
            : "PRAGMA foreign_keys=OFF;\n";

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private function tables(ConnectionInterface $connection, string $driver): array
    {
        if ($driver === 'mysql') {
            return array_values(array_map(
                fn ($row) => (string) array_values((array) $row)[0],
                $connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"),
            ));
        }

        return array_map(
            fn ($row) => $row->name,
            $connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite\\_%' ESCAPE '\\' ORDER BY name"),
        );
    }

    private function writeTable($handle, ConnectionInterface $connection, string $driver, string $table): void
    {
        $grammar = $connection->getQueryGrammar();
        $quoted = $grammar->wrap($table);

        $this->write($handle, "\n-- Tabel: {$table}\nDROP TABLE IF EXISTS {$quoted};\n");
        $this->write($handle, $this->createTableSql($connection, $driver, $table, $quoted).";\n");

        $this->writeRows($handle, $connection, $table, $quoted);

        // Secondary indexes are created after the data so the bulk insert is not slowed down.
        // MySQL's SHOW CREATE TABLE already includes them inline.
        if ($driver === 'sqlite') {
            foreach ($connection->select("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql IS NOT NULL", [$table]) as $index) {
                $this->write($handle, $index->sql.";\n");
            }
        }
    }

    private function createTableSql(ConnectionInterface $connection, string $driver, string $table, string $quoted): string
    {
        if ($driver === 'mysql') {
            $row = (array) $connection->selectOne("SHOW CREATE TABLE {$quoted}");

            return (string) ($row['Create Table'] ?? array_values($row)[1]);
        }

        $row = $connection->selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);

        return (string) $row->sql;
    }

    private function writeRows($handle, ConnectionInterface $connection, string $table, string $quoted): void
    {
        $query = $connection->table($table);

        // Batch by primary key when the table has an `id` column; otherwise
        // stream the cursor. Both avoid loading the whole table at once.
        $rows = $connection->getSchemaBuilder()->hasColumn($table, 'id')
            ? $query->lazyById(self::ROWS_PER_INSERT, 'id')
            : $query->cursor();

        $pdo = $connection->getPdo();
        $columnList = null;
        $tuples = [];

        foreach ($rows as $row) {
            $values = array_values((array) $row);
            $columnList ??= implode(', ', array_map(
                fn ($column) => $connection->getQueryGrammar()->wrap($column),
                array_keys((array) $row),
            ));

            $tuples[] = '('.implode(', ', array_map(fn ($value) => $this->literal($pdo, $value), $values)).')';

            if (count($tuples) >= self::ROWS_PER_INSERT) {
                $this->flushInsert($handle, $quoted, $columnList, $tuples);
                $tuples = [];
            }
        }

        if ($tuples !== []) {
            $this->flushInsert($handle, $quoted, $columnList, $tuples);
        }
    }

    private function flushInsert($handle, string $quoted, string $columnList, array $tuples): void
    {
        $this->write($handle, "INSERT INTO {$quoted} ({$columnList}) VALUES\n".implode(",\n", $tuples).";\n");
    }

    private function literal(PDO $pdo, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value) => (string) $value,
            is_float($value) => is_finite($value) ? var_export($value, true) : 'NULL',
            // Binary or non-UTF-8 data is written as a hex literal so it round-trips unchanged.
            is_string($value) && ! mb_check_encoding($value, 'UTF-8') => "X'".bin2hex($value)."'",
            default => $pdo->quote((string) $value),
        };
    }

    private function write($handle, string $sql): void
    {
        if (gzwrite($handle, $sql) === false) {
            throw new BackupStepException('Could not write the backup file.');
        }
    }
}
