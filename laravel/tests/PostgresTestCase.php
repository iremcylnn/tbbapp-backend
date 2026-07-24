<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use PDOException;

/**
 * Base class for tests that need the real PostgreSQL test database
 * (tbbapp_laravel_test — connection details in phpunit.xml).
 *
 * While PostgreSQL isn't installed yet, these tests SKIP instead of erroring,
 * so the rest of the suite stays green. The moment the database exists they
 * run automatically — no code change needed.
 */
abstract class PostgresTestCase extends TestCase
{
    use RefreshDatabase;

    private static ?bool $postgresAvailable = null;

    protected function setUp(): void
    {
        if (! self::postgresAvailable()) {
            $this->markTestSkipped(
                'PostgreSQL test database unreachable (start db17 / install PostgreSQL 17; see docker-compose.yml).'
            );
        }

        parent::setUp();
    }

    private static function postgresAvailable(): bool
    {
        if (self::$postgresAvailable !== null) {
            return self::$postgresAvailable;
        }

        try {
            new PDO(
                sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=2',
                    getenv('DB_HOST') ?: '127.0.0.1',
                    getenv('DB_PORT') ?: '5432',
                    getenv('DB_DATABASE') ?: 'tbbapp_laravel_test',
                ),
                getenv('DB_USERNAME') ?: 'tbbapp',
                getenv('DB_PASSWORD') ?: 'tbbapp_dev_password',
            );

            return self::$postgresAvailable = true;
        } catch (PDOException) {
            return self::$postgresAvailable = false;
        }
    }
}
