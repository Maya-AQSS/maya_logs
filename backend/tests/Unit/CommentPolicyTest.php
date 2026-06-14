<?php

declare(strict_types=1);

use App\Dtos\CommentDto;
use App\Models\ArchivedLog;
use App\Models\ErrorCode;
use App\Models\User;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Maya\Profile\Dtos\UserProfileDto;
use Maya\Profile\Enums\Locale;
use Maya\Profile\Services\Contracts\UserProfileServiceInterface;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->profileService = Mockery::mock(UserProfileServiceInterface::class);
});

afterEach(function () {
    Mockery::close();
});

function makeCommentPolicy(array $jwtUser): CommentPolicy
{
    $request = new Request;
    $request->attributes->set('jwt_user', $jwtUser);

    return new CommentPolicy($request, test()->profileService);
}

function makeCommentProfile(array $permissions): UserProfileDto
{
    return new UserProfileDto(
        id: 'user-1',
        email: 'c@maya.local',
        name: 'Comment User',
        locale: Locale::Spanish,
        extra: ['permissions' => $permissions],
    );
}

/**
 * La policy opera sobre el DTO (Opción A — DTO estricto), no sobre el modelo.
 */
function makeCommentDto(?string $authorId, string $commentableType): CommentDto
{
    return new CommentDto(
        id: 1,
        content: '<p>x</p>',
        commentableType: $commentableType,
        commentableId: 1,
        createdAt: null,
        updatedAt: null,
        user: null,
        userLoaded: false,
        authorId: $authorId,
    );
}

it('allows delete when user is comment author without delete slug', function () {
    $user = User::factory()->create(['id' => 'user-1']);
    $comment = makeCommentDto('user-1', ArchivedLog::class);

    $policy = makeCommentPolicy(['id' => 'user-1']);

    expect($policy->delete($user, $comment))->toBeTrue();
});

it('allows delete when user has archived-logs.comment.delete but is not author', function () {
    $user = User::factory()->create(['id' => 'user-1']);
    $comment = makeCommentDto('other-user', ArchivedLog::class);

    test()->profileService->shouldReceive('getProfile')->once()->andReturn(
        makeCommentProfile(['archived-logs.comment.delete']),
    );

    $policy = makeCommentPolicy(['id' => 'user-1']);

    expect($policy->delete($user, $comment))->toBeTrue();
});

it('allows delete when user has error-code.comment.delete but is not author', function () {
    $user = User::factory()->create(['id' => 'user-1']);
    $comment = makeCommentDto('other-user', ErrorCode::class);

    test()->profileService->shouldReceive('getProfile')->once()->andReturn(
        makeCommentProfile(['error-code.comment.delete']),
    );

    $policy = makeCommentPolicy(['id' => 'user-1']);

    expect($policy->delete($user, $comment))->toBeTrue();
});

it('denies delete when not author and missing delete slug', function () {
    $user = User::factory()->create(['id' => 'user-1']);
    $comment = makeCommentDto('other-user', ArchivedLog::class);

    test()->profileService->shouldReceive('getProfile')->once()->andReturn(
        makeCommentProfile([]),
    );

    $policy = makeCommentPolicy(['id' => 'user-1']);

    expect($policy->delete($user, $comment))->toBeFalse();
});

it('allows update only for comment author', function () {
    $user = User::factory()->create();
    $comment = makeCommentDto($user->id, ArchivedLog::class);

    $policy = makeCommentPolicy(['id' => $user->id]);

    expect($policy->update($user, $comment))->toBeTrue();
});

it('denies update when not comment author', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $comment = makeCommentDto($other->id, ArchivedLog::class);

    $policy = makeCommentPolicy(['id' => $user->id]);

    expect($policy->update($user, $comment))->toBeFalse();
});
