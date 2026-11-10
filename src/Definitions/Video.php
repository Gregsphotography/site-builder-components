<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\EmbedUrls;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;
use SiteBuilder\Core\Rendering\SafeUrl;

final class Video extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.video';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.video';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['provider' => 'url', 'aspect' => 'landscape'], translations: ['en' => ['title' => 'A closer look — sample clip', 'media' => null, 'url' => '/vendor/site-builder-components/examples/intro.mp4', 'poster' => null, 'transcript' => '<p>A silent two-second sample fades into and out of a teal background.</p>', 'tracks' => []]]);
    }

    public function properties(): array
    {
        return ['provider' => P::enum(['uploaded', 'url', 'youtube', 'vimeo']), 'aspect' => P::enum(['landscape', 'portrait', 'square']), 'title' => P::text(translated: true, requiredForPublish: true), 'media' => P::media(translated: true, mediaKind: 'video'), 'url' => P::url(translated: true), 'poster' => P::media(translated: true), 'transcript' => P::html(translated: true), 'tracks' => new P('list', translated: true, rules: ['max:12'], items: P::object(['label' => P::text(requiredForPublish: true), 'language' => P::text(rules: ['regex:/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})*$/D'], requiredForPublish: true), 'track' => P::media(mediaKind: 'track'), 'url' => P::url(), 'default' => new P('boolean', nullable: true)]))];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        if (! $publishing) {
            return [];
        }
        $provider = $values->get('provider');
        $url = $values->get('url');
        $errors = [];
        if ($provider === 'uploaded' && ! filled($values->get('media'))) {
            $errors[] = new Diagnostic('required_for_publish', '/media', 'Choose an uploaded video.');
        } elseif ($provider === 'url' && (! is_string($url) || ! app(SafeUrl::class)->valid($url, true))) {
            $errors[] = new Diagnostic('required_for_publish', '/url', 'Choose a safe video file URL.');
        } elseif (in_array($provider, ['youtube', 'vimeo'], true) && app(EmbedUrls::class)->video($provider, $url) === null) {
            $errors[] = new Diagnostic('invalid_video_url', '/url', 'Choose a supported HTTPS '.$provider.' video URL.');
        }
        foreach ($values->get('tracks', []) ?? [] as $i => $track) {
            if ($track instanceof \stdClass && ! filled($track->track ?? null) && ! filled($track->url ?? null)) {
                $errors[] = new Diagnostic('required_for_publish', '/tracks/'.$i, 'Choose a caption file or URL.');
            }
        }

        return $errors;
    }
}
