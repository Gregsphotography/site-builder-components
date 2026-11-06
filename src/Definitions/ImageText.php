<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\Images;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;

final class ImageText extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.image-text';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.image-text';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'image-left'], translations: ['en' => ['heading' => 'Bring your story into focus', 'body' => '<p>Pair a useful explanation with an image of your work.</p>', 'alt' => '']]);
    }

    public function properties(): array
    {
        return [
            'variant' => PropertyDefinition::enum(['image-left', 'image-right']),
            'heading' => PropertyDefinition::text(translated: true),
            'body' => PropertyDefinition::html(translated: true),
            'image' => PropertyDefinition::media(),
            'sources' => Images::sources(),
            'width' => new PropertyDefinition('number', nullable: true, rules: ['integer', 'min:1', 'max:20000']),
            'height' => new PropertyDefinition('number', nullable: true, rules: ['integer', 'min:1', 'max:20000']),
            'alt' => PropertyDefinition::text(translated: true),
            'label' => PropertyDefinition::text(translated: true),
            'url' => PropertyDefinition::url(translated: true),
            'page' => PropertyDefinition::page(translated: true),
        ];
    }
}
