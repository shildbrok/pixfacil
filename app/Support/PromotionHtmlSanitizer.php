<?php

namespace App\Support;

final class PromotionHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p','br','strong','b','em','i','u','s','ul','ol','li','blockquote','code','pre','h2','h3','h4','a'
    ];

    public static function sanitize(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') return null;

        if (! class_exists(\DOMDocument::class)) {
            return self::fallback($html);
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><body><div id="pf-root">' . $html . '</div></body></html>';
        $dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $root = $xpath->query('//*[@id="pf-root"]')->item(0);
        if (! $root) return self::fallback($html);

        foreach (iterator_to_array($xpath->query('.//*', $root)) as $node) {
            if (! $node instanceof \DOMElement) continue;
            $tag = strtolower($node->tagName);

            if (in_array($tag, ['script','style','iframe','object','embed','svg','math','form','input','button','textarea'], true)) {
                $node->parentNode?->removeChild($node);
                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::unwrap($node);
                continue;
            }

            foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->name);
                if ($tag !== 'a' || ! in_array($name, ['href','target','rel'], true)) {
                    $node->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a') {
                $href = trim((string) $node->getAttribute('href'));
                if ($href !== '' && ! self::safeHref($href)) $node->removeAttribute('href');
                if ($node->getAttribute('target') === '_blank') {
                    $node->setAttribute('rel', 'noopener noreferrer nofollow');
                } else {
                    $node->removeAttribute('target');
                    $node->removeAttribute('rel');
                }
            }
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return trim($result) ?: null;
    }

    private static function safeHref(string $href): bool
    {
        if (str_starts_with($href, '/') || str_starts_with($href, '#')) return true;
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
        return in_array($scheme, ['http','https','mailto'], true);
    }

    private static function unwrap(\DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (! $parent) return;
        while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
        $parent->removeChild($node);
    }

    private static function fallback(string $html): ?string
    {
        $allowed = '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><code><pre><h2><h3><h4><a>';
        $html = strip_tags($html, $allowed);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/isu', '', $html) ?? $html;
        $html = preg_replace('/href\s*=\s*(["\'])\s*javascript:.*?\1/isu', 'href="#"', $html) ?? $html;
        $html = preg_replace('/\s+style\s*=\s*(["\']).*?\1/isu', '', $html) ?? $html;
        return trim($html) ?: null;
    }
}
