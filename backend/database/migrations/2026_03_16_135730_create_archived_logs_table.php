<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_logs', function (Blueprint $table) {
            $table->id();
            // application_id sin FK: 'applications' es una vista sobre FDW
            $table->unsignedBigInteger('application_id');
            // archived_by_id: UUID del usuario de Odoo vía FDW (varchar); sin FK (view)
            $table->string('archived_by_id', 255);
            $table->foreignId('error_code_id')->nullable()->constrained('error_codes')->nullOnDelete();
            $table->enum('severity', ['critical', 'high', 'medium', 'low', 'other']);
            $table->text('message');
            $table->jsonb('metadata')->nullable();
            $table->text('description')->nullable();
            $table->string('url_tutorial', 500)->nullable();
            $table->timestamptz('original_created_at');
            $table->timestamptz('archived_at');
            $table->timestamptz('updated_at')->nullable();

            $table->index(['application_id', 'archived_at']);
            $table->index('archived_by_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_logs');
    }
};
