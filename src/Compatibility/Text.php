<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Compatibility;

use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ComponentDefinition;

final class Text implements ComponentDefinition
{
    public function type(): string
    {
        return 'demo.text';
    }

    public function version(): int
    {
        return 1;
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: [], translations: ['en' => ['heading' => 'A simple story', 'body' => '<p>Good content starts with a clear message.</p>']]);
    }

    public function properties(): array
    {
        return ['heading' => PropertyDefinition::text(translated: true), 'body' => PropertyDefinition::html(translated: true),
            'items' => PropertyDefinition::list(PropertyDefinition::object(['label' => PropertyDefinition::text(), 'url' => PropertyDefinition::url()]), translated: true)];
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::none();
    }

    public function view(): string
    {
        return 'site-builder-components::compatibility.text';
    }
}
