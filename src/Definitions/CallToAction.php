<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;

final class CallToAction extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.cta';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.cta';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'brand'], translations: ['en' => ['heading' => 'Let’s talk about your next step', 'body' => '<p>Start a conversation with our team.</p>', 'label' => 'Get in touch', 'url' => '/contact']]);
    }

    public function properties(): array
    {
        return [
            'variant' => PropertyDefinition::enum(['brand', 'neutral']),
            'heading' => PropertyDefinition::text(translated: true),
            'body' => PropertyDefinition::html(translated: true),
            'label' => PropertyDefinition::text(translated: true),
            'url' => PropertyDefinition::url(translated: true),
            'page' => PropertyDefinition::page(translated: true),
        ];
    }
}
