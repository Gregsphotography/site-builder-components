<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;

final class Section extends LayoutDefinition
{
    public function type(): string
    {
        return 'standard.section';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.section';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['heading_level' => 'h2']);
    }

    public function properties(): array
    {
        return ['heading' => PropertyDefinition::text(translated: true), 'heading_level' => PropertyDefinition::enum(['h2', 'h3'])];
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::types($this->contentTypes());
    }

    public function defaultChildren(): array
    {
        return [$this->text('A useful section')];
    }
}
