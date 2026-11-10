<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\PropertyValues;

final class Anchor extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.anchor';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.anchor';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['name' => 'section']);
    }

    public function properties(): array
    {
        return ['name' => P::text(rules: ['max:100', 'regex:/^[a-z][a-z0-9_-]*$/D'], requiredForPublish: true)];
    }
}
