<?php

namespace Tests\Unit;

use App\Rules\SafeUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeUrlTest extends TestCase
{
    #[DataProvider('allowedUrls')]
    public function test_it_allows_explicit_safe_schemes_and_relative_urls(string $url): void
    {
        $this->assertTrue(SafeUrl::allows($url));
    }

    #[DataProvider('unsafeUrls')]
    public function test_it_rejects_unsafe_or_ambiguous_urls(string $url): void
    {
        $this->assertFalse(SafeUrl::allows($url));
    }

    public static function allowedUrls(): array
    {
        return [
            'absolute https' => ['https://dmvwarriors.example/tickets'],
            'absolute http' => ['http://dmvwarriors.example'],
            'email' => ['mailto:info@dmvwarriors.example'],
            'telephone' => ['tel:+13015550198'],
            'root relative' => ['/schedule'],
            'path relative' => ['players/jordan-miles'],
            'anchor' => ['#standings'],
            'query' => ['?month=august'],
        ];
    }

    public static function unsafeUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'mixed case javascript' => ['JaVaScRiPt:alert(1)'],
            'tab normalized javascript' => ["java\tscript:alert(1)"],
            'newline normalized javascript' => ["java\nscript:alert(1)"],
            'data URI' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file URI' => ['file:///etc/passwd'],
            'protocol relative' => ['//evil.example/path'],
            'backslash protocol relative' => ['\\\\evil.example\\path'],
            'mixed slash protocol relative' => ['/\\evil.example/path'],
            'mixed backslash protocol relative' => ['\\/evil.example/path'],
        ];
    }
}
