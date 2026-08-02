<?php

namespace App\Livewire\Site\Support;

use DOMDocument;
use DOMElement;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

final class PublicInteractionNormalizer
{
    public function normalize(Htmlable|string $content): HtmlString
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="public-content-root">'.(string) $content.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('public-content-root');
        if (! $root instanceof DOMElement) {
            return new HtmlString((string) $content);
        }

        foreach (iterator_to_array($root->getElementsByTagName('a')) as $anchor) {
            if (! $anchor instanceof DOMElement || ! $anchor->hasAttribute('href')) {
                continue;
            }

            $href = self::url($anchor->getAttribute('href'));
            $anchor->setAttribute('href', $href);

            if ($anchor->hasAttribute('data-safe-href')) {
                $anchor->setAttribute(
                    'data-safe-href',
                    self::url($anchor->getAttribute('data-safe-href'))
                );
            }

            if (
                self::isInternal($href)
                && ! $anchor->hasAttribute('download')
                && $anchor->getAttribute('target') !== '_blank'
                && ! self::isDownload($href)
            ) {
                $anchor->setAttribute('wire:navigate', '');
            } else {
                $anchor->removeAttribute('wire:navigate');
            }
        }

        $this->replaceDownloadButton(
            $document,
            $root,
            'data-download-schedule',
            route('schedule.calendar')
        );
        $this->replaceDownloadButton(
            $document,
            $root,
            'data-download-sponsor',
            route('sponsor-pack')
        );

        foreach (iterator_to_array($root->getElementsByTagName('form')) as $form) {
            if (! $form instanceof DOMElement || ! $form->hasAttribute('data-livewire-form')) {
                continue;
            }

            $action = $form->getAttribute('data-livewire-form');
            foreach (iterator_to_array($form->getElementsByTagName('button')) as $button) {
                if ($button instanceof DOMElement && strtolower($button->getAttribute('type')) === 'submit') {
                    $button->setAttribute('wire:loading.attr', 'disabled');
                    $button->setAttribute('wire:target', $action);
                }
            }
        }

        $html = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $html .= $document->saveHTML($child);
        }

        return new HtmlString($html);
    }

    public static function url(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return url('/');
        }

        if (
            str_starts_with($value, '#')
            || str_starts_with($value, '?')
            || preg_match('/^(?:mailto|tel|sms):/i', $value) === 1
            || str_starts_with($value, '//')
        ) {
            return $value;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $value) === 1) {
            return preg_match('/^https?:/i', $value) === 1 ? $value : '#';
        }

        return url('/'.ltrim($value, '/'));
    }

    public static function isInternal(string $value): bool
    {
        if (! preg_match('/^https?:\/\//i', $value)) {
            return false;
        }

        $base = parse_url(url('/'));
        $candidate = parse_url($value);
        if (! is_array($base) || ! is_array($candidate)) {
            return false;
        }

        $sameOrigin = strtolower((string) ($base['scheme'] ?? '')) === strtolower((string) ($candidate['scheme'] ?? ''))
            && strtolower((string) ($base['host'] ?? '')) === strtolower((string) ($candidate['host'] ?? ''))
            && ($base['port'] ?? null) === ($candidate['port'] ?? null);
        if (! $sameOrigin) {
            return false;
        }

        $basePath = rtrim((string) ($base['path'] ?? ''), '/');
        $candidatePath = (string) ($candidate['path'] ?? '/');

        return $basePath === ''
            || $candidatePath === $basePath
            || str_starts_with($candidatePath, $basePath.'/');
    }

    private static function isDownload(string $value): bool
    {
        $path = rtrim((string) parse_url($value, PHP_URL_PATH), '/');

        return str_ends_with($path, '/schedule/calendar.ics')
            || str_ends_with($path, '/sponsor-pack');
    }

    private function replaceDownloadButton(
        DOMDocument $document,
        DOMElement $root,
        string $attribute,
        string $href
    ): void {
        $buttons = [];
        foreach (iterator_to_array($root->getElementsByTagName('button')) as $button) {
            if ($button instanceof DOMElement && $button->hasAttribute($attribute)) {
                $buttons[] = $button;
            }
        }

        foreach ($buttons as $button) {
            $link = $document->createElement('a');
            foreach (iterator_to_array($button->attributes) as $node) {
                if (! in_array($node->nodeName, ['type', $attribute], true)) {
                    $link->setAttribute($node->nodeName, $node->nodeValue);
                }
            }
            $link->setAttribute('href', $href);
            $link->setAttribute('download', '');

            while ($button->firstChild) {
                $link->appendChild($button->firstChild);
            }

            $button->parentNode?->replaceChild($link, $button);
        }
    }
}
