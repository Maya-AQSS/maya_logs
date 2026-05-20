<?php

use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class)->in('Feature');

function seedLogsLoginForTests(): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id'         => test()->userId,
        'permission_slug' => 'logs.login',
    ]);
}

function grantLogsPermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id'         => test()->userId,
        'permission_slug' => $slug,
    ]);
}

/** Permisos habituales para tests de controladores (middleware + policies vía FDW local). */
function grantLogsControllerPermissions(): void
{
    seedLogsLoginForTests();
    foreach ([
        'logs.index',
        'logs.show',
        'logs.update',
        'archived-logs.update',
        'archived-logs.delete',
        'archived-logs.comment.delete',
    ] as $slug) {
        grantLogsPermission($slug);
    }
}
