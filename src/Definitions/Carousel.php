<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;

final class Carousel extends LeafDefinition implements ValidatesComponentValues
{
    use QueriesContent { diagnostics as private sourceDiagnostics; }

    public function type(): string
    {
        return 'standard.carousel';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.carousel';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['content_mode' => 'manual', 'source' => 'builder.pages', 'sort' => 'newest', 'limit' => 12, 'variant' => 'cards', 'per_view' => 'three', 'autoplay' => false, 'interval' => 5, 'show_image' => true, 'show_excerpt' => true, 'show_date' => false], translations: ['en' => ['heading' => 'A few highlights', 'label' => 'Highlights', 'view_all_label' => 'View all', 'items' => [['title' => 'A thoughtful beginning', 'excerpt' => 'A clear purpose makes room for useful ideas.', 'url' => '/about'], ['title' => 'Make the next step clear', 'excerpt' => 'Give people the information they need.', 'url' => '/services'], ['title' => 'Keep the conversation going', 'excerpt' => 'Invite people to get in touch.', 'url' => '/contact']]]]);
    }

    public function properties(): array
    {
        return [...$this->queryProperties(), 'content_mode' => P::enum(['manual', 'dynamic']), 'variant' => P::enum(['images', 'logos', 'cards', 'testimonials', 'team']), 'per_view' => P::enum(['one', 'two', 'three', 'four']), 'autoplay' => new P('boolean'), 'interval' => new P('number', rules: ['integer', 'min:3', 'max:30']), 'heading' => P::text(translated: true), 'label' => P::text(translated: true, requiredForPublish: true), 'view_all_page' => P::page(), 'view_all_url' => P::url(translated: true), 'view_all_label' => P::text(translated: true), 'items' => new P('list', translated: true, rules: ['max:48'], items: P::object(['title' => P::text(rules: ['max:255']), 'subtitle' => P::text(rules: ['max:255']), 'excerpt' => P::text(rules: ['max:2000']), 'image' => P::media(), 'alt' => P::text(rules: ['max:2000']), 'url' => P::url(), 'page' => P::page()]))];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        $errors = $this->sourceDiagnostics($values, $context, $publishing);
        if ($publishing && $values->get('content_mode', 'manual') !== 'dynamic') {
            foreach ($values->get('items', []) ?? [] as $index => $item) {
                if ($item instanceof \stdClass && (! filled($item->title ?? null) || (filled($item->image ?? null) && ! filled($item->alt ?? null)))) {
                    $errors[] = new Diagnostic('required_for_publish', '/items/'.$index, 'Give each slide a title and describe its image.');
                }
            }
        }

        return $errors;
    }
}
