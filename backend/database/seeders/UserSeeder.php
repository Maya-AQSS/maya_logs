<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Pobla la tabla local `users` (stub en testing) con identidad mínima:
     * email + name + first_name + last_name + username.
     *
     * En entornos donde `users` es una vista FDW (read-only) — local con
     * Odoo conectado o producción — la inserción fallará. En ese caso el
     * seeder es no-op y la sincronización viene de Odoo vía Keycloak User
     * Federation.
     *
     * Para tests (SQLite) la tabla es local y se puede escribir.
     */
    public function run(): void
    {
        $users = [
            [
                'id' => '1',
                'email' => 'admin@example.com',
                'name' => 'Admin User',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'username' => 'admin',
            ],
            [
                'id' => '2',
                'email' => 'user2@example.com',
                'name' => 'User Two',
                'first_name' => 'User',
                'last_name' => 'Two',
                'username' => 'user2',
            ],
        ];

        foreach ($users as $user) {
            try {
                User::updateOrCreate(['id' => $user['id']], $user);
            } catch (\Throwable $e) {
                // users es una vista FDW de solo lectura — los datos vienen
                // de Odoo. No-op silencioso; el seeder solo pretende rellenar
                // la tabla stub en testing.
                Log::info('UserSeeder skip (FDW read-only): '.$e->getMessage());

                return;
            }
        }
    }
}
