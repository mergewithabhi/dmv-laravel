<?php

namespace App\Domain\Content;

use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSection;
use DOMElement;
use DOMText;
use DOMXPath;
use Illuminate\Support\HtmlString;

class FixedTemplateRenderer
{
    public function __construct(
        private readonly PageSchemaRegistry $registry,
        private readonly SectionContentExtractor $extractor,
        private readonly SiteTemplateHydrator $hydrator
    ) {}

    public function render(Page $page, array $context = []): HtmlString
    {
        $path = $this->registry->templatePath($page->template_key);
        $html = file_get_contents($path);
        [$document, $root] = $this->extractor->document($html);
        $media = MediaAsset::query()
            ->with('media')
            ->whereIn('id', $this->mediaIds($page))
            ->get()
            ->keyBy('id');

        foreach ($page->sections as $section) {
            $selector = $section->field_schema['selector'] ?? null;
            if (! $selector) {
                continue;
            }

            $sectionNode = $this->extractor->findBySelector($document, $root, $selector);
            if (! $sectionNode) {
                continue;
            }

            if (! $section->is_enabled) {
                $sectionNode->parentNode?->removeChild($sectionNode);

                continue;
            }

            $this->applyFields($document, $sectionNode, $section, $media);
        }

        $this->hydrator->hydrate($page->template_key, $document, $root);
        $this->rewriteLegacyLinks($document, $root);
        $this->addLivewireBindings($document, $root);
        $this->applyFormErrors($document, $root, $context['form_errors'] ?? []);
        $this->applyFormStatus($document, $root, $context);

        $rendered = '';
        foreach ($root->childNodes as $child) {
            $rendered .= $document->saveHTML($child);
        }

        return new HtmlString($rendered);
    }

    private function applyFields(
        \DOMDocument $document,
        DOMElement $sectionNode,
        PageSection $section,
        $media
    ): void {
        $xpath = new DOMXPath($document);

        // Locators are tag-position based (e.g. `img[2]`), so every node must be
        // resolved up front, before any mutation — replacing one <img> with a
        // placeholder <div> would otherwise shift the img-count and misresolve
        // every subsequent field's locator in this section.
        $resolved = [];
        foreach (($section->field_schema['fields'] ?? []) as $id => $field) {
            if (! array_key_exists($id, $section->payload)) {
                continue;
            }

            $node = $field['locator'] === '.'
                ? $sectionNode
                : $xpath->query($field['locator'], $sectionNode)?->item(0);

            if ($node) {
                $resolved[] = [$field, $node, $section->payload[$id]];
            }
        }

        foreach ($resolved as [$field, $node, $value]) {
            $input = $field['input'] ?? '';
            $isMediaSrc = in_array($input, ['media', 'icon'], true)
                && ($field['kind'] ?? null) === 'attribute'
                && ($field['attribute'] ?? null) === 'src'
                && $node instanceof DOMElement
                && strtolower($node->tagName) === 'img';

            if (in_array($input, ['media', 'icon'], true)) {
                $asset = is_numeric($value) ? $media->get((int) $value) : null;
                $resolvedUrl = $asset
                    ? ($input === 'media' ? ($asset->url('web') ?: $asset->url() ?: '') : ($asset->url() ?: ''))
                    : '';

                if ($isMediaSrc) {
                    if ($resolvedUrl === '') {
                        $this->replaceWithPlaceholder($document, $node);

                        continue;
                    }

                    $node->setAttribute('src', $resolvedUrl);
                    if ($input === 'media') {
                        $thumb = $asset?->url('thumb');
                        $web = $asset?->url('web');
                        if ($thumb && $web) {
                            $node->setAttribute('srcset', "{$thumb} 480w, {$web} 1600w");
                            $node->setAttribute('sizes', '(max-width: 720px) 100vw, 50vw');
                        }

                        $isHero = str_contains($section->section_key, 'hero');
                        $node->setAttribute('loading', $isHero ? 'eager' : 'lazy');
                        $node->setAttribute('decoding', 'async');
                        if ($isHero) {
                            $node->setAttribute('fetchpriority', 'high');
                        }
                    }

                    continue;
                }

                $value = $resolvedUrl;
            }

            if (($field['kind'] ?? null) === 'attribute' && $node instanceof DOMElement) {
                $node->setAttribute($field['attribute'], (string) $value);
            } elseif ($node instanceof DOMText) {
                $node->nodeValue = (string) $value;
            }
        }
    }

