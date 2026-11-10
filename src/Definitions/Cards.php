<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\Icons;
use SiteBuilder\Components\Rendering\Images;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;

final class Cards extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.cards';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.cards';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'cards', 'columns' => 'three'], translations: ['en' => ['heading' => 'How we can help', 'body' => '<p>Choose a useful starting point.</p>', 'items' => [['heading' => 'A thoughtful service', 'body' => '<p>Explain the value in a short, clear paragraph.</p>', 'label' => 'Learn more', 'url' => '/services']]]]);
    }

    public function properties(): array
    {
        return [
            ...CollectionFilters::properties(),
            'variant' => PropertyDefinition::enum(['cards', 'logos']),
            'heading' => PropertyDefinition::text(translated: true),
            'body' => PropertyDefinition::html(translated: true),
            'columns' => PropertyDefinition::enum(['two', 'three', 'four']),
            'items' => PropertyDefinition::list(PropertyDefinition::object(['tags' => CollectionFilters::tags(), 'icon' => PropertyDefinition::enum(Icons::GENERAL, nullable: true), 'heading' => PropertyDefinition::text(), 'body' => PropertyDefinition::html(), 'image' => PropertyDefinition::media(), 'sources' => Images::sources(), 'width' => Images::dimensions(), 'height' => Images::dimensions(), 'alt' => PropertyDefinition::text(), 'label' => PropertyDefinition::text(), 'url' => PropertyDefinition::url(), 'page' => PropertyDefinition::page()]), translated: true),
        ];
    }
}
