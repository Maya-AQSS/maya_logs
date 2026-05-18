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
use Maya\Profile\Migrations as ProfileMigrations;
use Maya\Profile\Repositories\Resolvers\FdwAcademicResolver;
use App\Services\ApplicationService;
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
use App\Models\User;
use App\Services\PanelUserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Maya\Profile\Repositories\Contracts\UserProfileResolverInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApplicationRepositoryInterface::class, ApplicationRepository::class);
        $this->app->singleton(ApplicationServiceInterface::class, ApplicationService::class);

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
        if ($this->app->environment(['production', 'staging'])) {
            URL::forceScheme('https');
        }

        // Migraciones FDW compartidas del paquete `maya/shared-profile-laravel`:
        //   - academicAssignments: user_study_types, user_studies, user_course_modules
        //   - teams: teams, team_members
        //   - userPermissions: user_resolved_permissions (la vista remota se
        //     configura por app en `database.fdw.user_permissions.remote_view`).
        // dms carga solo los dos primeros grupos (tiene su propio modelo de
        // permisos basado en `permission_code`).
        $this->loadMigrationsFrom(ProfileMigrations::academicAssignments());
        $this->loadMigrationsFrom(ProfileMigrations::teams());
        $this->loadMigrationsFrom(ProfileMigrations::userPermissions());

        // Guard JWT stateless: resuelve el usuario desde el atributo 'jwt_user'
        // que JwtMiddleware deposita en el request tras validar el token.
        Auth::viaRequest('jwt-token', function ($request) {
            $profile = $request->attributes->get('jwt_user');
            if (! is_array($profile) || empty($profile['id'])) {
                return null;
            }

            return User::query()->find($profile['id']);
        });
    }
}
