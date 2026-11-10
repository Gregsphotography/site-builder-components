<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Components\PlacementPolicy;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\PropertyValues;

final class TabPanel extends LayoutDefinition
{
    public function type(): string
    {
        return 'standard.tab-panel';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.tab-panel';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(translations: ['en' => ['title' => 'Panel']]);
    }

    public function properties(): array
    {
        return ['title' => P::text(translated: true, rules: ['max:255'], requiredForPublish: true)];
    }

    public function placement(): PlacementPolicy
    {
        return PlacementPolicy::childOnly(structural: true);
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::types($this->contentTypes());
    }

    public function defaultChildren(): array
    {
        return [$this->text('')];
    }
}
