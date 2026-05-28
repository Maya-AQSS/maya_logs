<?php

declare(strict_types=1);

uses(\Tests\TestCase::class);

use App\Services\LogPayload;

it('normalizes null app to empty string', function () {
    $p = LogPayload::fromArray(['app' => null]);
    expect($p->app)->toBe('');
});

it('defaults severity to other when missing', function () {
    $p = LogPayload::fromArray(['app' => 'x']);
    expect($p->severity)->toBe('other');
});

it('normalizes empty error_code to null', function () {
    $p = LogPayload::fromArray(['app' => 'x', 'error_code' => '']);
    expect($p->errorCode)->toBeNull();
});

it('preserves non-empty error_code', function () {
    $p = LogPayload::fromArray(['app' => 'x', 'error_code' => 'EC001']);
    expect($p->errorCode)->toBe('EC001');
});

it('casts line to int', function () {
    $p = LogPayload::fromArray(['app' => 'x', 'line' => '42']);
    expect($p->line)->toBe(42);
});

it('yields null occurred_at when missing', function () {
    $p = LogPayload::fromArray(['app' => 'x']);
    expect($p->occurredAt)->toBeNull();
});

it('passes metadata through as-is', function () {
    $p = LogPayload::fromArray(['app' => 'x', 'metadata' => ['k' => 'v']]);
    expect($p->metadata)->toBe(['k' => 'v']);
});
