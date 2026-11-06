<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\Images;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;

final class Testimonials extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.testimonials';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.testimonials';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'grid'], translations: ['en' => ['heading' => '', 'items' => []]]);
    }

    public function properties(): array
    {
        return ['variant' => P::enum(['single', 'grid']), 'heading' => P::text(translated: true, rules: ['max:255']),
            'items' => new P('list', translated: true, rules: ['max:12'], items: P::object([
                'quote' => P::text(rules: ['max:10000']), 'name' => P::text(rules: ['max:255']), 'role' => P::text(rules: ['max:255']), 'organisation' => P::text(rules: ['max:255']),
                'portrait' => new P('media', nullable: true, rules: ['max:2048']), 'sources' => Gallery::sources(), 'width' => Images::dimensions(), 'height' => Images::dimensions(), 'alt' => P::text(rules: ['max:2000']),
                'url' => new P('url', nullable: true, rules: ['max:2048']), 'page' => new P('page', nullable: true, rules: ['max:9007199254740991']),
            ]))];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        $errors = [];
        if ($publishing && is_array($values->get('items'))) {
            foreach ($values->get('items') as $i => $item) {
                if (! $item instanceof \stdClass) {
                    continue;
                }
                foreach (['quote', 'name'] as $field) {
                    if (! Gallery::filled($item->{$field} ?? null)) {
                        $errors[] = new Diagnostic('required_for_publish', '/items/'.$i.'/'.$field, 'Give every stored testimonial a quote and attribution.');
                    }
                }
            }
        }

        return $errors;
    }
}
