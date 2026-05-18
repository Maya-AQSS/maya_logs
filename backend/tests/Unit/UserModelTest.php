<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

/**
 * Verifica la configuración del modelo User (vista FDW read-only sobre Odoo).
 * No requiere base de datos — todo son aserciones sobre el estado del objeto.
 */
class UserModelTest extends TestCase
{
    public function test_primary_key_is_string_and_non_incrementing(): void
    {
        $user = new User();

        $this->assertSame('id', $user->getKeyName());
        $this->assertSame('string', $user->getKeyType());
        $this->assertFalse($user->incrementing);
    }

    public function test_no_timestamps(): void
    {
        $this->assertFalse((new User())->usesTimestamps());
    }

    public function test_table_is_users(): void
    {
        $this->assertSame('users', (new User())->getTable());
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $user = new User(['id' => 'uuid-1', 'email' => 'a@b.com', 'is_active' => '1']);
        $this->assertTrue($user->is_active);

        $user->is_active = '0';
        $this->assertFalse($user->is_active);
    }
}
