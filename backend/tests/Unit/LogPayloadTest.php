<?php

namespace Tests\Unit;

use App\Services\LogPayload;
use Tests\TestCase;

class LogPayloadTest extends TestCase
{
    public function test_null_app_normalizes_to_empty_string(): void
    {
        $p = LogPayload::fromArray(['app' => null]);
        $this->assertSame('', $p->app);
    }

    public function test_missing_severity_defaults_to_other(): void
    {
        $p = LogPayload::fromArray(['app' => 'x']);
        $this->assertSame('other', $p->severity);
    }

    public function test_empty_error_code_normalizes_to_null(): void
    {
        $p = LogPayload::fromArray(['app' => 'x', 'error_code' => '']);
        $this->assertNull($p->errorCode);
    }

    public function test_non_empty_error_code_is_preserved(): void
    {
        $p = LogPayload::fromArray(['app' => 'x', 'error_code' => 'EC001']);
        $this->assertSame('EC001', $p->errorCode);
    }

    public function test_line_is_cast_to_int(): void
    {
        $p = LogPayload::fromArray(['app' => 'x', 'line' => '42']);
        $this->assertSame(42, $p->line);
    }

    public function test_missing_occurred_at_yields_null(): void
    {
        $p = LogPayload::fromArray(['app' => 'x']);
        $this->assertNull($p->occurredAt);
    }

    public function test_metadata_is_passed_through_as_is(): void
    {
        $p = LogPayload::fromArray(['app' => 'x', 'metadata' => ['k' => 'v']]);
        $this->assertSame(['k' => 'v'], $p->metadata);
    }
}
