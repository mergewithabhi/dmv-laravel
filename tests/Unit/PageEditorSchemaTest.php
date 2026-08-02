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
}
