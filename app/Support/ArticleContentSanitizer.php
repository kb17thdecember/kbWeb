<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Lọc HTML do trình soạn thảo gửi lên: chỉ giữ thẻ, thuộc tính và style nằm trong danh sách cho phép.
 */
final class ArticleContentSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'font', 'hr',
    ];

    private const DROPPED_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'link', 'meta'];

    private const ALLOWED_STYLES = [
        'color', 'background-color', 'font-family', 'font-size', 'font-weight',
        'font-style', 'text-decoration', 'text-align', 'line-height',
    ];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        self::sanitizeChildren($body);

        $output = '';

        foreach ($body->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Có chữ hoặc ảnh thì bài viết mới được coi là có nội dung.
     */
    public static function hasContent(string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) !== ''
            || str_contains($html, '<img');
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType !== XML_TEXT_NODE) {
                    $parent->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED_TAGS, true)) {
                $parent->removeChild($child);

                continue;
            }

            self::sanitizeChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild !== null) {
                    $parent->insertBefore($child->firstChild, $child);
                }

                $parent->removeChild($child);

                continue;
            }

            self::sanitizeAttributes($child, $tag);

            if ($tag === 'img' && ! $child->hasAttribute('src')) {
                $parent->removeChild($child);
            }
        }
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = $attribute->value;
            $keep = match (true) {
                $name === 'style' => self::cleanStyle($value),
                $tag === 'a' && $name === 'href' => self::isSafeUrl($value, true) ? $value : null,
                $tag === 'a' && $name === 'target' => '_blank',
                $tag === 'img' && $name === 'src' => self::isSafeUrl($value, false) ? $value : null,
                $tag === 'img' && $name === 'alt' => $value,
                $tag === 'font' && in_array($name, ['color', 'face', 'size'], true) => preg_match('/^[#a-z0-9\s,\'"-]{1,60}$/i', $value) ? $value : null,
                default => null,
            };

            if ($keep === null || $keep === '') {
                $element->removeAttribute($attribute->name);

                continue;
            }

            $element->setAttribute($attribute->name, $keep);
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function cleanStyle(string $style): ?string
    {
        $clean = [];

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $value = trim($value);

            if (! in_array($property, self::ALLOWED_STYLES, true)
                || $value === ''
                || ! preg_match('/^[#a-z0-9\s,.%()\'"-]+$/i', $value)
                || preg_match('/url\s*\(|expression|javascript/i', $value)) {
                continue;
            }

            $clean[] = $property.': '.$value;
        }

        return $clean === [] ? null : implode('; ', $clean);
    }

    private static function isSafeUrl(string $url, bool $allowContactLinks): bool
    {
        $url = trim($url);

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        if (preg_match('#^https?://#i', $url)) {
            return true;
        }

        return $allowContactLinks && preg_match('/^(mailto|tel):/i', $url) === 1;
    }
}
