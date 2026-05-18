<?php

use Maya\Platform\Database\PostgresFdwMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos resueltos del usuario para maya-logs, consumidos vía FDW
 * desde maya_auth.v_logs_user_permissions.
 *
 * Permite al middleware RequirePermission comprobar permisos concretos
 * (logs.update, logs.delete) sin llamadas inter-servicio en caliente.
 */
return new class extends Migration
{
    private const VIEW_NAME  = 'user_resolved_permissions';
    private const FDW_TABLE  = 'user_resolved_permissions_fdw';
    private const FDW_SERVER = 'maya_auth_user_permissions_server';

    public function up(): void
    {
        if (app()->environment('testing')) {
            DB::statement('
                CREATE TABLE IF NOT EXISTS ' . self::VIEW_NAME . ' (
                    user_id          VARCHAR(255) NOT NULL,
                    permission_slug  VARCHAR(191) NOT NULL,
                    PRIMARY KEY (user_id, permission_slug)
                )
            ');
            return;
        }

        $this->setupFdw();
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            DB::statement('DROP TABLE IF EXISTS ' . self::VIEW_NAME);
            return;
        }

        PostgresFdwMigration::dropViewOrTableInPublic(self::VIEW_NAME);
        PostgresFdwMigration::dropForeignTableIfExists(self::FDW_TABLE);
        PostgresFdwMigration::dropFdwServerAndUserMapping(self::FDW_SERVER);
    }

    private function setupFdw(): void
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            $host     = config('database.connections.pgsql.host');
            $port     = config('database.connections.pgsql.port');
            $database = config('database.fdw.user_permissions.database', 'maya_auth');
            $username = config('database.fdw.user_permissions.username', 'maya');
            $password = config('database.fdw.user_permissions.password', 'secret');
        } else {
            $host     = config('database.fdw.user_permissions.host');
            $port     = config('database.fdw.user_permissions.port');
            $database = config('database.fdw.user_permissions.database');
            $username = config('database.fdw.user_permissions.username');
            $password = config('database.fdw.user_permissions.password');
        }

        if (!PostgresFdwMigration::ensurePostgresFdwExtension('user permissions')) {
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

        $foreignColumnsSql = 'user_id VARCHAR(255), permission_slug VARCHAR(191)';
        $viewSelectSql     = 'user_id, permission_slug';

        PostgresFdwMigration::createForeignTableWithPassThroughView(
            self::VIEW_NAME,
            $foreignColumnsSql,
            $viewSelectSql,
            self::FDW_SERVER,
            'public',
            'v_logs_user_permissions',
        );
    }
};