    private function replaceWithPlaceholder(\DOMDocument $document, DOMElement $img): void
    {
        $sizingClass = trim((string) preg_replace('/\s+/', ' ', $img->getAttribute('class')));
        $label = $img->getAttribute('data-placeholder-label') ?: 'Image';

        $placeholder = $document->createElement('div');
        $placeholder->setAttribute('class', trim($sizingClass.' media-placeholder'));

        foreach (['data-reveal', 'data-motion-media', 'role', 'aria-label', 'aria-hidden', 'id'] as $attribute) {
            if ($img->hasAttribute($attribute)) {
                $placeholder->setAttribute($attribute, $img->getAttribute($attribute));
            }
        }
        if (! $placeholder->hasAttribute('role')) {
            $placeholder->setAttribute('role', 'img');
        }

        $span = $document->createElement('span');
        $span->appendChild($document->createTextNode($label));
        $placeholder->appendChild($span);

        $img->parentNode?->replaceChild($placeholder, $img);
    }

    private function mediaIds(Page $page): array
    {
        return $page->sections
            ->flatMap(function (PageSection $section): array {
                $ids = [];
                foreach (($section->field_schema['fields'] ?? []) as $id => $field) {
                    if (
                        in_array($field['input'] ?? '', ['media', 'icon'], true)
                        && is_numeric($section->payload[$id] ?? null)
                    ) {
                        $ids[] = (int) $section->payload[$id];
                    }
                }

                return $ids;
            })
            ->unique()
            ->values()
            ->all();
    }

    private function rewriteLegacyLinks(\DOMDocument $document, DOMElement $root): void
    {
        $xpath = new DOMXPath($document);
        $redirects = config('cms.legacy_redirects', []);

        foreach ($xpath->query('.//a[@href]', $root) ?: [] as $anchor) {
            $href = $anchor->getAttribute('href');
            [$path, $fragment] = array_pad(explode('#', $href, 2), 2, null);
            $normalized = '/'.ltrim($path, '/');

            if (isset($redirects[$normalized])) {
                $anchor->setAttribute(
                    'href',
                    $redirects[$normalized].($fragment ? '#'.$fragment : '')
                );
            }

            $updatedHref = $anchor->getAttribute('href');
            if (str_starts_with($updatedHref, '/') && ! str_starts_with($updatedHref, '//')) {
                $anchor->setAttribute('href', url($updatedHref));
                $anchor->setAttribute('wire:navigate', '');
            }
        }
    }

    private function addLivewireBindings(\DOMDocument $document, DOMElement $root): void
    {
        $xpath = new DOMXPath($document);
        $forms = [
            'newsletter-form' => [
                'action' => 'submitNewsletter',
                'prefix' => null,
                'consent' => 'newsletterConsent',
            ],
            'contact-form' => [
                'action' => 'submitContact',
                'prefix' => 'contact',
                'consent' => 'contactConsent',
            ],
            'sponsor-request-form' => [
                'action' => 'submitSponsor',
                'prefix' => 'sponsor',
                'consent' => 'sponsorConsent',
            ],
        ];

        foreach ($forms as $class => $binding) {
            $query = ".//form[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
            $form = $xpath->query($query, $root)?->item(0);
            if (! $form instanceof DOMElement) {
                continue;
            }

            $form->setAttribute('wire:submit', $binding['action']);
            $form->setAttribute('data-livewire-form', $binding['action']);

            foreach ($xpath->query('.//*[@name]', $form) ?: [] as $control) {
                $name = $control->getAttribute('name');
                $model = $name === 'consent'
                    ? $binding['consent']
                    : ($binding['prefix'] ? $binding['prefix'].'.'.$name : 'newsletterEmail');
                $control->setAttribute('wire:model', $model);
            }

            $honeypot = $document->createElement('input');
            $honeypot->setAttribute('type', 'text');
            $honeypot->setAttribute('name', 'website');
            $honeypot->setAttribute('wire:model', 'website');
            $honeypot->setAttribute('class', 'form-honeypot');
            $honeypot->setAttribute('tabindex', '-1');
            $honeypot->setAttribute('autocomplete', 'off');
            $honeypot->setAttribute('aria-hidden', 'true');
            $form->appendChild($honeypot);

            if (config('services.turnstile.enabled') && config('services.turnstile.site_key')) {
                $token = $document->createElement('input');
                $token->setAttribute('type', 'hidden');
                $token->setAttribute('wire:model', 'turnstileToken');
                $token->setAttribute('data-turnstile-token', '');
                $form->appendChild($token);

                $widget = $document->createElement('div');
                $widget->setAttribute('class', 'cf-turnstile');
                $widget->setAttribute('data-sitekey', (string) config('services.turnstile.site_key'));
                $form->appendChild($widget);
            }
        }
    }

