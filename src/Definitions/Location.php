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

final class Location extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.location';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.location';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['embed_url' => null], translations: ['en' => ['heading' => 'Visit us', 'address' => 'Zürich, Switzerland', 'directions_label' => 'Get directions', 'load_label' => 'Load Google Maps', 'directions_url' => null]]);
    }

    public function properties(): array
    {
        return ['embed_url' => new P('url', nullable: true, rules: ['max:12000']), 'heading' => P::text(translated: true), 'address' => P::text(translated: true, requiredForPublish: true), 'directions_url' => P::url(translated: true), 'directions_label' => P::text(translated: true), 'load_label' => P::text(translated: true)];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        return filled($values->get('embed_url')) && app(EmbedUrls::class)->map($values->get('embed_url')) === null ? [new Diagnostic('invalid_map_url', '/embed_url', 'Use the HTTPS Google Maps /maps/embed URL from its Share → Embed a map option.')] : [];
    }
}
