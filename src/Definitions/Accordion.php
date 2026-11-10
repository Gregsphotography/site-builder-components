<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use Illuminate\Support\Str;
use SiteBuilder\Core\Components\ChildPolicy;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Content\PropertyValues;

final class Accordion extends LayoutDefinition
{
    public function type(): string
    {
        return 'standard.accordion';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.accordion';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['multiple' => true], translations: ['en' => ['heading' => 'A few useful details']]);
    }

    public function properties(): array
    {
        return ['multiple' => new P('boolean'), 'heading' => P::text(translated: true)];
    }

    public function allowedChildren(): ChildPolicy
    {
        return ChildPolicy::types(['standard.accordion-item'], 1, 8);
    }

    public function defaultChildren(): array
    {
        return array_map(fn ($title) => new Node((string) Str::uuid(), 'standard.accordion-item', translations: new JsonObject(['en' => (object) ['title' => $title]]), children: [$this->text('')]), ['How we work', 'What happens next']);
    }
}
