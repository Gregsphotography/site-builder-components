<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;

final class Text extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.text';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.text';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'default'], translations: ['en' => ['heading' => 'A useful story', 'body' => '<p>Share the details that help your visitors make a decision.</p>']]);
    }

    public function properties(): array
    {
        return [
            'variant' => PropertyDefinition::enum(['default', 'narrow']),
            'heading' => PropertyDefinition::text(translated: true),
            'body' => PropertyDefinition::html(translated: true),
            'items' => PropertyDefinition::list(PropertyDefinition::object(['label' => PropertyDefinition::text(), 'url' => PropertyDefinition::url(), 'page' => PropertyDefinition::page()]), translated: true),
        ];
    }
}
