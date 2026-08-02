<?php

namespace App\Domain\Content;

use App\Enums\FieldGroup;

/**
 * Infers a FieldGroup for a page-section field from the metadata the
 * DOM extractor already records (input type, attribute, owner tag), since
 * imported sections predate the granular-permission feature and were never
 * tagged at extraction time. Runtime inference means existing sections work
 * without a data backfill.
 */
class FieldGroupClassifier
{
    public function classify(array $field, string $sectionKey): FieldGroup
    {
        $input = $field['input'] ?? 'text';
        $kind = $field['kind'] ?? 'text';
        $attribute = $field['attribute'] ?? null;
        $tag = $this->ownerTag($field['label'] ?? '');

        if ($input === 'icon') {
            return FieldGroup::Icon;
        }

        if ($input === 'media') {
            return $this->isGallerySection($sectionKey) ? FieldGroup::Gallery : FieldGroup::Banner;
        }

        if ($input === 'url' || $attribute === 'href') {
            return FieldGroup::ButtonLink;
        }

        if ($kind === 'attribute' && in_array($attribute, ['alt', 'aria-label', 'title', 'placeholder'], true)) {
            return in_array($tag, ['a', 'button'], true) ? FieldGroup::ButtonLabel : FieldGroup::Text;
        }

        if ($kind === 'text' && in_array($tag, ['a', 'button'], true)) {
            return FieldGroup::ButtonLabel;
        }

        if ($kind === 'text' && preg_match('/^h[1-6]$/', (string) $tag) === 1) {
            return FieldGroup::Heading;
        }

        if ($kind === 'text' && $this->isStatSection($sectionKey)) {
            return FieldGroup::Statistic;
        }

        return FieldGroup::Text;
    }

    private function ownerTag(string $label): string
    {
        $firstWord = strtok($label, " \t\n");

        return strtolower($firstWord ?: '');
    }

    private function isGallerySection(string $sectionKey): bool
    {
        return (bool) preg_match('/gallery|community|family|social/i', $sectionKey);
    }

    private function isStatSection(string $sectionKey): bool
    {
        return str_contains($sectionKey, 'stat');
    }
}
