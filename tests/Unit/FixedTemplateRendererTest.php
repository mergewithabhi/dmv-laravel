<?php

namespace Tests\Unit;

use App\Domain\Content\FixedTemplateRenderer;
use App\Domain\Content\PageSchemaRegistry;
use App\Domain\Content\SectionContentExtractor;
use App\Enums\MediaKind;
use App\Enums\PublicationStatus;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FixedTemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_resolved_media_field_renders_a_real_image(): void
    {
        $asset = $this->imageAsset();
        [$page] = $this->pageWithHeroField((string) $asset->id);

        $hero = $this->heroFragment($this->render($page));

        $this->assertStringStartsWith('<img', $hero);
        $this->assertStringContainsString($asset->url('web'), $hero);
        $this->assertStringContainsString('loading="eager"', $hero);
        $this->assertStringNotContainsString('media-placeholder', $hero);
    }

    public function test_an_empty_payload_renders_a_placeholder_instead_of_a_broken_image(): void
    {
        [$page] = $this->pageWithHeroField('');

        $hero = $this->heroFragment($this->render($page));

        $this->assertStringNotContainsString('src=""', $hero);
        $this->assertStringStartsWith('<div', $hero);
        $this->assertStringContainsString('media-placeholder', $hero);
        $this->assertStringContainsString('Team celebration image', $hero);
    }

    public function test_a_payload_referencing_a_deleted_asset_also_falls_back_to_the_placeholder(): void
    {
        [$page] = $this->pageWithHeroField('999999');

        $hero = $this->heroFragment($this->render($page));

        $this->assertStringNotContainsString('src=""', $hero);
        $this->assertStringContainsString('media-placeholder', $hero);
    }

    public function test_placeholder_carries_over_reveal_and_accessibility_attributes(): void
    {
        [$page] = $this->pageWithHeroField('');

        $hero = $this->heroFragment($this->render($page));

        $this->assertStringContainsString('data-reveal="scale"', $hero);
        $this->assertStringContainsString('data-motion-media', $hero);
        $this->assertStringContainsString('role="img"', $hero);
    }

    public function test_clearing_an_existing_icon_field_falls_back_to_a_placeholder_not_a_broken_image(): void
    {
        $asset = $this->imageAsset();
        [$page, $section, $fieldId] = $this->pageWithHeroField((string) $asset->id);

        $hero = $this->heroFragment($this->render($page));
        $this->assertStringStartsWith('<img', $hero);

        $section->update(['payload' => [$fieldId => '']]);
        $page->refresh()->load('sections');

        $hero = $this->heroFragment($this->render($page));
        $this->assertStringNotContainsString('src=""', $hero);
        $this->assertStringContainsString('media-placeholder', $hero);
    }

    public function test_switching_back_to_a_valid_asset_swaps_the_placeholder_back_to_an_image(): void
    {
        $asset = $this->imageAsset();
        [$page, $section, $fieldId] = $this->pageWithHeroField('');

        $hero = $this->heroFragment($this->render($page));
        $this->assertStringContainsString('media-placeholder', $hero);

        $section->update(['payload' => [$fieldId => (string) $asset->id]]);
        $page->refresh()->load('sections');

        $hero = $this->heroFragment($this->render($page));
        $this->assertStringNotContainsString('media-placeholder', $hero);
        $this->assertStringContainsString($asset->url('web'), $hero);
    }

    private function render(Page $page): string
    {
        return (string) app(FixedTemplateRenderer::class)->render($page->load('sections'));
    }

    /**
     * The rendered fixture contains the entire about.html body (only the hero
     * PageSection is backed by real content); this isolates just the hero
     * media element so assertions aren't polluted by unrelated placeholders.
     */
    private function heroFragment(string $html): string
    {
        preg_match('/<(img|div) class="about-hero-media[^"]*"[^>]*>(?:<span>[^<]*<\/span>)?/', $html, $matches);
        $this->assertNotEmpty($matches, 'Expected an about-hero-media element in the rendered output.');

        return $matches[0];
    }

    private function imageAsset(): MediaAsset
    {
        $asset = MediaAsset::query()->create([
            'kind' => MediaKind::Image->value,
            'title' => 'Celebration',
            'alt_text' => 'Celebration photo',
        ]);
        $asset->addMediaFromString('fake-image-bytes')
            ->usingFileName('celebration.jpg')
            ->toMediaCollection('file');

        return $asset->refresh();
    }

    /**
     * Builds a Page/PageSection for the real `.about-hero` template using the
     * actual SectionContentExtractor, so the locator/field id under test always
     * matches what the extractor genuinely produces from the on-disk template.
     */
    private function pageWithHeroField(string $payloadValue): array
    {
        $registry = app(PageSchemaRegistry::class);
        $html = file_get_contents($registry->templatePath('about'));
        $extracted = app(SectionContentExtractor::class)->extract($html, '.about-hero-media');

        $fieldId = collect($extracted['schema']['fields'])
            ->first(fn (array $field) => ($field['input'] ?? null) === 'media')['id'];

        $page = Page::query()->create([
            'slug' => 'about',
            'template_key' => 'about',
            'title' => 'About',
            'status' => PublicationStatus::Published,
            'workflow_status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);

        $section = PageSection::query()->create([
            'page_id' => $page->id,
            'section_key' => 'hero',
            'label' => 'Hero',
            'position' => 1,
            'field_schema' => $extracted['schema'],
            'payload' => array_replace($extracted['payload'], [$fieldId => $payloadValue]),
        ]);

        return [$page->refresh()->load('sections'), $section->refresh(), $fieldId];
    }
}
