<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Contracts\ComponentDefinition;

abstract class LeafDefinition implements ComponentDefinition
{
    public function version(): int
    {
        return 1;
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::none();
    }
}
