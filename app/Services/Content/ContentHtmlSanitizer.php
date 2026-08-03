<?php

namespace App\Services\Content;

use DOMDocument;
use DOMElement;
use DOMNode;

class ContentHtmlSanitizer
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'p' => ['dir', 'text-align'],
        'br' => [],
        'h1' => ['dir', 'text-align'],
        'h2' => ['dir', 'text-align'],
        'h3' => ['dir', 'text-align'],
        'h4' => ['dir', 'text-align'],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'mark' => [],
        'code' => [],
        'pre' => [],
        'blockquote' => [],
        'hr' => [],
        'ul' => ['data-type'],
        'ol' => ['start'],
        'li' => ['data-checked'],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen', 'title', 'loading'],
    ];

    private const DROP_WITH_CONTENT = ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option'];

    public function sanitize(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="content-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('content-root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        foreach (iterator_to_array($root->childNodes) as $child) {
            $this->cleanNode($child);
        }

        $clean = '';

        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        return trim($clean);
    }

    private function cleanNode(DOMNode $node): void
    {
        if (! $node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);

        if (! array_key_exists($tag, self::ALLOWED)) {
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->parentNode?->removeChild($node);

                return;
            }

            $this->unwrap($node);

            return;
        }

        if ($tag === 'iframe' && ! $this->isAllowedYoutubeEmbed($node->getAttribute('src'))) {
            $node->parentNode?->removeChild($node);

            return;
        }

        foreach (iterator_to_array($node->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if (! in_array($name, self::ALLOWED[$tag], true)) {
                $node->removeAttribute($attribute->name);

                continue;
            }

            if ($name === 'href' && ! $this->isSafeLink($attribute->value)) {
                $node->removeAttribute('href');
            }

            if ($name === 'src' && $tag === 'img' && ! $this->isSafeImage($attribute->value)) {
                $node->removeAttribute('src');
            }

            if ($name === 'dir' && ! in_array($attribute->value, ['rtl', 'ltr', 'auto'], true)) {
                $node->removeAttribute('dir');
            }

            if ($name === 'text-align' && ! in_array($attribute->value, ['right', 'left', 'center', 'justify'], true)) {
                $node->removeAttribute('text-align');
            }
        }

        if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
            $node->setAttribute('rel', 'noopener noreferrer');
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->cleanNode($child);
        }
    }

    private function unwrap(DOMElement $node): void
    {
        $parent = $node->parentNode;

        if ($parent === null) {
            return;
        }

        while ($node->firstChild !== null) {
            $child = $node->firstChild;
            $parent->insertBefore($child, $node);
            $this->cleanNode($child);
        }

        $parent->removeChild($node);
    }

    private function isSafeLink(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }

    private function isSafeImage(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return true;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function isAllowedYoutubeEmbed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        return in_array($host, ['www.youtube-nocookie.com', 'youtube-nocookie.com'], true)
            && str_starts_with($path, '/embed/');
    }
}
