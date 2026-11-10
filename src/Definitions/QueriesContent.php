<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Discovery\ContentIndex;
use SiteBuilder\Core\Discovery\ContentSources;
use SiteBuilder\Core\Rendering\RenderContext;

trait QueriesContent
{
    private function queryProperties(): array
    {
        return ['source' => P::text(rules: ['max:80']), 'content_type' => P::text(rules: ['max:255']), 'content_tag' => P::text(rules: ['max:80']), 'sort' => P::enum(['newest', 'oldest', 'title']), 'limit' => new P('number', rules: ['integer', 'min:1', 'max:48']), 'show_image' => new P('boolean'), 'show_excerpt' => new P('boolean'), 'show_date' => new P('boolean')];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        if (! $publishing || (in_array($this->type(), ['standard.carousel', 'standard.timeline'], true) && $values->get('content_mode', 'manual') !== 'dynamic')) {
            return [];
        }
        $source = $values->get('source') ?? 'builder.pages';
        if (($source !== '*' && app(ContentSources::class)->get($source) === null) || ! app(ContentIndex::class)->available()) {
            return [new Diagnostic('content_source_unavailable', '/source', 'Install discovery storage and choose a registered public content source before publishing.')];
        }

        return [];
    }
}
