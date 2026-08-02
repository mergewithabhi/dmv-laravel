<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    public function test_livewire_uses_its_csp_safe_runtime_without_eval_or_duplicate_preloads(): void
    {
        $response = $this->get('/login')->assertOk();
        $policy = $response->headers->get('Content-Security-Policy');

        $this->assertTrue(config('livewire.csp_safe'));
        $this->assertNotNull($policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $response
            ->assertDontSee('rel="preload"', false)
            ->assertDontSee('rel="modulepreload"', false);

        Livewire::flushState();
        $siteResponse = $this->get('/news')->assertOk();
        preg_match(
            '/<script[^>]+src="([^"]*livewire[^"]*)"/i',
            (string) $siteResponse->getContent(),
            $matches
        );

        $scriptUrl = html_entity_decode($matches[1] ?? '');
        $this->assertNotSame('', $scriptUrl);
        $this->assertStringContainsString('/livewire.csp.js?', $scriptUrl);
        $this->assertStringNotContainsString('/livewire.js?', $scriptUrl);

        $scriptResponse = $this->get($scriptUrl)->assertOk();
        $script = (string) $scriptResponse->getContent();
        $this->assertStringNotContainsString('new Function', $script);
        $this->assertStringNotContainsString('normalRawEvaluator', $script);
    }
}
