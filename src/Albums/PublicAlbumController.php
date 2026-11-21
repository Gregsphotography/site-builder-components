<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Albums;

use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;
use SiteBuilder\Core\Contracts\MediaResolver;
use SiteBuilder\Core\Rendering\LocaleCatalog;
use SiteBuilder\Core\Rendering\PublicAssets;
use SiteBuilder\Core\Rendering\RenderContext;
use SiteBuilder\Core\Rendering\SafeUrl;

final class PublicAlbumController
{
    public function __invoke(string $slug, AlbumLibrary $albums, PublicAssets $assets, LocaleCatalog $locales, MediaResolver $media, SafeUrl $urls): Response
    {
        $snapshot = $albums->publishedBySlug($slug);
        abort_if($snapshot === null, 404);

        $locale = $locales->default();
        $previousLocale = app()->getLocale();
        app()->setLocale($locale);
        try {
            return $assets->scope(function () use ($snapshot, $locale, $media, $urls): Response {
                $context = new RenderContext($locale);
                $title = $snapshot['title'];
                $canonical = route('site-builder.album', ['slug' => $snapshot['slug']]);
                $cover = $snapshot['cover'] === null ? null : $media->url($snapshot['cover']);
                $cover = filled($cover) && $urls->valid($cover, true) ? $cover : null;
                $seo = [
                    'title' => $title,
                    'canonical' => $canonical,
                    'image' => $cover === null ? null : url($cover),
                    'alternates' => [],
                    'graph' => ['@context' => 'https://schema.org', '@type' => 'ImageGallery', 'name' => $title, 'url' => $canonical],
                ];
                $slot = new HtmlString(view('site-builder-components::album', compact('snapshot', 'context', 'cover'))->render());

                return response()->view('site-builder-components::layouts.site', compact('locale', 'title', 'seo', 'slot'), 200, ['Cache-Control' => 'public, max-age=0, must-revalidate']);
            });
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
