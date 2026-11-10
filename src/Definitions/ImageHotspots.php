<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\PropertyValues;

final class ImageHotspots extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.image-hotspots';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.image-hotspots';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(translations: ['en' => ['heading' => 'Explore the image', 'label' => 'Image details', 'items' => [['x' => 5000, 'y' => 5000, 'title' => 'A point of interest', 'body' => '<p>Add useful context for this part of the image.</p>']]]]);
    }

    public function properties(): array
    {
        return ['image' => new P('media', requiredForPublish: true), 'heading' => P::text(translated: true), 'label' => P::text(translated: true, requiredForPublish: true), 'alt' => P::text(translated: true, requiredForPublish: true), 'items' => new P('list', translated: true, rules: ['max:24'], items: P::object(['x' => new P('number', rules: ['integer', 'min:0', 'max:10000'], requiredForPublish: true), 'y' => new P('number', rules: ['integer', 'min:0', 'max:10000'], requiredForPublish: true), 'title' => P::text(rules: ['max:255'], requiredForPublish: true), 'body' => P::html(), 'url' => P::url(), 'page' => P::page(), 'link_label' => P::text(rules: ['max:255'])]))];
    }
}
