<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;

final class SearchResults extends LeafDefinition implements ValidatesComponentValues
{
    use QueriesContent;

    public function type(): string
    {
        return 'standard.search-results';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.content-listing';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['source' => '*', 'variant' => 'list', 'sort' => 'newest', 'limit' => 12, 'visitor_filters' => true, 'show_image' => true, 'show_excerpt' => true, 'show_date' => false], translations: ['en' => ['heading' => 'Search results', 'label' => 'Search the site', 'empty_message' => 'No results. Try a different search.']]);
    }

    public function properties(): array
    {
        return (new ContentListing)->properties();
    }
}
