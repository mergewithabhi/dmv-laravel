<?php

namespace Tests\Unit;

use App\Domain\Content\PageEditorSchema;
use App\Models\PageSection;
use Tests\TestCase;

class PageEditorSchemaTest extends TestCase
{
    public function test_it_hides_technical_fields_and_uses_human_labels(): void
    {
        $section = new PageSection([
            'section_key' => 'hero',
            'field_schema' => [
                'selector' => '.hero',
                'fields' => [
                    'image' => ['label' => 'IMG src', 'input' => 'media', 'attribute' => 'src', 'locator' => './img[1]'],
                    'alt' => ['label' => 'IMG alt', 'input' => 'text', 'attribute' => 'alt', 'locator' => './img[1]'],
                    'heading' => ['label' => 'H1 - Welcome', 'input' => 'text', 'kind' => 'text', 'locator' => './h1[1]/text()[1]'],
                    'aria' => ['label' => 'SECTION aria-label', 'input' => 'text', 'attribute' => 'aria-label', 'locator' => '.'],
                ],
            ],
            'payload' => ['image' => null, 'alt' => 'Team entering the court', 'heading' => 'Welcome', 'aria' => 'Hero'],
        ]);

        $fields = app(PageEditorSchema::class)->fields('home', $section);

        $this->assertSame('Main image', $fields['image']['editor_label']);
        $this->assertSame('Image 1 description', $fields['alt']['editor_label']);
        $this->assertSame('Heading: Welcome', $fields['heading']['editor_label']);
        $this->assertArrayNotHasKey('aria', $fields);
    }

    public function test_it_hides_home_instagram_feed_fields_from_the_page_editor(): void
    {
        $section = new PageSection([
            'section_key' => 'community',
            'field_schema' => [
                'selector' => '.community-grid',
                'fields' => [
                    'community_heading' => [
                        'label' => 'H2 - Our Commitment to the DMV',
                        'input' => 'text',
                        'kind' => 'text',
                        'locator' => './div[1]/h2[1]/text()[1]',
                    ],
                    'instagram_heading' => [
                        'label' => 'H2 - DMVWarriors',
                        'input' => 'text',
                        'kind' => 'text',
                        'locator' => './div[2]/div[1]/h2[1]/text()[1]',
                    ],
                    'instagram_image' => [
                        'label' => 'IMG src',
                        'input' => 'media',
                        'kind' => 'attribute',
                        'attribute' => 'src',
                        'locator' => './div[2]/div[2]/img[1]',
                    ],
                    'instagram_url' => [
                        'label' => 'A href',
                        'input' => 'url',
                        'kind' => 'attribute',
                        'attribute' => 'href',
                        'locator' => './div[2]/div[3]/a[1]',
                    ],
                ],
            ],
            'payload' => [
                'community_heading' => 'Our Commitment to the DMV',
                'instagram_heading' => 'DMVWarriors',
                'instagram_image' => null,
                'instagram_url' => 'https://instagram.com/',
            ],
        ]);

        $fields = app(PageEditorSchema::class)->fields('home', $section);

        $this->assertArrayHasKey('community_heading', $fields);
        $this->assertArrayNotHasKey('instagram_heading', $fields);
        $this->assertArrayNotHasKey('instagram_image', $fields);
        $this->assertArrayNotHasKey('instagram_url', $fields);
    }
}
