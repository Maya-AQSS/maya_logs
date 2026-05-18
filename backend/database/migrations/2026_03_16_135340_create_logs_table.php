<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla escrita por el worker `php artisan logs:consume` que consume la
     * cola `logs.ingest` de RabbitMQ (exchange `maya.logs`).
     *
     * El modelo Eloquent `App\Models\Log` bloquea escrituras via booted(); el
     * worker usa DB::table('logs')->insert(...) para saltarse ese bloqueo.
     * Esta migración solo crea la tabla si no existe aún (dev/test).
     *
     * Estructura esperada:
     *   id               bigserial PK
     *   error_code_id    bigint FK nullable → error_codes.id
     *   application_id   bigint FK → applications.id
     *   severity         enum (critical, high, medium, low, other)
     *   message          text
     *   file             varchar nullable
     *   line             integer nullable
     *   metadata         jsonb nullable
     *   resolved         boolean default false
     *   created_at       timestamptz
     */
    public function up(): void
    {
        if (Schema::hasTable('logs')) {
            return;
        }

        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('error_code_id')->nullable()->constrained('error_codes')->nullOnDelete();
            // application_id sin FK: 'applications' es una vista sobre FDW
            $table->unsignedBigInteger('application_id');
            $table->enum('severity', ['critical', 'high', 'medium', 'low', 'other']);
            $table->text('message');
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamptz('created_at')->nullable();

            $table->index('error_code_id');
            $table->index(['application_id', 'created_at']);
            $table->index(['severity', 'resolved']);
        });
    }

    public function down(): void
    {
        // Precaucion: dropea datos historicos. Solo usar en dev/test.
        Schema::dropIfExists('logs');
    }
};
