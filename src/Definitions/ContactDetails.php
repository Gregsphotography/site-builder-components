<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;

final class ContactDetails extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.contact-details';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.contact-details';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'default'], translations: ['en' => ['heading' => 'Get in touch', 'body' => '<p>Choose the way that works best for you.</p>', 'items' => [['label' => 'Email', 'value' => 'hello@example.com', 'url' => 'mailto:hello@example.com'], ['label' => 'Telephone', 'value' => '+41 00 000 00 00', 'url' => 'tel:+41000000000']]]]);
    }

    public function properties(): array
    {
        return [
            'variant' => PropertyDefinition::enum(['default']),
            'heading' => PropertyDefinition::text(translated: true),
            'body' => PropertyDefinition::html(translated: true),
            'items' => PropertyDefinition::list(PropertyDefinition::object(['label' => PropertyDefinition::text(), 'value' => PropertyDefinition::text(), 'url' => PropertyDefinition::url(), 'page' => PropertyDefinition::page()]), translated: true),
        ];
    }
}
