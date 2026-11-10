<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\PropertyValues;

final class Divider extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.divider';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.divider';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'line']);
    }

    public function properties(): array
    {
        return ['variant' => P::enum(['line', 'dots'])];
    }
}
