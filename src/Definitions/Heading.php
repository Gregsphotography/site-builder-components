<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\PropertyValues;

final class Heading extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.heading';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.heading';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['level' => 'h2'], translations: ['en' => ['text' => 'A clear next step', 'highlight' => '', 'after' => '']]);
    }

    public function properties(): array
    {
        return ['level' => P::enum(['h1', 'h2', 'h3', 'h4', 'h5', 'h6']), 'text' => P::text(translated: true, rules: ['max:1000'], requiredForPublish: true), 'highlight' => P::text(translated: true, rules: ['max:500']), 'after' => P::text(translated: true, rules: ['max:1000'])];
    }
}
