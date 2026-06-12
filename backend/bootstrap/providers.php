<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    \App\Providers\EventServiceProvider::class,
    // Registro explícito: aporta el comando db:generate-seeders (antes
    // App\Console\Commands\GenerateSeedersFromDatabase, ahora compartido).
    \Maya\Platform\Providers\SharedPlatformServiceProvider::class,
];
