<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\PropertyValues;

final class TableOfContents extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.table-of-contents';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.table-of-contents';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['include_h4' => false], translations: ['en' => ['heading' => 'On this page']]);
    }

    public function properties(): array
    {
        return ['include_h4' => new P('boolean'), 'heading' => P::text(translated: true, requiredForPublish: true)];
    }
}
