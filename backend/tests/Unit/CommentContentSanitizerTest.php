<?php

declare(strict_types=1);

use App\Services\CommentContentSanitizer;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->sanitizer = new CommentContentSanitizer();
});

// Purifier::clean passes through whatever we give it in tests (with config 'rich_comment').
// We mock the Purifier facade to avoid configuring HTMLPurifier in tests.

it('returns sanitized content for plain text', function () {
    Purifier::shouldReceive('clean')
        ->with('<p>Hello world</p>', 'rich_comment')
        ->once()
        ->andReturn('<p>Hello world</p>');

    $result = $this->sanitizer->sanitize('<p>Hello world</p>');

    expect($result)->toBe('<p>Hello world</p>');
});

it('throws ValidationException for blank text after sanitization', function () {
    Purifier::shouldReceive('clean')
        ->andReturn('<p>   </p>');

    expect(fn () => $this->sanitizer->sanitize('<p>   </p>'))
        ->toThrow(ValidationException::class);
});

it('passes for content with only an image tag', function () {
    $imgHtml = '<img src="data:image/png;base64,' . base64_encode(str_pad("\x89PNG", 20, "\x00")) . '">';
    Purifier::shouldReceive('clean')
        ->andReturn($imgHtml);

    $result = $this->sanitizer->sanitize($imgHtml);
    expect($result)->toBe($imgHtml);
});

it('throws ValidationException when content exceeds 10MB', function () {
    $bigContent = str_repeat('a', 10 * 1024 * 1024 + 1);
    $html = '<p>' . $bigContent . '</p>';
    Purifier::shouldReceive('clean')
        ->andReturn($html);

    expect(fn () => $this->sanitizer->sanitize($html))
        ->toThrow(ValidationException::class);
});

it('passes for content exactly at size limit', function () {
    // Build content just at limit (strlen of full html ≤ 10MB)
    $content = '<p>' . str_repeat('x', 10 * 1024 * 1024 - 7) . '</p>';
    Purifier::shouldReceive('clean')
        ->andReturn($content);

    $result = $this->sanitizer->sanitize($content);
    expect($result)->toBe($content);
});

it('throws ValidationException for embedded image exceeding 2MB', function () {
    $largeImage = str_repeat("\x89PNG", 600_000); // >2MB
    $encoded = base64_encode($largeImage);
    $html = '<img src="data:image/png;base64,' . $encoded . '">';
    Purifier::shouldReceive('clean')
        ->andReturn($html);

    expect(fn () => $this->sanitizer->sanitize($html))
        ->toThrow(ValidationException::class);
});

it('throws ValidationException for image with invalid base64', function () {
    $html = '<img src="data:image/png;base64,!!!invalid!!!">';
    Purifier::shouldReceive('clean')
        ->andReturn($html);

    expect(fn () => $this->sanitizer->sanitize($html))
        ->toThrow(ValidationException::class);
});

it('throws ValidationException for image with disallowed magic bytes (not PNG/JPEG/GIF/WEBP)', function () {
    // BMP magic bytes — not in the allowed list
    $bmpData = str_pad('BM', 20, "\x00");
    $encoded = base64_encode($bmpData);
    $html = '<img src="data:image/png;base64,' . $encoded . '">';
    Purifier::shouldReceive('clean')
        ->andReturn($html);

    expect(fn () => $this->sanitizer->sanitize($html))
        ->toThrow(ValidationException::class);
});

it('passes for valid JPEG embedded image', function () {
    $jpegData = "\xFF\xD8\xFF" . str_repeat("\x00", 20);
    $encoded = base64_encode($jpegData);
    $html = '<img src="data:image/jpeg;base64,' . $encoded . '">';
    Purifier::shouldReceive('clean')
        ->andReturn($html);

    $result = $this->sanitizer->sanitize($html);
    expect($result)->toBe($html);
});

it('passes for valid GIF embedded image', function () {
    $gifData = 'GIF8' . str_repeat("\x00", 20);
    $encoded = base64_encode($gifData);
    $html = '<img src="data:image/gif;base64,' . $encoded . '">';
    Purifier::shouldReceive('clean')
        ->andReturn($html);

    $result = $this->sanitizer->sanitize($html);
    expect($result)->toBe($html);
});

it('passes for valid WEBP embedded image', function () {
    $webpData = 'RIFF' . "\x00\x00\x00\x00" . 'WEBP' . str_repeat("\x00", 10);
    $encoded = base64_encode($webpData);
    $html = '<img src="data:image/webp;base64,' . $encoded . '">';
    Purifier::shouldReceive('clean')
        ->andReturn($html);

    $result = $this->sanitizer->sanitize($html);
    expect($result)->toBe($html);
});

it('skips non-data-uri image src without throwing', function () {
    $html = '<p>text</p><img src="https://example.com/img.png">';
    Purifier::shouldReceive('clean')
        ->andReturn($html);

    $result = $this->sanitizer->sanitize($html);
    expect($result)->toBe($html);
});
