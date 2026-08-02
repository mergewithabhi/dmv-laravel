<?php

namespace App\Domain\Content;

use App\Models\PageSection;
use DOMElement;
use DOMNode;
use DOMXPath;

class PageEditorSchema
{
    private const DYNAMIC_CONTAINERS = [
        'home' => [
            'news-grid',
            'players',
            'next-game',
            'schedule-section',
            'partner-logos',
        ],
        'about' => ['leadership-grid'],
        'roster' => ['full-roster-grid', 'coaching-grid'],
        'schedule' => [
            'schedule-next-game',
            'season-table',
            'standings-panel',
            'season-stats',
            'home-venue',
            'schedule-partners',
        ],
        'sponsors' => ['sponsor-tier'],
    ];

    private const TECHNICAL_ATTRIBUTES = [
        'aria-label',
        'data-game-date',
        'datetime',
        'value',
    ];

    public function __construct(
        private readonly PageSchemaRegistry $registry,
        private readonly SectionContentExtractor $extractor
    ) {}

    public function fields(string $templateKey, PageSection $section): array
    {
        $fields = $section->field_schema['fields'] ?? [];
        if ($fields === []) {
            return [];
        }

        $hidden = $this->dynamicFieldIds($templateKey, $section, $fields);
        $counts = [];
        $imageNumber = 0;
        $buttonNumber = 0;
        $lastImageNumber = null;
        $result = [];

        foreach ($fields as $fieldId => $field) {
            if (isset($hidden[$fieldId]) || $this->isTechnical($field)) {
                continue;
            }

            $input = $field['input'] ?? 'text';
            $tag = $this->tag($field);
            $attribute = $field['attribute'] ?? null;

            if ($input === 'icon') {
                $lastImageNumber = null;
                continue;
            }
            if ($tag === 'img' && $attribute === 'alt') {
                if ($lastImageNumber === null) {
                    continue;
                }
                $field['editor_label'] = "Image {$lastImageNumber} description";
                $field['editor_help'] = 'Describe the image for visitors using screen readers.';
                $field['editor_group'] = 'images';
                $result[$fieldId] = $field;

                continue;
            }
            if ($input === 'media') {
                $lastImageNumber = ++$imageNumber;
                $field['editor_label'] = $imageNumber === 1 ? 'Main image' : "Image {$imageNumber}";
                $field['editor_group'] = 'images';
                $result[$fieldId] = $field;

                continue;
            }

            $lastImageNumber = null;
            if ($input === 'url' || $attribute === 'href') {
                $buttonNumber++;
                $field['editor_label'] = $buttonNumber === 1 ? 'Button link' : "Button {$buttonNumber} link";
                $field['editor_help'] = 'Use a website address or a page path such as /contact.';
                $field['editor_group'] = 'buttons';
                $result[$fieldId] = $field;

                continue;
            }

            $role = match (true) {
                preg_match('/^h[1-6]$/', $tag) === 1 => 'Heading',
                $tag === 'a' || $tag === 'button' => 'Button text',
                in_array($tag, ['p', 'div'], true) => 'Description',
                in_array($tag, ['label', 'option'], true) => 'Form text',
                in_array($tag, ['strong', 'b', 'em'], true) => 'Highlighted text',
                in_array($tag, ['dt', 'th'], true) => 'Label',
                in_array($tag, ['dd', 'td'], true) => 'Value',
                default => 'Text',
            };
            $counts[$role] = ($counts[$role] ?? 0) + 1;
            $currentValue = trim((string) ($section->payload[$fieldId] ?? ''));
            $context = mb_strlen($currentValue) > 38 ? mb_substr($currentValue, 0, 35).'...' : $currentValue;
            $field['editor_label'] = $context !== '' && ! in_array($role, ['Description', 'Text', 'Value'], true)
                ? "{$role}: {$context}"
                : ($counts[$role] === 1 ? $role : "{$role} {$counts[$role]}");
            $field['editor_group'] = $role === 'Button text' ? 'buttons' : 'content';
            $result[$fieldId] = $field;
        }

        return $result;
    }

    private function dynamicFieldIds(string $templateKey, PageSection $section, array $fields): array
    {
        $classes = self::DYNAMIC_CONTAINERS[$templateKey] ?? [];
        if ($classes === []) {
            return [];
        }
        $selector = $section->field_schema['selector'] ?? null;
        if (! $selector) {
            return [];
        }

        $html = file_get_contents($this->registry->templatePath($templateKey));
        [$document, $root] = $this->extractor->document($html);
        $sectionNode = $this->extractor->findBySelector(
            $document,
            $root,
            $selector
        );
        if (! $sectionNode) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $hidden = [];
        foreach ($fields as $fieldId => $field) {
            $locator = $field['locator'] ?? null;
            if (! $locator) {
                continue;
            }
            $node = $locator === '.' ? $sectionNode : $xpath->query($locator, $sectionNode)?->item(0);
            if ($node && $this->hasDynamicAncestor($node, $sectionNode, $classes)) {
                $hidden[$fieldId] = true;
            }
        }

        return $hidden;
    }

    private function hasDynamicAncestor(DOMNode $node, DOMElement $section, array $classes): bool
    {
        $current = $node instanceof DOMElement ? $node : $node->parentNode;
        while ($current instanceof DOMElement) {
            $nodeClasses = preg_split('/\s+/', trim($current->getAttribute('class'))) ?: [];
            if (array_intersect($classes, $nodeClasses) !== []) {
                return true;
            }
            if ($current->isSameNode($section)) {
                break;
            }
            $current = $current->parentNode;
        }

        return false;
    }

    private function isTechnical(array $field): bool
    {
        $value = trim((string) ($field['label'] ?? ''));

        return in_array($field['attribute'] ?? null, self::TECHNICAL_ATTRIBUTES, true)
            || preg_match('/^[★◇@|]+$/u', str_replace(' ', '', str($value)->after(' - ')->value())) === 1;
    }

    private function tag(array $field): string
    {
        return strtolower((string) strtok($field['label'] ?? '', " \t\n"));
    }
}
