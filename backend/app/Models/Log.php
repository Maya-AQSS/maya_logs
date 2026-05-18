<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Log extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'resolved' => 'boolean',
            'created_at' => 'datetime',
            'line' => 'integer',
        ];
    }

    /**
     * Disable all CRUD operations on the model
     */
    protected static function booted(): void
    {
        static::creating(fn () => false);
        static::updating(fn () => false);
        static::deleting(fn () => false);
        static::saving(fn () => false);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function errorCode(): BelongsTo
    {
        return $this->belongsTo(ErrorCode::class);
    }
}
