<?php

namespace App\Support\Editorial;

use App\Support\Html\EditorialHtml;
use DOMElement;

final class EditorialContent
{
    public function __construct(
        private readonly EditorialHtml $html,
        private readonly TemplateBlockRegistry $registry,
    ) {}

    /**
     * Persistable representation: free-form HTML sanitized; recognized
     * Template Block wrappers preserved and inner HTML sanitized.
     */
    public function normalize(?string $html): string
    {
        return $this->process($html);
    }

    /**
     * Public/admin output. Same contract as normalize so legacy unsaved rows
     * cannot execute markup the editor has not resaved.
     */
    public function render(?string $html): string
    {
        return $this->process($html);
    }

    private function process(?string $html): string
    {
        $parsed = $this->html->parse($html);

        if ($parsed === null) {
            return '';
        }

        [$document, $root] = $parsed;
        $this->html->scrubNode($root, fn (DOMElement $element): bool => $this->handleWrapper($element));

        return $this->html->serialize($document, $root);
    }

    private function handleWrapper(DOMElement $element): bool
    {
        if (! $this->isWrapper($element)) {
            return false;
        }

        $type = $this->registry->normalizeType((string) $element->getAttribute('data-block-type'));

        if ($type === null || ! $this->registry->allows($type)) {
            $this->stripWrapperIdentity($element);

            return false;
        }

        $this->lockWrapper($element, $type);
        $content = $this->contentContainer($element);
        $this->lockContentContainer($content);
        $this->html->scrubNode($content, fn (DOMElement $child): bool => $this->handleWrapper($child));

        return true;
    }

    private function isWrapper(DOMElement $element): bool
    {
        if (strtolower($element->tagName) !== 'div') {
            return false;
        }

        $class = ' '.$element->getAttribute('class').' ';

        return str_contains($class, ' tiptap-block-wrapper ');
    }

    private function stripWrapperIdentity(DOMElement $element): void
    {
        $element->removeAttribute('data-block-type');

        $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
        $classes = array_values(array_filter(
            $classes,
            fn (string $class): bool => $class !== '' && $class !== 'tiptap-block-wrapper',
        ));

        if ($classes === []) {
            $element->removeAttribute('class');
        } else {
            $element->setAttribute('class', implode(' ', $classes));
        }
    }

    private function lockWrapper(DOMElement $element, string $type): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $element->removeAttribute($attribute->name);
        }

        $element->setAttribute('class', 'tiptap-block-wrapper');
        $element->setAttribute('data-block-type', $type);
    }

    private function contentContainer(DOMElement $wrapper): DOMElement
    {
        foreach ($wrapper->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if (strtolower($child->tagName) !== 'div') {
                continue;
            }

            $class = ' '.$child->getAttribute('class').' ';
            if (str_contains($class, ' tiptap-block-content ')) {
                return $child;
            }
        }

        $document = $wrapper->ownerDocument;
        $content = $document->createElement('div');
        $content->setAttribute('class', 'tiptap-block-content');

        foreach (iterator_to_array($wrapper->childNodes) as $child) {
            $content->appendChild($child);
        }

        $wrapper->appendChild($content);

        return $content;
    }

    private function lockContentContainer(DOMElement $content): void
    {
        foreach (iterator_to_array($content->attributes ?? []) as $attribute) {
            $content->removeAttribute($attribute->name);
        }

        $content->setAttribute('class', 'tiptap-block-content');
    }
}
