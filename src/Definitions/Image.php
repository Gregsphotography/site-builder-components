<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\Images;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;

final class Image extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.image';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.image';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'default', 'decorative' => false], translations: ['en' => ['alt' => '', 'caption' => '']]);
    }

    public function properties(): array
    {
        return [
            'variant' => PropertyDefinition::enum(['default', 'wide']),
            'image' => PropertyDefinition::media(),
            'sources' => Images::sources(),
            'width' => new PropertyDefinition('number', nullable: true, rules: ['integer', 'min:1', 'max:20000']),
            'height' => new PropertyDefinition('number', nullable: true, rules: ['integer', 'min:1', 'max:20000']),
            'alt' => PropertyDefinition::text(translated: true),
            'caption' => PropertyDefinition::text(translated: true),
            'decorative' => new PropertyDefinition('boolean'),
        ];
    }
}
