<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FallbackConcurrencyDatabase
{
    public const CONNECTION =
        'fallback_concurrency';

    public const DEFAULT_DATABASE =
        'db_siagapasok_concurrency_test';

    private const PRODUCTION_DATABASE =
        'db_siagapasok';

    public static function isConfigured(): bool
    {
        $enabled =
            getenv(
                'SIAGAPASOK_REAL_DB_CONCURRENCY'
            );

        if ($enabled === false) {
            return false;
        }

        return filter_var(
            $enabled,
            FILTER_VALIDATE_BOOL
        );
    }

    public static function configure(): void
    {
        $database =
            self::databaseName();

        /*
         * Absolute safety boundary.
         *
         * migrate:fresh tidak pernah boleh
         * diarahkan ke database utama.
         */
        if (
            strtolower($database)
            === strtolower(
                self::PRODUCTION_DATABASE
            )
        ) {
            throw new RuntimeException(
                'Concurrency test menolak database '
                .'utama db_siagapasok.'
            );
        }

        /*
         * Dedicated testing database harus jelas
         * terlihat sebagai database test.
         */
        if (
            ! preg_match(
                '/test|testing|concurrency/i',
                $database
            )
        ) {
            throw new RuntimeException(
                'Database concurrency wajib '
                .'mengandung "test", "testing", '
                .'atau "concurrency" pada namanya.'
            );
        }

        /*
         * Ambil host, port, username, password,
         * charset dan sslmode dari PostgreSQL
         * connection existing.
         *
         * DB_DATABASE tidak dipakai karena
         * phpunit.xml mengoverride-nya menjadi
         * :memory: untuk regular SQLite tests.
         */
        $postgres =
            config(
                'database.connections.pgsql'
            );

        if (
            ! is_array(
                $postgres
            )
        ) {
            throw new RuntimeException(
                'PostgreSQL connection tidak ditemukan '
                .'di config/database.php.'
            );
        }

        $connection = [
            ...$postgres,

            'driver' =>
                'pgsql',

            'database' =>
                $database,
        ];

        config([
            'database.default' =>
                self::CONNECTION,

            'database.connections.'
            .self::CONNECTION =>
                $connection,
        ]);

        DB::purge(
            self::CONNECTION
        );

        DB::setDefaultConnection(
            self::CONNECTION
        );

        /*
         * Fail immediately jika:
         * - pdo_pgsql tidak tersedia;
         * - PostgreSQL mati;
         * - credential salah;
         * - database test belum dibuat.
         */
        DB::connection(
            self::CONNECTION
        )->getPdo();

        $driver =
            DB::connection(
                self::CONNECTION
            )->getDriverName();

        if ($driver !== 'pgsql') {
            throw new RuntimeException(
                'Real concurrency gate wajib '
                .'berjalan pada PostgreSQL.'
            );
        }

        $connectedDatabase =
            DB::connection(
                self::CONNECTION
            )->getDatabaseName();

        if (
            $connectedDatabase
            !== $database
        ) {
            throw new RuntimeException(
                'Concurrency connection terhubung '
                .'ke database yang tidak diharapkan.'
            );
        }
    }

    public static function databaseName(): string
    {
        $configured =
            getenv(
                'SIAGAPASOK_CONCURRENCY_DB_DATABASE'
            );

        if (
            $configured === false
            || trim(
                (string) $configured
            ) === ''
        ) {
            return self::DEFAULT_DATABASE;
        }

        return trim(
            (string) $configured
        );
    }
}