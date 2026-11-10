<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

use SiteBuilder\Core\Discovery\ContentIndex;
use SiteBuilder\Core\Discovery\ContentQuery;
use SiteBuilder\Core\Discovery\ContentResults;
use SiteBuilder\Core\Rendering\PublicAssets;
use SiteBuilder\Core\Rendering\RenderContext;

final class ContentLists
{
    public function key(string $address): string
    {
        return 'sb_'.substr(hash('sha256', $address), 0, 16).'_';
    }

    public function results(array $props, RenderContext $context, string $prefix, bool $search = false): ContentResults
    {
        app(PublicAssets::class)->requirePrivateResponse();
        $state = $context->mode === 'public' ? $context->query : null;
        $filters = $search || ($props['visitor_filters'] ?? false);
        $q = $filters ? ($state?->get($prefix.'q') ?? '') : '';
        if ($search && trim($q) === '') {
            return new ContentResults;
        }

        return app(ContentIndex::class)->query(new ContentQuery(source: $search && ($props['source'] ?? '*') === '*' ? (($state?->get('source') ?? '') ?: '*') : ($props['source'] ?? 'builder.pages'), text: $q, type: mb_substr(($props['content_type'] ?? '') ?: ($filters ? ($state?->get($prefix.'type') ?? '') : ''), 0, 255), tag: mb_substr(($props['content_tag'] ?? '') ?: ($filters ? ($state?->get($prefix.'tag') ?? '') : ''), 0, 80), sort: $search ? 'relevance' : ($props['sort'] ?? 'newest'), page: (int) ($state?->get($prefix.'page', '1') ?? 1), perPage: $props['limit'] ?? 12, search: $search), $context->locale);
    }

    public function pageUrl(RenderContext $context, string $prefix, int $page): string
    {
        $values = $context->query?->all() ?? [];
        $values[$prefix.'page'] = (string) $page;

        return '?'.http_build_query($values, encoding_type: PHP_QUERY_RFC3986);
    }
}
