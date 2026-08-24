<?php

namespace App\Support\Html;

use DOMDocument;
use DOMElement;
use DOMNode;

final class EditorialHtml
{
    /**
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'h2', 'h3', 'h4', 'ul', 'ol', 'li',
        'a', 'img', 'blockquote', 'hr',
        'figure', 'figcaption', 'div', 'span',
        'table', 'thead', 'tbody', 'tr', 'td', 'th',
        'pre', 'code',
        'svg', 'path',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'rel', 'target', 'class'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'class'],
        'div' => ['class'],
        'span' => ['class'],
        'p' => ['class'],
        'h2' => ['class'],
        'h3' => ['class'],
        'h4' => ['class'],
        'figure' => ['class'],
        'figcaption' => ['class'],
        'ul' => ['class'],
        'ol' => ['class'],
        'li' => ['class'],
        'blockquote' => ['class'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        'svg' => ['class', 'viewbox', 'fill', 'stroke', 'width', 'height', 'xmlns', 'aria-hidden'],
        'path' => ['d', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'class'],
    ];

    public function sanitize(?string $html): string
    {
        $parsed = $this->parse($html);

        if ($parsed === null) {
            return '';
        }

        [$document, $root] = $parsed;
        $this->scrubNode($root);

        return $this->serialize($document, $root);
    }

    /**
     * @return array{0: DOMDocument, 1: DOMElement}|null
     */
    public function parse(?string $html): ?array
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $document = new DOMDocument;
        $wrapped = '<?xml encoding="utf-8"><div id="editorial-root">'.$html.'</div>';

        libxml_use_internal_errors(true);
        $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $document->getElementById('editorial-root');

        if (! $root instanceof DOMElement) {
            return null;
        }

        return [$document, $root];
    }

    public function serialize(DOMDocument $document, DOMElement $root): string
    {
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * @param  (callable(DOMElement): bool)|null  $beforeElement
     */
    public function scrubNode(DOMNode $node, ?callable $beforeElement = null): void
    {
        $toRemove = [];

        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($beforeElement !== null && $beforeElement($child) === true) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $toRemove[] = $child;

                continue;
            }

            $this->scrubAttributes($child, $tag);
            $this->scrubNode($child, $beforeElement);
        }

        foreach ($toRemove as $child) {
            $node->removeChild($child);
        }
    }

    public function scrubAttributes(DOMElement $element, ?string $tag = null): void
    {
        $tag ??= strtolower($element->tagName);
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
        $toRemove = [];

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $toRemove[] = $name;

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && $this->isUnsafeUrl($value)) {
                $toRemove[] = $name;
            }
        }

        foreach ($toRemove as $name) {
            $element->removeAttribute($name);
        }
    }

    public function isUnsafeUrl(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        if (str_starts_with($value, '/') || str_starts_with($value, '#')) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return ! in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}
