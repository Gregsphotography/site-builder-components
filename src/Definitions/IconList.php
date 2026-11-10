<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\Icons;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\PropertyValues;

final class IconList extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.icon-list';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.icon-list';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(translations: ['en' => ['heading' => 'Useful things to know', 'items' => [['label' => 'Thoughtful advice', 'icon' => 'check-circle'], ['label' => 'A clear next step', 'icon' => 'check-circle']]]]);
    }

    public function properties(): array
    {
        return ['heading' => P::text(translated: true), 'items' => new P('list', translated: true, rules: ['max:40'], items: P::object(['label' => P::text(rules: ['max:1000'], requiredForPublish: true), 'icon' => P::enum(Icons::GENERAL, nullable: true), 'url' => P::url(), 'page' => P::page()]))];
    }
}
