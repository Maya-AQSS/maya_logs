<?php

use Maya\Platform\Database\PostgresFdwMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de equipos (FDW global → Odoo / maya core).
 *
 * Mismo patrón que {@see maya_dms} 0001_00_00_000001_000000: cada app del
 * ecosistema proyecta `teams` localmente para que `/me` pueda enriquecer
 * el perfil sin depender de otra app.
 *
 * Rutas:
 * - `testing`:               tabla física `teams` (sin postgres_fdw).
 * - `local`:                 `teams_source` + foreign table + vista vía FDW.
 * - `staging`/`production`:  FDW remoto según `database.fdw.teams.*`.
 */
return new class extends Migration
{
    private const VIEW_NAME          = 'teams';
    private const FDW_TABLE          = 'teams_fdw';
    private const FDW_SERVER         = 'teams_server';
    private const LOCAL_SOURCE_TABLE = 'teams_source';

    private function isTestEnv(): bool
    {
        // Detect testing via DB name as fallback: APP_ENV may be 'local' inside
        // the container while phpunit overrides DB_DATABASE → maya_*_test.
        if (app()->environment('testing')) {
            return true;
        }
        $db = config('database.connections.pgsql.database');
        return is_string($db) && str_ends_with($db, '_test');
    }

    public function up(): void
    {
        if ($this->isTestEnv()) {
            $this->createTestingTable();
            return;
        }

        $this->setupFdw();
    }

    public function down(): void
    {
        if ($this->isTestEnv()) {
            DB::statement('DROP TABLE IF EXISTS '.self::VIEW_NAME);
            return;
        }

        PostgresFdwMigration::dropViewOrTableInPublic(self::VIEW_NAME);
        PostgresFdwMigration::dropForeignTableIfExists(self::FDW_TABLE);
        PostgresFdwMigration::dropFdwServerAndUserMapping(self::FDW_SERVER);

        if (app()->environment('local')) {
            DB::statement('DROP TABLE IF EXISTS '.self::LOCAL_SOURCE_TABLE);
        }
    }

    private function createTestingTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS teams (
                id              VARCHAR(255) PRIMARY KEY,
                name            VARCHAR(255) NOT NULL,
                description     TEXT,
                owner_id        VARCHAR(255),
                is_department   BOOLEAN NOT NULL DEFAULT FALSE,
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at      TIMESTAMP NULL
            )
        ');
    }

    private function setupFdw(): void
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            $this->createLocalSourceTable();

            $host     = config('database.connections.pgsql.host');
            $port     = config('database.connections.pgsql.port');
            $database = config('database.connections.pgsql.database');
            $username = config('database.connections.pgsql.username');
            $password = config('database.connections.pgsql.password');
            $schema   = 'public';
            $source   = self::LOCAL_SOURCE_TABLE;
        } else {
            $host     = config('database.fdw.teams.host');
            $port     = config('database.fdw.teams.port');
            $database = config('database.fdw.teams.database');
            $username = config('database.fdw.teams.username');
            $password = config('database.fdw.teams.password');
            $schema   = config('database.fdw.teams.schema', 'public');
            $source   = config('database.fdw.teams.table', 'teams');
        }

        if (! PostgresFdwMigration::ensurePostgresFdwExtension('teams catalog')) {
            return;
        }

        PostgresFdwMigration::createFdwServerWithUserMapping(
            self::FDW_SERVER,
            (string) $host,
            (string) $port,
            (string) $database,
            (string) $username,
            (string) $password,
        );

        $foreignColumnsSql = 'id VARCHAR(255), name VARCHAR(255), description TEXT, owner_id VARCHAR(255), '
            .'is_department BOOLEAN, created_at TIMESTAMP, updated_at TIMESTAMP, deleted_at TIMESTAMP';

        $viewSelectSql = 'id, name, description, owner_id, is_department, created_at, updated_at, deleted_at';

        PostgresFdwMigration::createForeignTableWithPassThroughView(
            self::VIEW_NAME,
            $foreignColumnsSql,
            $viewSelectSql,
            self::FDW_SERVER,
            (string) $schema,
            (string) $source,
        );
    }

    private function createLocalSourceTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS teams_source (
                id              VARCHAR(255) PRIMARY KEY,
                name            VARCHAR(255) NOT NULL,
                description     TEXT,
                owner_id        VARCHAR(255),
                is_department   BOOLEAN NOT NULL DEFAULT FALSE,
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at      TIMESTAMP NULL
            )
        ');
    }
};
