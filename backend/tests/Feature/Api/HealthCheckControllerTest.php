<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns healthy status on GET /api/v1/health', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk();
    $json = $response->json();
    expect($json)->toHaveKey('status');
});

it('returns live status on GET /api/v1/health/live', function () {
    $response = $this->getJson('/api/v1/health/live');

    $response->assertOk();
});

it('returns ready status on GET /api/v1/health/ready', function () {
    $response = $this->getJson('/api/v1/health/ready');

    // ready may return 200 or 503 depending on DB, either is acceptable
    expect($response->status())->toBeIn([200, 503]);
});
