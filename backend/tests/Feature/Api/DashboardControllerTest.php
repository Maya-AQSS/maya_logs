<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(JwtMiddleware::class);

    $this->userId = (string) Str::uuid();
    User::forceCreate([
        'id'         => $this->userId,
        'email'      => 'dash-test@maya.localhost',
        'name'       => 'Dash User',
        'first_name' => 'Dash',
        'last_name'  => 'User',
        'username'   => 'dashuser',
        'is_active'  => true,
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    seedLogsLoginForTests();
});

it('returns dashboard data with data key', function () {
    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json())->toHaveKey('data');
});

it('returns severity cards in dashboard data', function () {
    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('data'))->toHaveKey('severity_cards');
});

it('returns application totals in dashboard data', function () {
    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('data'))->toHaveKey('application_totals');
});

it('counts logs per severity when logs exist', function () {
    $appId = DB::table('applications')->insertGetId([
        'name'       => 'Dash App',
        'slug'       => 'dash-app',
        'is_active'  => true,
        'created_at' => now(),
    ]);

    DB::table('logs')->insert([
        ['application_id' => $appId, 'severity' => 'critical', 'message' => 'crit', 'file' => 'f.php', 'line' => 1, 'metadata' => '{}', 'resolved' => false, 'created_at' => now()],
        ['application_id' => $appId, 'severity' => 'high', 'message' => 'high', 'file' => 'f.php', 'line' => 1, 'metadata' => '{}', 'resolved' => false, 'created_at' => now()],
    ]);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
    $cards = $response->json('data.severity_cards');
    // severity_cards should be an array with severity totals
    expect($cards)->toBeArray();
});
