<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\PropertyValues;

final class Breadcrumbs extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.breadcrumbs';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.breadcrumbs';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(translations: ['en' => ['label' => 'Breadcrumbs']]);
    }

    public function properties(): array
    {
        return ['label' => P::text(translated: true)];
    }
}
