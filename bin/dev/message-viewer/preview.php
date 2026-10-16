<?php

declare(strict_types=1);

namespace Backendbase\MessageViewer;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use RuntimeException;

use function htmlspecialchars;
use function in_array;
use function preg_match;
use function strtolower;
use function trim;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

function emailPreview(string $html, string $text): string
{
    if (trim($html) === '') {
        return previewDocument('<pre>' . escapeHtml($text) . '</pre>');
    }

    $document = new DOMDocument();
    if (! $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        throw new RuntimeException('Cannot render this email preview.');
    }

    return previewDocument(previewChildren($document));
}

function previewDocument(string $content): string
{
    return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Email preview</title><style>body{margin:20px;font:16px/1.5 sans-serif;color:#172c3c;overflow-wrap:anywhere}'
        . 'pre{white-space:pre-wrap;font:inherit}img{max-width:100%;height:auto}table{max-width:100%}</style>'
        . '</head><body>' . $content . '</body></html>';
}

function previewChildren(DOMNode $parent): string
{
    $html = '';
    foreach ($parent->childNodes as $child) {
        $html .= previewNode($child);
    }

    return $html;
}

function previewNode(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return escapeHtml($node->textContent);
    }

    if (! $node instanceof DOMElement) {
        return '';
    }

    $tag = strtolower($node->tagName);
    if ($tag === 'body') {
        $tag = 'div';
    }

    if (in_array($tag, ['script', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'title'], true)) {
        return '';
    }

    if (! in_array($tag, ['a', 'b', 'blockquote', 'br', 'caption', 'center', 'code', 'col', 'colgroup', 'dd', 'div', 'dl', 'dt', 'em', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 's', 'small', 'span', 'strong', 'style', 'sub', 'sup', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'u', 'ul'], true)) {
        return previewChildren($node);
    }

    $opening = '<' . $tag . previewAttributes($node) . '>';
    if (in_array($tag, ['br', 'col', 'hr', 'img'], true)) {
        return $opening;
    }

    return $opening . previewChildren($node) . '</' . $tag . '>';
}

function previewAttributes(DOMElement $node): string
{
    $attributes = '';
    foreach ($node->attributes as $attribute) {
        $attributes .= previewAttribute(strtolower($attribute->name), $attribute->value);
    }

    return $attributes;
}

function previewAttribute(string $name, string $value): string
{
    $inlineImage = $name === 'src' && preg_match('/\Adata:image\/(png|gif|jpeg|webp);base64,[a-zA-Z0-9+\/=\s]+\z/', $value) === 1;
    if (! $inlineImage && ! in_array($name, ['align', 'alt', 'bgcolor', 'border', 'cellpadding', 'cellspacing', 'class', 'color', 'colspan', 'dir', 'face', 'height', 'id', 'lang', 'rowspan', 'size', 'style', 'title', 'valign', 'width'], true)) {
        return '';
    }

    return ' ' . $name . '="' . escapeHtml($value) . '"';
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
