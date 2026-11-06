<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Components\PlacementPolicy;
use SiteBuilder\Core\Content\PropertyValues;

final class Column extends LayoutDefinition
{
    public function type(): string
    {
        return 'standard.column';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.column';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues;
    }

    public function properties(): array
    {
        return [];
    }

    public function placement(): PlacementPolicy
    {
        return PlacementPolicy::childOnly(structural: true);
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::types($this->contentTypes(columns: false));
    }

    public function defaultChildren(): array
    {
        return [$this->text('Column content')];
    }
}
