<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            // application_id sin FK: 'applications' es una vista sobre FDW
            $table->unsignedBigInteger('application_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->timestamps();

            $table->unique(['code', 'application_id']);
            $table->index('application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_codes');
    }
};
