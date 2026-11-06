<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Compatibility;

use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ComponentDefinition;

final class Hero implements ComponentDefinition
{
    public function type(): string
    {
        return 'demo.hero';
    }

    public function version(): int
    {
        return 1;
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['tone' => 'brand'], translations: ['en' => ['heading' => 'Make room for your next idea', 'body' => '<p>A clear starting point for a thoughtful website.</p>']]);
    }

    public function properties(): array
    {
        return ['heading' => PropertyDefinition::text(translated: true, rules: ['string', 'max:255']), 'body' => PropertyDefinition::html(translated: true), 'tone' => PropertyDefinition::enum(['brand', 'neutral'])];
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::none();
    }

    public function view(): string
    {
        return 'site-builder-components::compatibility.hero';
    }
}
