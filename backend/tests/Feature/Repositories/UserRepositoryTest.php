<?php

declare(strict_types=1);

use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repo = new UserRepository();
});

it('finds user by key (uuid)', function () {
    $user = User::factory()->create();

    $found = $this->repo->findByKey($user->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($user->id);
});

it('returns null when user does not exist', function () {
    $result = $this->repo->findByKey('non-existent-uuid');

    expect($result)->toBeNull();
});

it('returns null for empty string key', function () {
    $result = $this->repo->findByKey('');

    expect($result)->toBeNull();
});

it('does not confuse users with similar ids', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $foundA = $this->repo->findByKey($userA->id);
    $foundB = $this->repo->findByKey($userB->id);

    expect($foundA->id)->toBe($userA->id)
        ->and($foundB->id)->toBe($userB->id);
});
