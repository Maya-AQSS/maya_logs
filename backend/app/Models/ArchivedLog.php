<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArchivedLog extends Model
{
    use HasFactory, SoftDeletes;

    const CREATED_AT = null;

    const UPDATED_AT = 'updated_at';

    protected static function booted(): void
    {
        // En hard delete (forceDelete) el registro desaparece para siempre,
        // por lo que los comentarios huérfanos deben eliminarse.
        // La transacción que garantiza atomicidad debe ponerse en el Service
        // que llame a forceDelete(), igual que en ErrorCodeService::delete().

        static::forceDeleting(function (self $archivedLog) {
            $archivedLog->comments()->delete();
        });
    }

    protected $fillable = [
        'application_id',
        'archived_by_id',
        'error_code_id',
        'severity',
        'message',
        'metadata',
        'description',
        'url_tutorial',
        'original_created_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'original_created_at' => 'datetime',
            'archived_at' => 'datetime',
            'deleted_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_id');
    }

    public function errorCode(): BelongsTo
    {
        return $this->belongsTo(ErrorCode::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Aplica eager loading estándar para vistas de lista y detalle.
     *
     * @param  Builder<ArchivedLog>  $query
     * @return Builder<ArchivedLog>
     */
    public function scopeWithStandardRelations(Builder $query): Builder
    {
        return $query->with(['application', 'archivedBy', 'errorCode'])->withCount('comments');
    }

    public function getMetadataFormattedAttribute(): ?string
    {
        if (! is_array($this->metadata) || $this->metadata === []) {
            return null;
        }

        return json_encode($this->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
