<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use Illuminate\Support\Str;
use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Content\PropertyValues;

final class Columns extends LayoutDefinition
{
    public function type(): string
    {
        return 'standard.columns';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.columns';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'equal']);
    }

    public function properties(): array
    {
        return ['variant' => PropertyDefinition::enum(['equal', 'sidebar-start', 'sidebar-end'])];
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::types(['standard.column'], 1, 4);
    }

    public function defaultChildren(): array
    {
        return array_map(fn (string $heading): Node => new Node((string) Str::uuid(), 'standard.column', children: [$this->text($heading)]), ['First column', 'Second column']);
    }
}
