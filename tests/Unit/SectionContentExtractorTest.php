<?php

namespace Tests\Unit;

use App\Domain\Content\SectionContentExtractor;
use Tests\TestCase;

class SectionContentExtractorTest extends TestCase
{
    public function test_it_extracts_visible_text_links_media_icons_and_accessibility_copy(): void
    {
        $html = <<<'HTML'
<section class="hero">
    <h1>DMV Warriors</h1>
    <a href="/schedule" aria-label="Open schedule">Schedule</a>
    <img src="assets/photo.png" alt="Team photo">
    <img src="assets/icons/calendar.svg" alt="">
</section>
HTML;

        $result = app(SectionContentExtractor::class)->extract(
            $html,
            '.hero',
            fn (string $path) => str_contains($path, 'calendar') ? 12 : 11
        );
        $inputs = collect($result['schema']['fields'])->pluck('input');

        $this->assertContains('text', $inputs);
        $this->assertContains('url', $inputs);
        $this->assertContains('media', $inputs);
        $this->assertContains('icon', $inputs);
        $this->assertContains(11, $result['payload']);
        $this->assertContains(12, $result['payload']);
    }

    public function test_an_empty_src_placeholder_field_is_extracted_but_its_static_label_hint_never_is(): void
    {
        $html = <<<'HTML'
<section class="hero">
    <img class="hero-media" src="" alt="Team celebration image" data-placeholder-label="Team celebration image" />
</section>
HTML;

        $result = app(SectionContentExtractor::class)->extract($html, '.hero');
        $field = collect($result['schema']['fields'])->first(fn (array $field) => ($field['input'] ?? null) === 'media');

        $this->assertNotNull($field);
        $this->assertSame('attribute', $field['kind']);
        $this->assertSame('src', $field['attribute']);
        $this->assertSame('', $result['payload'][$field['id']]);

        // The `alt` attribute is legitimately extracted as its own editable text
        // field (it happens to share wording with the placeholder label here);
        // only the `data-placeholder-label` attribute itself must never surface.
        $this->assertStringNotContainsString('data-placeholder-label', json_encode($result['schema']));
        $altField = collect($result['schema']['fields'])->first(fn (array $field) => ($field['attribute'] ?? null) === 'alt');
        $this->assertNotNull($altField);
        $this->assertSame('Team celebration image', $result['payload'][$altField['id']]);
    }
}
