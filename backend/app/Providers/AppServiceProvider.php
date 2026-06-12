<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\ApplicationRepositoryInterface;
use App\Repositories\Contracts\ArchivedLogRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\ErrorCodeRepositoryInterface;
use App\Repositories\Contracts\LogIngestionRepositoryInterface;
use App\Repositories\Contracts\LogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\ApplicationRepository;
use App\Repositories\Eloquent\ArchivedLogRepository;
use App\Repositories\Eloquent\CommentRepository;
use App\Repositories\Eloquent\ErrorCodeRepository;
use App\Repositories\Eloquent\LogIngestionRepository;
use App\Repositories\Eloquent\LogRepository;
use App\Repositories\Eloquent\UserRepository;
use Maya\Platform\Support\RegistersFdwBootstrap;
use Maya\Profile\Migrations as ProfileMigrations;
use Maya\Profile\Repositories\Resolvers\FdwAcademicResolver;
use App\Services\ApplicationService;
use App\Services\ArchivedFieldsValidator;
use App\Services\ArchivedLogService;
use App\Services\CommentContentSanitizer;
use App\Services\CommentService;
use App\Services\Contracts\ApplicationServiceInterface;
use App\Services\Contracts\ArchivedLogServiceInterface;
use App\Services\Contracts\CommentContentSanitizerInterface;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\ErrorCodeServiceInterface;
use App\Services\Contracts\LogServiceInterface;
use App\Services\ErrorCodeService;
use App\Services\LogService;
use App\Services\SeverityRankingService;
use App\Services\PanelUserService;
use Illuminate\Support\ServiceProvider;
use Maya\Profile\Repositories\Contracts\UserProfileResolverInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApplicationRepositoryInterface::class, ApplicationRepository::class);
        $this->app->singleton(ApplicationServiceInterface::class, ApplicationService::class);

        $this->app->singleton(SeverityRankingService::class, SeverityRankingService::class);
        $this->app->singleton(ArchivedFieldsValidator::class, ArchivedFieldsValidator::class);
        $this->app->singleton(ArchivedLogRepositoryInterface::class, ArchivedLogRepository::class);
        $this->app->singleton(ArchivedLogServiceInterface::class, ArchivedLogService::class);

        $this->app->singleton(LogRepositoryInterface::class, LogRepository::class);
        $this->app->singleton(LogIngestionRepositoryInterface::class, LogIngestionRepository::class);
        $this->app->singleton(LogServiceInterface::class, LogService::class);

        $this->app->singleton(UserRepositoryInterface::class, UserRepository::class);
        $this->app->singleton(PanelUserService::class, PanelUserService::class);

        $this->app->singleton(CommentRepositoryInterface::class, CommentRepository::class);
        $this->app->singleton(CommentContentSanitizerInterface::class, CommentContentSanitizer::class);
        $this->app->singleton(CommentServiceInterface::class, CommentService::class);

        $this->app->singleton(ErrorCodeRepositoryInterface::class, ErrorCodeRepository::class);
        $this->app->singleton(ErrorCodeServiceInterface::class, ErrorCodeService::class);

        // Resolver de perfil enriquecido cross-app: el shared MeController consume
        // este binding para devolver /me con permissions/study_type_ids/study_ids/
        // module_ids/team_ids/teams enriquecidos desde las FDW locales (mismas
        // vistas que el resto de apps Maya proyectan localmente — sin
        // dependencias cruzadas en runtime).
        $this->app->singleton(UserProfileResolverInterface::class, FdwAcademicResolver::class);
    }

    public function boot(): void
    {
        // Bloque de bootstrap FDW compartido (shared-platform-laravel):
        // forceScheme(https) en production/staging, Broadcast::routes bajo
        // /api/v1 con middleware ['api','jwt'] (default), migraciones de
        // shared-profile, listener FdwTeardown para migrate:fresh/db:wipe y
        // guard JWT stateless 'jwt-token' (resuelve App\Models\User por defecto).
        RegistersFdwBootstrap::register($this);

        // Migraciones FDW compartidas del paquete `ceedcv-maya/shared-profile-laravel`:
        //   - users
        //   - academicAssignments: user_study_types, user_studies, user_course_modules
        //   - teams: teams, team_members
        //   - userPermissions: user_resolved_permissions (la vista remota se
        //     configura por app en `database.fdw.user_permissions.remote_view`).
        //
        // NOTA: no se pasan via la opción 'profileMigrations' del helper porque
        // RegistersFdwBootstrap (0.16.0) invoca ServiceProvider::loadMigrationsFrom(),
        // que es protected, desde fuera del provider → Error en runtime. Hasta que
        // el paquete lo corrija (p. ej. via Migrator::path()), se cargan aquí,
        // dentro del scope del provider, con comportamiento idéntico.
        $this->loadMigrationsFrom(ProfileMigrations::users());
        $this->loadMigrationsFrom(ProfileMigrations::academicAssignments());
        $this->loadMigrationsFrom(ProfileMigrations::teams());
        $this->loadMigrationsFrom(ProfileMigrations::userPermissions());
    }
}
