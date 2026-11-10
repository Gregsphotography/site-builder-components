<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

use Illuminate\Validation\ValidationException;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Rendering\ContentOccurrences;
use SiteBuilder\Core\Rendering\RenderContext;
use SiteBuilder\Core\Routing\PageUrlResolver;

final class SearchDestination
{
    public function resolve(mixed $id, string $locale): ?array
    {
        $page = is_numeric($id) ? Page::query()->where('status', 'published')->with('slugs')->find($id) : null;
        $url = $page === null ? null : app(PageUrlResolver::class)->path($page, $locale);
        if ($url === null || $page->published_content === null) {
            return null;
        }
        try {
            foreach (app(ContentOccurrences::class)->visible($page->published_content, new RenderContext($locale, pageId: $page->id, layout: $page->layout)) as $occurrence) {
                if ($occurrence['node']->type === 'standard.search-results') {
                    $props = $occurrence['props'];

                    return ['url' => $url, 'source' => $props['source'] ?: '*', 'type' => $props['content_type'] ?? '', 'tag' => $props['content_tag'] ?? ''];
                }
            }
        } catch (ValidationException|\InvalidArgumentException) {
            return null;
        }

        return null;
    }
}
