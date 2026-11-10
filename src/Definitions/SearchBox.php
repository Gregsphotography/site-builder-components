<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\SearchDestination;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;

final class SearchBox extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.search-box';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.search-box';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['live' => true], translations: ['en' => ['label' => 'Search the site', 'placeholder' => 'What are you looking for?', 'button_label' => 'Search']]);
    }

    public function properties(): array
    {
        return ['results_page' => P::page(), 'live' => new P('boolean'), 'label' => P::text(translated: true, requiredForPublish: true), 'placeholder' => P::text(translated: true, rules: ['max:255']), 'button_label' => P::text(translated: true, requiredForPublish: true)];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        if (! $publishing) {
            return [];
        }
        $id = $values->get('results_page') ?? config('site-builder.discovery.results_page');

        return app(SearchDestination::class)->resolve($id, $context->locale) !== null ? [] : [new Diagnostic('search_page_unavailable', '/results_page', 'Choose a published page containing search results and a URL in this locale.')];
    }
}
