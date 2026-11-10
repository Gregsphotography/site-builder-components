<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Rendering\RenderContext;

final class StandardSearchText
{
    private const FIELDS = ['heading', 'eyebrow', 'text', 'body', 'title', 'subtitle', 'excerpt', 'quote', 'name', 'role', 'organisation', 'caption', 'alt', 'question', 'answer', 'address', 'phone', 'email', 'hours', 'label', 'link_label', 'secondary_label', 'description', 'value'];

    public function text(Node $node, array $props, RenderContext $context): string
    {
        if (in_array($node->type, ['standard.form', 'standard.content-listing', 'standard.search-box', 'standard.search-results', 'standard.table-of-contents', 'standard.navigation', 'standard.brand', 'standard.breadcrumbs'], true) || (in_array($node->type, ['standard.carousel', 'standard.timeline'], true) && ($props['content_mode'] ?? 'manual') === 'dynamic')) {
            return '';
        }
        $text = [];
        $collect = function (array $properties, mixed $values) use (&$collect, &$text): void {
            foreach ($properties as $key => $property) {
                $value = data_get($values, $key);
                if (in_array($property->kind, ['text', 'html'], true) && in_array($key, self::FIELDS, true) && is_string($value)) {
                    $text[] = $value;
                } elseif ($property->kind === 'object') {
                    $collect($property->properties, $value);
                } elseif ($property->kind === 'list' && is_array($value)) {
                    foreach ($value as $item) {
                        $collect($property->items->properties, $item);
                    }
                }
            }
        };
        $collect(app(ComponentRegistry::class)->resolve($node->type)->properties(), $props);

        return implode(' ', $text);
    }
}
