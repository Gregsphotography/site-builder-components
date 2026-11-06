<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\Images;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;

final class Hero extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.hero';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.hero';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'centred'], translations: ['en' => ['heading' => 'A clear introduction', 'body' => '<p>Tell people who you help and what they can do next.</p>', 'label' => 'Learn more', 'url' => '/about']]);
    }

    public function properties(): array
    {
        return [
            'variant' => PropertyDefinition::enum(['centred', 'split', 'background']),
            'eyebrow' => PropertyDefinition::text(translated: true),
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
            'secondary_label' => PropertyDefinition::text(translated: true),
            'secondary_url' => PropertyDefinition::url(translated: true),
            'secondary_page' => PropertyDefinition::page(translated: true),
        ];
    }
}
