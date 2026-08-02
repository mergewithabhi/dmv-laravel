<?php

namespace App\Domain\Content;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use RuntimeException;

class SectionContentExtractor
{
    private const CONTENT_ATTRIBUTES = [
        'src',
        'href',
        'alt',
        'aria-label',
        'title',
        'placeholder',
        'datetime',
        'data-game-date',
        'value',
    ];

    public function extract(string $html, string $selector, ?callable $mediaResolver = null): array
    {
        [$document, $root] = $this->document($html);
        $section = $this->findBySelector($document, $root, $selector);

        if (! $section) {
            throw new RuntimeException("Template selector [{$selector}] was not found.");
        }

        $fields = [];
        $payload = [];
        $counter = 0;

        $walker = function (DOMNode $node) use (
            &$walker,
            &$fields,
            &$payload,
            &$counter,
            $section,
            $mediaResolver
        ): void {
            if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['script', 'style'], true)) {
                return;
            }

            if ($node instanceof DOMText && trim($node->nodeValue) !== '') {
                $id = sprintf('field_%03d', ++$counter);
                $value = trim($node->nodeValue);
                $fields[$id] = [
                    'id' => $id,
                    'kind' => 'text',
                    'locator' => $this->locator($node, $section),
                    'input' => mb_strlen($value) > 90 ? 'textarea' : 'text',
                    'label' => $this->fieldLabel($node, $value),
                    'max' => 5000,
                ];
                $payload[$id] = $value;
            }

            if ($node instanceof DOMElement) {
                foreach (self::CONTENT_ATTRIBUTES as $attributeName) {
                    if (! $node->hasAttribute($attributeName)) {
                        continue;
                    }

                    $attribute = $node->getAttributeNode($attributeName);
                    $this->extractAttribute(
                        $attribute,
                        $section,
                        $fields,
                        $payload,
                        $counter,
                        $mediaResolver
                    );
                }
            }

            foreach ($node->childNodes as $child) {
                $walker($child);
            }
        };

        $walker($section);

        return [
            'schema' => [
                'selector' => $selector,
                'fields' => $fields,
            ],
            'payload' => $payload,
        ];
    }

    private function extractAttribute(
        DOMAttr $attribute,
        DOMElement $root,
        array &$fields,
        array &$payload,
        int &$counter,
        ?callable $mediaResolver
    ): void {
        $id = sprintf('field_%03d', ++$counter);
        $value = $attribute->value;
        $input = match ($attribute->name) {
            'src' => str_contains($value, '/icons/') ? 'icon' : 'media',
            'href' => 'url',
            'datetime', 'data-game-date' => 'datetime',
            default => 'text',
        };

        if (in_array($input, ['media', 'icon'], true) && $mediaResolver) {
            $resolved = $mediaResolver($value, $input);
            $value = $resolved ?: $value;
        }

        $fields[$id] = [
            'id' => $id,
            'kind' => 'attribute',
            'attribute' => $attribute->name,
            'locator' => $this->locator($attribute->ownerElement, $root),
            'input' => $input,
            'label' => $this->attributeLabel($attribute),
            'max' => 2048,
        ];
        $payload[$id] = $value;
    }

    public function document(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="cms-template-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('cms-template-root');

        if (! $root) {
            throw new RuntimeException('Could not parse the fixed page template.');
        }

        return [$document, $root];
    }

    public function findBySelector(DOMDocument $document, DOMElement $root, string $selector): ?DOMElement
    {
        $xpath = new DOMXPath($document);

        if (str_starts_with($selector, '#')) {
            $query = ".//*[@id='{$this->xpathValue(substr($selector, 1))}']";
        } elseif (str_starts_with($selector, '.')) {
            $class = $this->xpathValue(substr($selector, 1));
            $query = ".//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
        } else {
            $query = './/'.strtolower($selector);
        }

        $node = $xpath->query($query, $root)?->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function locator(DOMNode $node, DOMElement $root): string
    {
        if ($node->isSameNode($root)) {
            return '.';
        }

        $segments = [];
        $current = $node;

        while ($current && ! $current->isSameNode($root)) {
            if ($current instanceof DOMText) {
                $segments[] = 'text()['.$this->siblingPosition($current, true).']';
            } elseif ($current instanceof DOMElement) {
                $segments[] = strtolower($current->tagName).'['.$this->siblingPosition($current).']';
            }

            $current = $current->parentNode;
        }

        return './'.implode('/', array_reverse($segments));
    }

    private function siblingPosition(DOMNode $node, bool $textOnly = false): int
    {
        $position = 1;
        $sibling = $node->previousSibling;

        while ($sibling) {
            if (
                ($textOnly && $sibling instanceof DOMText)
                || (! $textOnly && $sibling instanceof DOMElement && $sibling->nodeName === $node->nodeName)
            ) {
                $position++;
            }
            $sibling = $sibling->previousSibling;
        }

        return $position;
    }

    private function fieldLabel(DOMText $node, string $value): string
    {
        $parent = $node->parentNode instanceof DOMElement ? strtolower($node->parentNode->tagName) : 'text';
        $summary = mb_strlen($value) > 48 ? mb_substr($value, 0, 45).'...' : $value;

        return strtoupper($parent).' - '.$summary;
    }

    private function attributeLabel(DOMAttr $attribute): string
    {
        $tag = strtoupper($attribute->ownerElement->tagName);

        return "{$tag} {$attribute->name}";
    }

    private function xpathValue(string $value): string
    {
        return str_replace("'", '', $value);
    }
}
