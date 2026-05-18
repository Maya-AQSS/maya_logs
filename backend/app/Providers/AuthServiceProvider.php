<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\ArchivedLog;
use App\Models\Comment;
use App\Models\ErrorCode;
use App\Policies\ArchivedLogPolicy;
use App\Policies\CommentPolicy;
use App\Policies\ErrorCodePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        ArchivedLog::class => ArchivedLogPolicy::class,
        Comment::class => CommentPolicy::class,
        ErrorCode::class => ErrorCodePolicy::class,
    ];

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
