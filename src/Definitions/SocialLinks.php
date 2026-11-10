<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\Icons;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\PropertyValues;

final class SocialLinks extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.social-links';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.social-links';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(translations: ['en' => ['label' => 'Keep in touch', 'items' => [['service' => 'email', 'label' => 'Email us', 'url' => 'mailto:hello@example.test']]]]);
    }

    public function properties(): array
    {
        return ['label' => P::text(translated: true), 'items' => new P('list', translated: true, rules: ['max:20'], items: P::object(['service' => P::enum([...Icons::SOCIAL, 'email', 'telephone', 'website']), 'label' => P::text(rules: ['max:255'], requiredForPublish: true), 'url' => new P('url', requiredForPublish: true)]))];
    }
}
