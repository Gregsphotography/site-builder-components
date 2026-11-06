<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Compatibility;

use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ComponentDefinition;

final class CallToAction implements ComponentDefinition
{
    public function type(): string
    {
        return 'demo.cta';
    }

    public function version(): int
    {
        return 1;
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: [], translations: ['en' => ['heading' => 'What comes next?', 'label' => 'Explore the library', 'url' => '/admin/component-library']]);
    }

    public function properties(): array
    {
        return ['heading' => PropertyDefinition::text(translated: true), 'label' => PropertyDefinition::text(translated: true), 'url' => PropertyDefinition::url(translated: true)];
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::none();
    }

    public function view(): string
    {
        return 'site-builder-components::compatibility.cta';
    }
}
