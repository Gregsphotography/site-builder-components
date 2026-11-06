<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Compatibility;

use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ComponentDefinition;

final class ImageText implements ComponentDefinition
{
    public function type(): string
    {
        return 'demo.image-text';
    }

    public function version(): int
    {
        return 1;
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: [], translations: ['en' => ['heading' => 'Bring it into focus', 'body' => '<p>Pair an image with a useful explanation.</p>', 'image' => '/images/landscape.svg', 'alt' => 'An illustrated green landscape']]);
    }

    public function properties(): array
    {
        return ['heading' => PropertyDefinition::text(translated: true), 'body' => PropertyDefinition::html(translated: true), 'image' => PropertyDefinition::media(translated: true), 'alt' => PropertyDefinition::text(translated: true)];
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::none();
    }

    public function view(): string
    {
        return 'site-builder-components::compatibility.image-text';
    }
}
