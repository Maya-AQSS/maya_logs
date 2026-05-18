<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\ErrorCodeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;

#[ObservedBy([ErrorCodeObserver::class])]
class ErrorCode extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // booted() es el hook recomendado desde Laravel 8+.
        // Cascade delete comments when error code is deleted
        static::deleting(function (self $errorCode) {
            $errorCode->comments()->delete();
        });

        // Invalida el cache del selector de error codes cuando se crea o edita uno.
        static::saved(function () {
            Cache::forget('error_codes:for_select');
        });
    }

    protected $fillable = [
        'code',
        'application_id',
        'name',
        'file',
        'line',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'line' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(Log::class);
    }

    public function archivedLogs(): HasMany
    {
        return $this->hasMany(ArchivedLog::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
