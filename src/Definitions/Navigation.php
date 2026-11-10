<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;
use stdClass;

final class Navigation extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.navigation';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.navigation';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['orientation' => 'horizontal'], translations: ['en' => ['label' => 'Main navigation', 'items' => []]]);
    }

    public function properties(): array
    {
        return ['orientation' => PropertyDefinition::enum(['horizontal', 'stacked']), 'label' => PropertyDefinition::text(translated: true, rules: ['max:255']),
            'items' => new PropertyDefinition('list', translated: true, rules: ['max:100'], items: $this->entry(1))];
    }

    private function entry(int $level): PropertyDefinition
    {
        return PropertyDefinition::object([
            'label' => PropertyDefinition::text(rules: ['max:255']),
            'url' => new PropertyDefinition('url', nullable: true, rules: ['max:2048']),
            'page' => new PropertyDefinition('page', nullable: true, rules: ['max:9007199254740991']),
            'overlay' => PropertyDefinition::revision(nullable: true, revisionKind: 'overlay'),
            'children' => new PropertyDefinition('list', rules: ['max:'.($level === 3 ? '0' : '100')], items: $level === 3 ? PropertyDefinition::object([]) : $this->entry($level + 1)),
        ]);
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        $errors = [];
        $items = $values->get('items');
        if (! is_array($items) || $items === []) {
            return [];
        }
        if ($publishing && (! is_string($values->get('label')) || trim($values->get('label')) === '')) {
            $errors[] = new Diagnostic('required_for_publish', '/label', 'Name this navigation landmark.');
        }
        $count = 0;
        $walk = function (array $entries, int $depth, string $path) use (&$walk, &$count, &$errors, $publishing): void {
            foreach ($entries as $index => $entry) {
                if (++$count > 100) {
                    return;
                }
                if (! $entry instanceof stdClass) {
                    continue;
                }
                $location = $path.'/'.$index;
                $children = is_array($entry->children ?? null) ? $entry->children : [];
                if ($depth === 3 && $children !== []) {
                    $errors[] = new Diagnostic('navigation_depth', $location.'/children', 'Navigation supports three levels.');
                }
                if ($publishing) {
                    if (! is_string($entry->label ?? null) || trim($entry->label) === '') {
                        $errors[] = new Diagnostic('required_for_publish', $location.'/label', 'Give this entry a label.');
                    }
                    if ($children === [] && ($entry->overlay ?? null) === null && ($entry->page ?? null) === null && (! is_string($entry->url ?? null) || trim($entry->url) === '')) {
                        $errors[] = new Diagnostic('required_for_publish', $location.'/url', 'A leaf entry needs a page or URL destination.');
                    }
                }
                if ($depth < 3) {
                    $walk($children, $depth + 1, $location.'/children');
                }
            }
        };
        $walk($items, 1, '/items');
        if ($count > 100) {
            $errors[] = new Diagnostic('navigation_count', '/items', 'Use at most 100 navigation entries across all levels.');
        }

        return $errors;
    }
}
