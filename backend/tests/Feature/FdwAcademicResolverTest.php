<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maya\Profile\Enums\Locale;
use Maya\Profile\Repositories\Resolvers\FdwAcademicResolver;
use Tests\TestCase;

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
class FdwAcademicResolverTest extends TestCase
{
    use RefreshDatabase;

    private const USER_ID = 'usr_resolver_test';

    private FdwAcademicResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->resolver = new FdwAcademicResolver();
    }

    public function test_resolve_returns_empty_arrays_when_no_local_data(): void
    {
        $dto = $this->resolver->resolve(self::USER_ID, [
            'id'    => self::USER_ID,
            'email' => 'jwt@example.com',
            'name'  => 'JWT User',
        ]);

        $this->assertSame(self::USER_ID, $dto->id);
        $this->assertSame('jwt@example.com', $dto->email);
        $this->assertSame('JWT User', $dto->name);
        $this->assertEquals(Locale::default(), $dto->locale);

        $this->assertSame([], $dto->extra['permissions'] ?? null);
        $this->assertSame([], $dto->extra['study_type_ids'] ?? null);
        $this->assertSame([], $dto->extra['study_ids'] ?? null);
        $this->assertSame([], $dto->extra['module_ids'] ?? null);
        $this->assertSame([], $dto->extra['team_ids'] ?? null);
        $this->assertSame([], $dto->extra['teams'] ?? null);
    }

    public function test_resolve_enriches_with_academic_data_from_fdw_stubs(): void
    {
        DB::table('user_resolved_permissions')->insert([
            ['user_id' => self::USER_ID, 'permission_slug' => 'audit.read'],
            ['user_id' => self::USER_ID, 'permission_slug' => 'audit.export'],
            ['user_id' => 'other_user',  'permission_slug' => 'audit.delete'],
        ]);

        DB::table('user_study_types')->insert([
            ['id' => 'ust-1', 'user_id' => self::USER_ID, 'study_type_id' => 'ST_ESPA'],
            ['id' => 'ust-2', 'user_id' => self::USER_ID, 'study_type_id' => 'ST_BACH'],
            ['id' => 'ust-3', 'user_id' => 'other_user',  'study_type_id' => 'ST_FP'],
        ]);

        DB::table('user_studies')->insert([
            ['id' => 'us-1', 'user_id' => self::USER_ID, 'study_id' => 'S_ESPA'],
            ['id' => 'us-2', 'user_id' => 'other_user',  'study_id' => 'S_BACH'],
        ]);

        DB::table('user_course_modules')->insert([
            ['id' => 'um-1', 'user_id' => self::USER_ID, 'module_id' => 'M_MAT_1'],
            ['id' => 'um-2', 'user_id' => self::USER_ID, 'module_id' => 'M_ENG_1'],
        ]);

        DB::table('teams')->insert([
            ['id' => 'T1', 'name' => 'Equipo Calidad',  'description' => 'QA', 'is_department' => false],
            ['id' => 'T2', 'name' => 'Departamento ESPA', 'description' => null, 'is_department' => true],
            ['id' => 'T3', 'name' => 'Otro Equipo',     'description' => null, 'is_department' => false],
        ]);

        DB::table('team_members')->insert([
            ['id' => 'tm-1', 'team_id' => 'T1', 'user_id' => self::USER_ID, 'role' => 'member'],
            ['id' => 'tm-2', 'team_id' => 'T2', 'user_id' => self::USER_ID, 'role' => 'lead'],
            ['id' => 'tm-3', 'team_id' => 'T3', 'user_id' => 'other_user',  'role' => 'member'],
        ]);

        $dto = $this->resolver->resolve(self::USER_ID, ['id' => self::USER_ID]);

        $permissions = $dto->extra['permissions'];
        sort($permissions);
        $this->assertSame(['audit.export', 'audit.read'], $permissions);

        $studyTypeIds = $dto->extra['study_type_ids'];
        sort($studyTypeIds);
        $this->assertSame(['ST_BACH', 'ST_ESPA'], $studyTypeIds);

        $this->assertSame(['S_ESPA'], $dto->extra['study_ids']);

        $moduleIds = $dto->extra['module_ids'];
        sort($moduleIds);
        $this->assertSame(['M_ENG_1', 'M_MAT_1'], $moduleIds);

        $teamIds = $dto->extra['team_ids'];
        sort($teamIds);
        $this->assertSame(['T1', 'T2'], $teamIds);

        $teams = collect($dto->extra['teams'])->sortBy('id')->values()->all();
        $this->assertCount(2, $teams);
        $this->assertSame('T1', $teams[0]['id']);
        $this->assertSame('Equipo Calidad', $teams[0]['name']);
        $this->assertSame('QA', $teams[0]['description']);
        $this->assertSame('member', $teams[0]['role']);
        $this->assertFalse($teams[0]['is_department']);
        $this->assertSame('T2', $teams[1]['id']);
        $this->assertSame('lead', $teams[1]['role']);
        $this->assertTrue($teams[1]['is_department']);
    }

    public function test_resolve_filters_strictly_by_user_id_never_returns_others_data(): void
    {
        DB::table('user_study_types')->insert([
            ['id' => 'ust-other', 'user_id' => 'other_user', 'study_type_id' => 'ST_FP'],
        ]);
        DB::table('team_members')->insert([
            ['id' => 'tm-other', 'team_id' => 'T_OTHER', 'user_id' => 'other_user', 'role' => 'member'],
        ]);
        DB::table('teams')->insert([
            ['id' => 'T_OTHER', 'name' => 'Otro', 'description' => null, 'is_department' => false],
        ]);

        $dto = $this->resolver->resolve(self::USER_ID, ['id' => self::USER_ID]);

        $this->assertSame([], $dto->extra['study_type_ids']);
        $this->assertSame([], $dto->extra['team_ids']);
        $this->assertSame([], $dto->extra['teams']);
    }

    public function test_resolve_returns_empty_arrays_for_empty_user_id(): void
    {
        $dto = $this->resolver->resolve('', []);

        $this->assertSame([], $dto->extra['permissions']);
        $this->assertSame([], $dto->extra['study_type_ids']);
        $this->assertSame([], $dto->extra['study_ids']);
        $this->assertSame([], $dto->extra['module_ids']);
        $this->assertSame([], $dto->extra['team_ids']);
        $this->assertSame([], $dto->extra['teams']);
    }
}
