<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\User;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new CommentPolicy();
});

it('allows delete when user id matches comment user_id', function () {
    $user = User::factory()->create();
    $comment = new Comment(['user_id' => $user->id]);

    expect($this->policy->delete($user, $comment))->toBeTrue();
});

it('denies delete when user id does not match comment user_id', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $comment = new Comment(['user_id' => $other->id]);

    expect($this->policy->delete($user, $comment))->toBeFalse();
});

it('allows update when user id matches comment user_id', function () {
    $user = User::factory()->create();
    $comment = new Comment(['user_id' => $user->id]);

    expect($this->policy->update($user, $comment))->toBeTrue();
});

it('denies update when user id does not match comment user_id', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $comment = new Comment(['user_id' => $other->id]);

    expect($this->policy->update($user, $comment))->toBeFalse();
});
