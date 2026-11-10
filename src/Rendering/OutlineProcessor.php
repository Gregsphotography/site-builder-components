<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\HtmlString;
use SiteBuilder\Core\Contracts\RenderedDocumentProcessor;
use SiteBuilder\Core\Rendering\RenderContext;
use SiteBuilder\Core\Rendering\RenderResult;

final class OutlineProcessor implements RenderedDocumentProcessor
{
    public function process(RenderResult $result, RenderContext $context): RenderResult
    {
        $html = $result->html->toHtml();
        if (! str_contains($html, 'data-sb-outline') && ! str_contains($html, 'sb-heading')) {
            return $result;
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8"><div data-sb-outline-root>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
            $xpath = new DOMXPath($dom);
            $root = $xpath->query('//*[@data-sb-outline-root]')->item(0);
            $counts = $headings = [];
            foreach ($xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $root) as $heading) {
                if ($heading->hasAttribute('data-sb-panel-title')) {
                    continue;
                }
                $owner = $heading;
                while ($owner instanceof DOMElement && ! $owner->hasAttribute('data-site-builder-node') && ! $owner->hasAttribute('data-site-builder-render-address')) {
                    $owner = $owner->parentNode;
                }
                $address = $owner instanceof DOMElement ? ($owner->getAttribute('data-site-builder-render-address') ?: $owner->getAttribute('data-site-builder-node')) : 'body';
                $index = $counts[$address] ?? 0;
                $counts[$address] = $index + 1;
                if (! $heading->hasAttribute('id')) {
                    $heading->setAttribute('id', 'sb-heading-'.substr(hash('sha256', $address.':'.$index), 0, 20));
                }
                $headings[] = $heading;
            }
            foreach ($xpath->query('.//*[@data-sb-outline]', $root) as $toc) {
                $list = $dom->createElement('ol');
                foreach ($headings as $heading) {
                    if (! in_array($heading->tagName, $toc->getAttribute('data-include-h4') === 'true' ? ['h2', 'h3', 'h4'] : ['h2', 'h3'], true) || trim($heading->textContent) === '') {
                        continue;
                    }
                    $li = $dom->createElement('li');
                    $li->setAttribute('data-level', $heading->tagName);
                    $link = $dom->createElement('a');
                    $link->setAttribute('href', '#'.$heading->getAttribute('id'));
                    $link->appendChild($dom->createTextNode(trim($heading->textContent)));
                    $li->appendChild($link);
                    $list->appendChild($li);
                }
                $toc->appendChild($list);
            }
            $output = '';
            foreach ($root->childNodes as $child) {
                $output .= $dom->saveHTML($child);
            }

            return new RenderResult(new HtmlString($output), $result->diagnostics, $result->structuredData, $result->headerSlot, $result->footerSlot);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
