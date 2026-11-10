<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\Icons;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;

final class Buttons extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.buttons';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.buttons';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(translations: ['en' => ['label' => 'Useful next steps', 'items' => [['label' => 'Get in touch', 'url' => '/contact', 'variant' => 'primary', 'icon' => 'arrow-right']]]]);
    }

    public function properties(): array
    {
        return ['label' => P::text(translated: true), 'items' => new P('list', translated: true, rules: ['min:1', 'max:4'], requiredForPublish: true, items: P::object(['label' => P::text(rules: ['max:255'], requiredForPublish: true), 'url' => P::url(), 'page' => P::page(), 'variant' => P::enum(['primary', 'secondary'], nullable: true), 'icon' => P::enum(Icons::GENERAL, nullable: true)]))];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        $errors = [];
        foreach ($publishing ? ($values->get('items', []) ?? []) : [] as $index => $item) {
            if ($item instanceof \stdClass && ($item->page ?? null) === null && ! filled($item->url ?? null)) {
                $errors[] = new Diagnostic('required_for_publish', '/items/'.$index, 'Choose a page or URL for this button.');
            }
        }

        return $errors;
    }
}
