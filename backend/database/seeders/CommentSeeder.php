<?php

namespace Database\Seeders;

use App\Models\ArchivedLog;
use App\Models\Comment;
use App\Models\ErrorCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        Comment::updateOrCreate(
            ['id' => 1],
            [
                'content' => 'Comment 1 de Admin',
                'user_id' => '1',
                'commentable_type' => ErrorCode::class,
                'commentable_id' => 1,
            ]
        );

        Comment::updateOrCreate(
            ['id' => 2],
            [
                'content' => 'Comment 2 de User',
                'user_id' => '2',
                'commentable_type' => ErrorCode::class,
                'commentable_id' => 1,
            ]
        );

        Comment::updateOrCreate(
            ['id' => 3],
            [
                'content' => 'Comment 3 de Admin',
                'user_id' => '1',
                'commentable_type' => ArchivedLog::class,
                'commentable_id' => 1,
            ]
        );

        Comment::updateOrCreate(
            ['id' => 4],
            [
                'content' => 'Comment 2 de User',
                'user_id' => '2',
                'commentable_type' => ArchivedLog::class,
                'commentable_id' => 1,
            ]
        );

        /*
        TODO: Necesario mientras exista el seeder porque sino el id autoincremental de la BD se desincroniza con el id de la tabla.
        Resync de la secuencia PostgreSQL para evitar UniqueConstraintViolation al insertar nuevos comentarios
        Al eliminarlo será necesario hacer "sail migrate:fresh --seed"
        */
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('comments', 'id'), (SELECT COALESCE(MAX(id), 1) FROM comments))"
            );
        }
    }
}
