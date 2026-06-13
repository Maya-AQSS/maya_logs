<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maya\Profile\Enums\Locale;
use Maya\Profile\Repositories\Resolvers\FdwAcademicResolver;

uses(RefreshDatabase::class);

/**
 * Contrato del resolver de perfil enriquecido para maya_logs.
 *
 * Verifica que `FdwAcademicResolver` (en el paquete shared
 * `maya-shared-profile-laravel`) lee de las FDW locales que cada app del
 * ecosistema proyecta (mismas vistas que `maya_dms`) y devuelve un
 * `UserProfileDto` con los campos canónicos cross-app:
 *
 *  - `permissions`        (de `user_resolved_permissions`)
 *  - `study_type_ids`     (de `user_study_types`)
 *  - `study_ids`          (de `user_studies`)
 *  - `module_ids`         (de `user_course_modules`)
 *  - `team_ids`           (de `team_members`)
 *  - `teams`              (JOIN `team_members` × `teams`)
 *
 * El test fuerza datos en las tablas testing (stubs sin FDW) y comprueba
 * el shape del DTO devuelto. Degradación silenciosa: tablas vacías →
 * arrays vacíos, nunca null.
 */

beforeEach(function () {
    Cache::flush();
    $this->resolver = new FdwAcademicResolver();
    $this->userId   = 'usr_resolver_test';
});

it('returns empty arrays when no local data exists', function () {
    $dto = $this->resolver->resolve($this->userId, [
        'id'    => $this->userId,
        'email' => 'jwt@example.com',
        'name'  => 'JWT User',
    ]);

    expect($dto->id)->toBe($this->userId);
    expect($dto->email)->toBe('jwt@example.com');
    expect($dto->name)->toBe('JWT User');
    expect($dto->locale)->toEqual(Locale::default());

    expect($dto->extra['permissions'] ?? null)->toBe([]);
    expect($dto->extra['study_type_ids'] ?? null)->toBe([]);
    expect($dto->extra['study_ids'] ?? null)->toBe([]);
    expect($dto->extra['module_ids'] ?? null)->toBe([]);
    expect($dto->extra['team_ids'] ?? null)->toBe([]);
    expect($dto->extra['teams'] ?? null)->toBeNull();
});

it('enriches dto with academic data from fdw stubs', function () {
    DB::table('user_resolved_permissions')->insert([
        ['user_id' => $this->userId, 'permission_slug' => 'audit.read'],
        ['user_id' => $this->userId, 'permission_slug' => 'audit.export'],
        ['user_id' => 'other_user',  'permission_slug' => 'audit.delete'],
    ]);

    DB::table('user_study_types')->insert([
        ['id' => 'ust-1', 'user_id' => $this->userId, 'study_type_id' => 'ST_ESPA'],
        ['id' => 'ust-2', 'user_id' => $this->userId, 'study_type_id' => 'ST_BACH'],
        ['id' => 'ust-3', 'user_id' => 'other_user',   'study_type_id' => 'ST_FP'],
    ]);

    DB::table('user_studies')->insert([
        ['id' => 'us-1', 'user_id' => $this->userId, 'study_id' => 'S_ESPA'],
        ['id' => 'us-2', 'user_id' => 'other_user',   'study_id' => 'S_BACH'],
    ]);

    DB::table('user_course_modules')->insert([
        ['id' => 'um-1', 'user_id' => $this->userId, 'module_id' => 'M_MAT_1'],
        ['id' => 'um-2', 'user_id' => $this->userId, 'module_id' => 'M_ENG_1'],
    ]);

    DB::table('teams')->insert([
        ['id' => 'T1', 'name' => 'Equipo Calidad',    'description' => 'QA', 'is_department' => false],
        ['id' => 'T2', 'name' => 'Departamento ESPA', 'description' => null, 'is_department' => true],
        ['id' => 'T3', 'name' => 'Otro Equipo',       'description' => null, 'is_department' => false],
    ]);

    DB::table('team_members')->insert([
        ['id' => 'tm-1', 'team_id' => 'T1', 'user_id' => $this->userId, 'role' => 'member'],
        ['id' => 'tm-2', 'team_id' => 'T2', 'user_id' => $this->userId, 'role' => 'lead'],
        ['id' => 'tm-3', 'team_id' => 'T3', 'user_id' => 'other_user',   'role' => 'member'],
    ]);

    $dto = $this->resolver->resolve($this->userId, ['id' => $this->userId]);

    $permissions = $dto->extra['permissions'];
    sort($permissions);
    expect($permissions)->toBe(['audit.export', 'audit.read']);

    $studyTypeIds = $dto->extra['study_type_ids'];
    sort($studyTypeIds);
    expect($studyTypeIds)->toBe(['ST_BACH', 'ST_ESPA']);

    expect($dto->extra['study_ids'])->toBe(['S_ESPA']);

    $moduleIds = $dto->extra['module_ids'];
    sort($moduleIds);
    expect($moduleIds)->toBe(['M_ENG_1', 'M_MAT_1']);

    // loadTeamIdsSplit separa equipos (no-departamento) de departamentos:
    // T1 (is_department=false) → team_ids; T2 (is_department=true) → department_ids.
    expect($dto->extra['team_ids'])->toBe(['T1']);
    expect($dto->extra['department_ids'])->toBe(['T2']);

    // teams[] (objetos detallados) es exclusivo de maya_dms; el resto de apps
    // solo exponen team_ids (ver AcademicDataReader: "El resto de apps NO
    // incluyen teams[] en /me"). Por eso no se asume aquí.
    expect($dto->extra['teams'] ?? null)->toBeNull();
});

it('filters strictly by user_id and never returns other users data', function () {
    DB::table('user_study_types')->insert([
        ['id' => 'ust-other', 'user_id' => 'other_user', 'study_type_id' => 'ST_FP'],
    ]);
    DB::table('team_members')->insert([
        ['id' => 'tm-other', 'team_id' => 'T_OTHER', 'user_id' => 'other_user', 'role' => 'member'],
    ]);
    DB::table('teams')->insert([
        ['id' => 'T_OTHER', 'name' => 'Otro', 'description' => null, 'is_department' => false],
    ]);

    $dto = $this->resolver->resolve($this->userId, ['id' => $this->userId]);

    expect($dto->extra['study_type_ids'])->toBe([]);
    expect($dto->extra['team_ids'])->toBe([]);
    expect($dto->extra['teams'] ?? null)->toBeNull();
});

it('returns empty arrays for empty user id', function () {
    $dto = $this->resolver->resolve('', []);

    expect($dto->extra['permissions'])->toBe([]);
    expect($dto->extra['study_type_ids'])->toBe([]);
    expect($dto->extra['study_ids'])->toBe([]);
    expect($dto->extra['module_ids'])->toBe([]);
    expect($dto->extra['team_ids'])->toBe([]);
    expect($dto->extra['teams'] ?? null)->toBeNull();
});
