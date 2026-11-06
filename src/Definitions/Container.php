<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Content\PropertyValues;

final class Container extends LayoutDefinition
{
    public function type(): string
    {
        return 'standard.container';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.container';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues;
    }

    public function properties(): array
    {
        return [];
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::types($this->contentTypes());
    }

    public function defaultChildren(): array
    {
        return [$this->text('Content in a shared container')];
    }
}
