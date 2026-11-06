<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use SiteBuilder\Core\Components\PlacementPolicy;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Contracts\ComponentDefinition;
use SiteBuilder\Core\Contracts\HasDefaultChildren;
use SiteBuilder\Core\Contracts\HasPlacementPolicy;

abstract class LayoutDefinition implements ComponentDefinition, HasDefaultChildren, HasPlacementPolicy
{
    public function version(): int
    {
        return 1;
    }

    public function placement(): PlacementPolicy
    {
        return PlacementPolicy::anywhere(structural: true);
    }

    protected function contentTypes(bool $columns = true): array
    {
        $additional = config('site-builder-components.additional_layout_children', []);
        if (! is_array($additional) || ! array_is_list($additional) || array_any($additional, fn ($type): bool => ! is_string($type) || ! preg_match('/^[a-z0-9][a-z0-9._-]{0,99}$/D', $type) || str_starts_with($type, 'standard.'))) {
            throw new InvalidArgumentException('Additional layout children must be a trusted list of host component types outside the standard namespace.');
        }

        return array_values(array_unique([
            'standard.hero', 'standard.text', 'standard.image', 'standard.image-text',
            'standard.cards', 'standard.cta', 'standard.faq', 'standard.contact-details',
            'standard.brand', 'standard.navigation', 'standard.gallery', 'standard.testimonials',
            'standard.container', ...($columns ? ['standard.columns'] : []),
            'core.global-reference', ...$additional,
        ]));
    }

    protected function text(string $heading = 'Add your content'): Node
    {
        return new Node((string) Str::uuid(), 'standard.text', props: new JsonObject(['variant' => 'default']), translations: new JsonObject(['en' => (object) ['heading' => $heading, 'body' => '<p>Share the useful details that help your visitors take the next step.</p>']]));
    }
}
