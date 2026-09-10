<?php

namespace App\Services\Security;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class HtmlSanitizer
{
    /**
     * Whitelist of allowed HTML tags for rich product descriptions & metafields.
     */
    protected static array $allowedTags = [
        'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del',
        'blockquote', 'pre', 'code',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
        'span', 'div', 'a', 'img', 'sub', 'sup', 'small', 'big', 'abbr', 'address', 'center'
    ];

    /**
     * Tags whose entire subtree (including inner text/elements) must be removed.
     */
    protected static array $removeWithContent = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet',
        'meta', 'link', 'svg', 'math', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'base', 'template', 'noscript',
        'frame', 'frameset', 'canvas', 'video', 'audio', 'source', 'track'
    ];

    /**
     * Allowed attributes per tag (lowercase).
     */
    protected static array $allowedAttributes = [
        '*' => ['class', 'id', 'title', 'dir', 'lang', 'align'],
        'a' => ['href', 'target', 'rel', 'title', 'download', 'name'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading', 'align'],
        'table' => ['border', 'cellpadding', 'cellspacing', 'width', 'height', 'align', 'summary'],
        'th' => ['colspan', 'rowspan', 'scope', 'align', 'valign', 'width', 'height'],
        'td' => ['colspan', 'rowspan', 'align', 'valign', 'width', 'height'],
        'col' => ['span', 'width'],
        'colgroup' => ['span', 'width'],
        'ol' => ['type', 'start', 'reversed'],
        'li' => ['value'],
    ];

    /**
     * Allowed URL schemes.
     */
    protected static array $allowedProtocols = ['http', 'https', 'mailto', 'tel'];

    /**
     * Sanitize an HTML string to prevent XSS while retaining valid formatting.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Fast path for plain text without HTML tags
        if (!str_contains($html, '<') && !str_contains($html, '>')) {
            return $html;
        }

        $dom = new DOMDocument();
        // Suppress warnings from malformed HTML fragments
        $previousLibxmlUseErrors = libxml_use_internal_errors(true);

        // Prepend UTF-8 encoding hint to ensure multibyte characters are handled properly
        $encodedHtml = '<?xml encoding="UTF-8"><div>' . $html . '</div>';
        $dom->loadHTML($encodedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlUseErrors);

        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root) {
            return '';
        }

        self::sanitizeNode($root);

        // Extract inner HTML of the wrapper div
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }

    /**
     * Recursively sanitize DOM nodes.
     */
    protected static function sanitizeNode(DOMNode $node): void
    {
        $childNodes = [];
        foreach ($node->childNodes as $child) {
            $childNodes[] = $child;
        }

        foreach ($childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                /** @var DOMElement $child */
                $tagName = strtolower($child->tagName);

                // 1. If tag must be removed with all child content (script, iframe, style, etc.)
                if (in_array($tagName, self::$removeWithContent, true)) {
                    $node->removeChild($child);
                    continue;
                }

                // 2. If tag is not in allowed list, unwrap it (keep child nodes, remove tag itself)
                if (!in_array($tagName, self::$allowedTags, true)) {
                    self::sanitizeNode($child);
                    while ($child->hasChildNodes()) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }

                // 3. Sanitize attributes of allowed tag
                self::sanitizeAttributes($child, $tagName);

                // 4. Recurse into children
                self::sanitizeNode($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                // Strip HTML comments to prevent conditional IE comments or parser confusion
                $node->removeChild($child);
            }
        }
    }

    /**
     * Sanitize attributes on an allowed DOMElement.
     */
    protected static function sanitizeAttributes(DOMElement $element, string $tagName): void
    {
        if (!$element->hasAttributes()) {
            return;
        }

        $attributesToRemove = [];
        $allowedForTag = array_merge(
            self::$allowedAttributes['*'] ?? [],
            self::$allowedAttributes[$tagName] ?? []
        );

        foreach ($element->attributes as $attr) {
            $attrName = strtolower($attr->name);
            $attrValue = $attr->value;

            // Block any event handler attribute (starts with "on", e.g. onerror, onclick)
            if (str_starts_with($attrName, 'on')) {
                $attributesToRemove[] = $attr->name;
                continue;
            }

            // Check attribute whitelist
            if (!in_array($attrName, $allowedForTag, true)) {
                $attributesToRemove[] = $attr->name;
                continue;
            }

            // Sanitize URL attributes (href, src)
            if (in_array($attrName, ['href', 'src'], true)) {
                if (!self::isSafeUrl($attrValue)) {
                    $attributesToRemove[] = $attr->name;
                    continue;
                }

                // Enforce rel="noopener noreferrer" on target="_blank"
                if ($tagName === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
                    $element->setAttribute('rel', 'noopener noreferrer');
                }
            }
        }

        foreach ($attributesToRemove as $attrName) {
            $element->removeAttribute($attrName);
        }
    }

    /**
     * Determine whether a URL is safe.
     */
    public static function isSafeUrl(?string $url): bool
    {
        if ($url === null) {
            return false;
        }

        $trimmed = trim($url);
        if ($trimmed === '') {
            return false;
        }

        // Relative URLs and anchor links are safe
        if (str_starts_with($trimmed, '/') || str_starts_with($trimmed, './') || str_starts_with($trimmed, '../') || str_starts_with($trimmed, '#')) {
            return true;
        }

        // Check scheme
        $colonPos = strpos($trimmed, ':');
        if ($colonPos !== false) {
            $scheme = strtolower(substr($trimmed, 0, $colonPos));
            // Disallow any control characters in scheme
            $scheme = preg_replace('/[^a-z0-9+-.]/', '', $scheme);

            return in_array($scheme, self::$allowedProtocols, true);
        }

        // URLs without a scheme (e.g. domain.com/path or relative)
        return true;
    }
}
