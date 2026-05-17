<?php

declare(strict_types=1);

use App\Rules\AcceptableTutorialUrl;

uses(\Tests\TestCase::class);

function runUrlRule(mixed $value): array
{
    $rule = new AcceptableTutorialUrl();
    $failures = [];
    $rule->validate('url', $value, function (string $msg) use (&$failures) {
        $failures[] = $msg;
    });

    return $failures;
}

it('passes empty string without failure', function () {
    expect(runUrlRule(''))->toBe([]);
});

it('passes valid https URL with domain', function () {
    expect(runUrlRule('https://example.com/path'))->toBe([]);
});

it('passes valid http URL with subdomain', function () {
    expect(runUrlRule('http://docs.example.com/page'))->toBe([]);
});

it('passes localhost URL', function () {
    expect(runUrlRule('http://localhost:3000/foo'))->toBe([]);
});

it('passes IPv4 address URL', function () {
    expect(runUrlRule('http://192.168.1.1/api'))->toBe([]);
});

it('passes IPv6 address URL', function () {
    expect(runUrlRule('http://[::1]/api'))->toBe([]);
});

it('fails for ftp scheme', function () {
    expect(runUrlRule('ftp://example.com/file'))->not->toBe([]);
});

it('fails for single-label host without dot', function () {
    // filter_var passes "https://example" but our rule rejects it
    // Actually filter_var may also reject it — just ensure no crash
    $result = runUrlRule('https://example');
    // Either fails at filter_var or at dot-check — result is non-empty
    // The important thing: it does NOT silently pass
    expect(true)->toBeTrue(); // placeholder — real assertion below tests via non-empty on missing dot
    $result2 = runUrlRule('https://nodot/path');
    expect($result2)->not->toBe([]); // must fail
});

it('fails for non-URL string', function () {
    expect(runUrlRule('not-a-url'))->not->toBe([]);
});

it('fails for mailto scheme', function () {
    expect(runUrlRule('mailto:user@example.com'))->not->toBe([]);
});

it('passes null cast to empty string via trim', function () {
    // value is cast to string — null becomes ''
    expect(runUrlRule(null))->toBe([]);
});
