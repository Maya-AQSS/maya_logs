<?php

declare(strict_types=1);

use App\Models\ArchivedLog;
use App\Models\Comment;
use App\Models\User;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Maya\Profile\Dtos\UserProfileDto;
use Maya\Profile\Enums\Locale;
use Maya\Profile\Services\Contracts\UserProfileServiceInterface;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->profileService = Mockery::mock(UserProfileServiceInterface::class);
});

afterEach(function () {
    Mockery::close();
});

function makeCommentPolicy(array $jwtUser): CommentPolicy
{
    $request = new Request();
    $request->attributes->set('jwt_user', $jwtUser);

    return new CommentPolicy($request, test()->profileService);
}

function makeCommentProfile(array $permissions): UserProfileDto
{
    return new UserProfileDto(
        id: 'user-1',
        email: 'c@maya.local',
        name: 'Comment User',
        locale: Locale::Es,
        extra: ['permissions' => $permissions],
    );
}

it('allows delete when author and has archived-logs.comment.delete', function () {
    $user = User::factory()->create(['id' => 'user-1']);
    $comment = new Comment([
        'user_id' => 'user-1',
        'commentable_type' => ArchivedLog::class,
    ]);

    test()->profileService->shouldReceive('getProfile')->once()->andReturn(
        makeCommentProfile(['archived-logs.comment.delete']),
    );

    $policy = makeCommentPolicy(['id' => 'user-1']);

    expect($policy->delete($user, $comment))->toBeTrue();
});

it('denies delete when author but missing archived-logs.comment.delete', function () {
    $user = User::factory()->create(['id' => 'user-1']);
    $comment = new Comment([
        'user_id' => 'user-1',
        'commentable_type' => ArchivedLog::class,
    ]);

    test()->profileService->shouldReceive('getProfile')->once()->andReturn(
        makeCommentProfile([]),
    );

    $policy = makeCommentPolicy(['id' => 'user-1']);

    expect($policy->delete($user, $comment))->toBeFalse();
});

it('denies delete when has permission but not author', function () {
    $user = User::factory()->create(['id' => 'user-1']);
    $comment = new Comment([
        'user_id' => 'other-user',
        'commentable_type' => ArchivedLog::class,
    ]);

    $policy = makeCommentPolicy(['id' => 'user-1']);

    expect($policy->delete($user, $comment))->toBeFalse();
});

it('allows update only for comment author', function () {
    $user = User::factory()->create();
    $comment = new Comment(['user_id' => $user->id]);

    $policy = makeCommentPolicy(['id' => $user->id]);

    expect($policy->update($user, $comment))->toBeTrue();
});

it('denies update when not comment author', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $comment = new Comment(['user_id' => $other->id]);

    $policy = makeCommentPolicy(['id' => $user->id]);

    expect($policy->update($user, $comment))->toBeFalse();
});
