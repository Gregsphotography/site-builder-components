<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;

final class ContentListing extends LeafDefinition implements ValidatesComponentValues
{
    use QueriesContent;

    public function type(): string
    {
        return 'standard.content-listing';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.content-listing';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['source' => 'builder.pages', 'variant' => 'cards', 'sort' => 'newest', 'limit' => 12, 'visitor_filters' => true, 'show_image' => true, 'show_excerpt' => true, 'show_date' => false], translations: ['en' => ['heading' => 'Explore our latest content', 'label' => 'Content listing', 'empty_message' => 'No matching content. Try another filter.']]);
    }

    public function properties(): array
    {
        return [...$this->queryProperties(), 'variant' => P::enum(['cards', 'images', 'list']), 'visitor_filters' => new P('boolean'), 'heading' => P::text(translated: true, rules: ['max:255']), 'label' => P::text(translated: true, requiredForPublish: true), 'empty_message' => P::text(translated: true, rules: ['max:1000'])];
    }
}
