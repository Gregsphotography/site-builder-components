<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Rendering\RenderContext;
use SiteBuilder\Core\Rendering\SafeUrl;
use SiteBuilder\Core\Routing\PageUrlResolver;

final class ContentLinks
{
    public function __construct(private PageUrlResolver $pages, private SafeUrl $safe) {}

    public function url(?int $page, ?string $url, string $locale): ?string
    {
        if ($page !== null) {
            $target = Page::query()->where('status', 'published')->find($page);

            return $target === null ? null : $this->pages->url($target, $locale);
        }

        return is_string($url) && $url !== '' && $this->safe->valid($url) ? $url : null;
    }

    public function current(?int $page, string $url, RenderContext $context): bool
    {
        if ($context->pageId === null) {
            return false;
        }
        if ($page !== null) {
            return $page === $context->pageId;
        }
        $current = Page::query()->where('status', 'published')->find($context->pageId);
        $canonical = $current === null ? null : $this->pages->url($current, $context->locale);
        if ($canonical === null) {
            return false;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $url = rtrim((string) config('app.url'), '/').$url;
        }

        return rtrim($canonical, '/') === rtrim($url, '/');
    }
}
