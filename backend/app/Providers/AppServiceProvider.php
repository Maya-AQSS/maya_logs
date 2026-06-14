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
use App\Services\PanelUserService;
use App\Services\SeverityRankingService;
use Illuminate\Support\ServiceProvider;
use Maya\Platform\Support\RegistersFdwBootstrap;
use Maya\Profile\Migrations as ProfileMigrations;
use Maya\Profile\Repositories\Contracts\UserProfileResolverInterface;
use Maya\Profile\Repositories\Resolvers\FdwAcademicResolver;

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
        // /api/v1 con middleware ['api','jwt'] (default), listener FdwTeardown
        // para migrate:fresh/db:wipe y guard JWT stateless 'jwt-token'
        // (resuelve App\Models\User por defecto).
        //
        // Migraciones FDW compartidas de `ceedcv-maya/shared-profile-laravel`:
        //   - users
        //   - academicAssignments: user_study_types, user_studies, user_course_modules
        //   - teams: teams, team_members
        //   - userPermissions: user_resolved_permissions (la vista remota se
        //     configura por app en `database.fdw.user_permissions.remote_view`).
        RegistersFdwBootstrap::register($this, [
            'profileMigrations' => [
                ProfileMigrations::users(),
                ProfileMigrations::academicAssignments(),
                ProfileMigrations::teams(),
                ProfileMigrations::userPermissions(),
            ],
        ]);
    }
}
