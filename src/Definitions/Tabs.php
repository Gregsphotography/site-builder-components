<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use Illuminate\Support\Str;
use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Content\PropertyValues;

final class Tabs extends LayoutDefinition
{
    public function type(): string
    {
        return 'standard.tabs';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.tabs';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(translations: ['en' => ['label' => 'Useful information']]);
    }

    public function properties(): array
    {
        return ['label' => P::text(translated: true, rules: ['max:255'], requiredForPublish: true)];
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::types(['standard.tab-panel'], 1, 8);
    }

    public function defaultChildren(): array
    {
        return array_map(fn ($title) => new Node((string) Str::uuid(), 'standard.tab-panel', translations: new JsonObject(['en' => (object) ['title' => $title]]), children: [$this->text('')]), ['Overview', 'Details']);
    }
}
