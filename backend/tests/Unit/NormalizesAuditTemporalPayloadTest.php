<?php

declare(strict_types=1);

use App\Observers\Concerns\NormalizesAuditTemporalPayload;
use Illuminate\Support\Carbon;

uses(\Tests\TestCase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('formats eloquent timestamps in configured timezone', function () {
    config(['messaging.audit_timestamp_timezone' => 'Europe/Madrid']);
    Carbon::setTestNow(Carbon::parse('2026-05-15T08:20:47Z'));

    $normalizer = new class
    {
        use NormalizesAuditTemporalPayload;

        /** @param  list<string>  $keys */
        public function normalize(?array $payload, array $keys): ?array
        {
            return $this->normalizeAuditTemporalPayload($payload, $keys);
        }
    };

    $result = $normalizer->normalize(
        ['created_at' => '2026-05-15 08:20:47', 'name' => 'x'],
        ['created_at', 'updated_at'],
    );

    expect($result['name'])->toBe('x')
        ->and((string) $result['created_at'])->toContain('2026-05-15T10:20:47')
        ->and((string) $result['created_at'])->toContain('+');
});

it('falls back to utc zulu when timezone config is empty', function () {
    config(['messaging.audit_timestamp_timezone' => '']);
    Carbon::setTestNow(Carbon::parse('2026-05-15T08:20:47Z'));

    $normalizer = new class
    {
        use NormalizesAuditTemporalPayload;

        public function normalize(?array $payload): ?array
        {
            return $this->normalizeAuditTemporalPayload($payload, ['created_at']);
        }
    };

    $result = $normalizer->normalize(['created_at' => '2026-05-15 08:20:47']);

    expect($result['created_at'])->toBe('2026-05-15T08:20:47Z');
});
