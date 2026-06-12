<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Maya\Profile\Models\ReadOnlyFdwApplication;

// applications es una vista sobre FDW → maya_auth — solo lectura.
// La configuración read-only (UPDATED_AT null, guarded *, cast created_at)
// vive en la base compartida; aquí solo las relaciones propias de logs.
class Application extends ReadOnlyFdwApplication
{
    use HasFactory;

    public function logs(): HasMany
    {
        return $this->hasMany(Log::class);
    }

    public function archivedLogs(): HasMany
    {
        return $this->hasMany(ArchivedLog::class);
    }

    public function errorCodes(): HasMany
    {
        return $this->hasMany(ErrorCode::class);
    }
}
