<?php

declare(strict_types=1);

use App\Enums\Severity;
use App\Filters\SeverityFilter;

uses(\Tests\TestCase::class);

// ─── Severity enum ───────────────────────────────────────────────────────────

it('returns all severity values as strings', function () {
    $values = Severity::values();

    expect($values)->toBeArray()
        ->and($values)->toContain('critical', 'high', 'medium', 'low', 'other')
        ->and($values)->toHaveCount(5);
});

it('generates validation rule string with all values', function () {
    $rule = Severity::validationRule();

    expect($rule)->toStartWith('in:')
        ->and($rule)->toContain('critical')
        ->and($rule)->toContain('high')
        ->and($rule)->toContain('medium')
        ->and($rule)->toContain('low')
        ->and($rule)->toContain('other');
});

// ─── SeverityFilter ──────────────────────────────────────────────────────────

it('returns empty array for null input', function () {
    expect(SeverityFilter::normalize(null))->toBe([]);
});

it('returns empty array for empty string', function () {
    expect(SeverityFilter::normalize(''))->toBe([]);
});

it('returns empty array for empty array', function () {
    expect(SeverityFilter::normalize([]))->toBe([]);
});

it('wraps single string in array', function () {
    $result = SeverityFilter::normalize('high');

    expect($result)->toBe(['high']);
});

it('returns array as-is when all values valid', function () {
    $result = SeverityFilter::normalize(['high', 'low']);

    expect($result)->toBe(['high', 'low']);
});

it('deduplicates repeated values', function () {
    $result = SeverityFilter::normalize(['high', 'high', 'low']);

    expect($result)->toBe(['high', 'low']);
});

it('aborts 422 for unknown severity string', function () {
    expect(fn () => SeverityFilter::normalize('unknown'))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('aborts 422 for array containing unknown severity', function () {
    expect(fn () => SeverityFilter::normalize(['high', 'bogus']))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
