<?php

declare(strict_types=1);

uses(\Tests\TestCase::class);

use App\Models\User;

/**
 * Verifica la configuración del modelo User (vista FDW read-only sobre Odoo).
 * No requiere base de datos — todo son aserciones sobre el estado del objeto.
 */

it('has string primary key that is non-incrementing', function () {
    $user = new User();

    expect($user->getKeyName())->toBe('id');
    expect($user->getKeyType())->toBe('string');
    expect($user->incrementing)->toBeFalse();
});

it('does not use timestamps', function () {
    expect((new User())->usesTimestamps())->toBeFalse();
});

it('uses users table', function () {
    expect((new User())->getTable())->toBe('users');
});

it('casts is_active to boolean', function () {
    $user = new User(['id' => 'uuid-1', 'email' => 'a@b.com', 'is_active' => '1']);
    expect($user->is_active)->toBeTrue();

    $user->is_active = '0';
    expect($user->is_active)->toBeFalse();
});