    private function applyFormStatus(\DOMDocument $document, DOMElement $root, array $context): void
    {
        $xpath = new DOMXPath($document);
        $statuses = [
            '.form-message' => $context['newsletter_message'] ?? null,
            '.contact-form-status' => $context['contact_message'] ?? null,
            '.sponsor-form-status' => $context['sponsor_message'] ?? null,
        ];

        foreach ($statuses as $selector => $message) {
            if (! $message) {
                continue;
            }

            $class = substr($selector, 1);
            $query = ".//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
            $node = $xpath->query($query, $root)?->item(0);
            if ($node instanceof DOMElement) {
                $node->nodeValue = $message;
                $node->setAttribute('tabindex', '-1');
                $hasErrors = match ($selector) {
                    '.form-message' => $this->hasAnyError(
                        $context['form_errors'] ?? [],
                        ['newsletterEmail', 'newsletterConsent']
                    ),
                    '.contact-form-status' => $this->hasAnyError(
                        $context['form_errors'] ?? [],
                        ['contact.', 'contactConsent']
                    ),
                    '.sponsor-form-status' => $this->hasAnyError(
                        $context['form_errors'] ?? [],
                        ['sponsor.', 'sponsorConsent']
                    ),
                    default => false,
                };
                if ($hasErrors) {
                    $node->setAttribute('role', 'alert');
                    $node->setAttribute('aria-live', 'assertive');
                    $node->setAttribute('class', trim($node->getAttribute('class').' is-error'));
                }
            }
        }
    }

    private function applyFormErrors(
        \DOMDocument $document,
        DOMElement $root,
        array $errors
    ): void {
        if ($errors === []) {
            return;
        }

        $xpath = new DOMXPath($document);
        $forms = [
            'newsletter-form' => ['prefix' => null, 'consent' => 'newsletterConsent'],
            'contact-form' => ['prefix' => 'contact', 'consent' => 'contactConsent'],
            'sponsor-request-form' => ['prefix' => 'sponsor', 'consent' => 'sponsorConsent'],
        ];

        foreach ($forms as $class => $binding) {
            $formQuery = ".//form[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
            $form = $xpath->query($formQuery, $root)?->item(0);
            if (! $form instanceof DOMElement) {
                continue;
            }

            foreach ($xpath->query('.//*[@name]', $form) ?: [] as $control) {
                if (! $control instanceof DOMElement) {
                    continue;
                }

                $name = $control->getAttribute('name');
                $key = $name === 'consent'
                    ? $binding['consent']
                    : ($binding['prefix'] ? "{$binding['prefix']}.{$name}" : 'newsletterEmail');
                $message = $errors[$key][0] ?? null;
                if (! $message) {
                    continue;
                }

                $id = $control->getAttribute('id') ?: 'error-field-'.str_replace('.', '-', $key);
                $errorId = $id.'-error';
                $control->setAttribute('id', $id);
                $control->setAttribute('aria-invalid', 'true');
                $describedBy = trim($control->getAttribute('aria-describedby').' '.$errorId);
                $control->setAttribute('aria-describedby', $describedBy);

                $error = $document->createElement('span');
                $error->setAttribute('id', $errorId);
                $error->setAttribute('class', 'form-field-error');
                $error->setAttribute('role', 'alert');
                $error->appendChild($document->createTextNode((string) $message));

                if ($control->getAttribute('type') === 'checkbox') {
                    $control->parentNode?->appendChild($error);
                } else {
                    $control->parentNode?->insertBefore($error, $control->nextSibling);
                }
            }
        }
    }

    private function hasAnyError(array $errors, array $keysOrPrefixes): bool
    {
        foreach ($keysOrPrefixes as $keyOrPrefix) {
            foreach (array_keys($errors) as $key) {
                if ($key === $keyOrPrefix || str_starts_with($key, $keyOrPrefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
